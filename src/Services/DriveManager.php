<?php

namespace OiLab\OiLaravelDrive\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Contracts\DriveableResolver;
use OiLab\OiLaravelDrive\Data\DriveBreadcrumbData;
use OiLab\OiLaravelDrive\Data\DriveItemsData;
use OiLab\OiLaravelDrive\Data\DriveListingData;
use OiLab\OiLaravelDrive\Enums\DriveEntryKind;
use OiLab\OiLaravelDrive\Events\DriveCreated;
use OiLab\OiLaravelDrive\Events\DriveDeleted;
use OiLab\OiLaravelDrive\Events\DriveEntryOwnerChanged;
use OiLab\OiLaravelDrive\Events\DriveUpdated;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\Models\DriveEntry;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * The drives of an entity, the lookup of their folders and files, listings,
 * search, trash and owners. Folder and file operations live in DriveFolders
 * and DriveFiles.
 */
class DriveManager
{
    public function __construct(
        protected DriveableResolver $resolver,
        protected DrivePresenter $presenter,
    ) {}

    public function resolver(): DriveableResolver
    {
        return $this->resolver;
    }

    /**
     * The entity the user works in.
     *
     * @throws DriveException when the user has none
     */
    public function currentDriveable(Authenticatable $user): Model
    {
        return $this->resolver->current($user) ?? throw DriveException::noDriveable();
    }

    /**
     * The default drive of an entity (its first one), created on first use.
     */
    public function driveFor(Model $driveable): Drive
    {
        $drive = $this->drivesQuery($driveable)->oldest('id')->first();

        return $drive ?? $this->createDrive($driveable, (string) config('oi-laravel-drive.drives.default_name', 'Documents'));
    }

    /**
     * Every drive of an entity, the default one created when none exists.
     *
     * @return Collection<int, Drive>
     */
    public function drivesOf(Model $driveable): Collection
    {
        $drives = $this->drivesQuery($driveable)->with('user')->orderBy('name')->get();

        if ($drives->isEmpty()) {
            $drives->push($this->driveFor($driveable));
        }

        return $drives;
    }

    /**
     * The drive named by its UUID, or the default drive of the user's entity.
     *
     * @throws ModelNotFoundException|DriveException
     */
    public function resolveDrive(Authenticatable $user, ?string $uuid = null): Drive
    {
        if ($uuid !== null && $uuid !== '') {
            return OiLaravelDrive::driveModel()::query()->where('uuid', $uuid)->firstOrFail();
        }

        return $this->driveFor($this->currentDriveable($user));
    }

    /**
     * @param  array<string, mixed>|null  $props
     *
     * @throws DriveException when the entity already has its single drive
     */
    public function createDrive(Model $driveable, string $name, ?Authenticatable $owner = null, ?array $props = null): Drive
    {
        if (! OiLaravelDrive::allowsMultipleDrives() && $this->drivesQuery($driveable)->exists()) {
            throw DriveException::singleDrive();
        }

        if ($owner !== null && ! $this->resolver->isMember($owner, $driveable)) {
            throw DriveException::notMember();
        }

        $user = Auth::user();
        $owner ??= $user !== null && $this->resolver->isMember($user, $driveable) ? $user : null;

        $drive = OiLaravelDrive::driveModel()::query()->create([
            'driveable_type' => $driveable->getMorphClass(),
            'driveable_id' => $driveable->getKey(),
            'name' => trim($name),
            'user_id' => $owner?->getAuthIdentifier(),
            'props' => $props,
        ]);

        event(new DriveCreated($drive));

        return $drive;
    }

    /**
     * @param  array<string, mixed>|null  $props
     */
    public function updateDrive(Drive $drive, ?string $name = null, ?array $props = null): Drive
    {
        if ($name !== null) {
            $drive->name = trim($name);
        }

        if ($props !== null) {
            $drive->props = $props;
        }

        if ($drive->isDirty()) {
            $drive->save();
            event(new DriveUpdated($drive));
        }

        return $drive;
    }

    /**
     * Deletes a drive for good, with all its folders and files.
     */
    public function deleteDrive(Drive $drive): void
    {
        $data = $this->presenter->drive($drive);

        DB::transaction(function () use ($drive): void {
            $folders = app(DriveFolders::class);

            while ($root = $this->folderQuery($drive)->withTrashed()->whereNull('parent_id')->first()) {
                $folders->destroy($root, withFiles: true);
            }

            $files = app(DriveFiles::class);

            $this->fileQuery($drive)->withTrashed()->get()->each(fn (File $file) => $files->destroy($file));

            $drive->entries()->delete();
            $drive->forceDelete();
        });

        event(new DriveDeleted($data));
    }

    /**
     * The folders of a drive.
     *
     * @return Builder<Folder>
     */
    public function folderQuery(Drive $drive): Builder
    {
        return OiLaravelDrive::folderModel()::query()
            ->whereHas('driveEntry', fn (Builder $query) => $query->where('drive_id', $drive->getKey()));
    }

    /**
     * The files of a drive.
     *
     * @return Builder<File>
     */
    public function fileQuery(Drive $drive): Builder
    {
        return OiLaravelDrive::fileModel()::query()
            ->whereHas('driveEntry', fn (Builder $query) => $query->where('drive_id', $drive->getKey()));
    }

    /**
     * The files of a drive filed in no folder (or only in trashed ones).
     *
     * @return Builder<File>
     */
    public function rootFileQuery(Drive $drive): Builder
    {
        return $this->fileQuery($drive)->whereDoesntHave('driveFolders');
    }

    /**
     * A folder of any drive, by UUID.
     *
     * @throws ModelNotFoundException
     */
    public function findFolder(string $uuid, bool $withTrashed = false): Folder
    {
        return OiLaravelDrive::folderModel()::query()
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->where('uuid', $uuid)
            ->has('driveEntry')
            ->firstOrFail();
    }

    /**
     * A file of any drive, by UUID.
     *
     * @throws ModelNotFoundException
     */
    public function findFile(string $uuid, bool $withTrashed = false): File
    {
        return OiLaravelDrive::fileModel()::query()
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->where('uuid', $uuid)
            ->has('driveEntry')
            ->firstOrFail();
    }

    public function entryOf(Folder|File $item): DriveEntry
    {
        return $item->driveEntry ?? throw (new ModelNotFoundException)->setModel($item::class, [$item->getKey()]);
    }

    public function driveOf(Folder|File $item): Drive
    {
        return $this->entryOf($item)->drive;
    }

    /**
     * The folders and files directly in a folder, or at the root.
     */
    public function contents(Drive $drive, ?Folder $folder = null): DriveListingData
    {
        $folders = $this->folderQuery($drive)
            ->where('parent_id', $folder?->getKey())
            ->orderBy('name')
            ->get();

        $files = ($folder === null ? $this->rootFileQuery($drive) : $this->fileQuery($drive)
            ->whereHas('driveFolders', fn (Builder $query) => $query->whereKey($folder->getKey())))
            ->orderBy('filename_download')
            ->get();

        return new DriveListingData(
            drive: $this->presenter->drive($drive),
            folder: $folder ? $this->presenter->folder($folder) : null,
            breadcrumbs: $folder ? $this->breadcrumbs($folder) : [],
            folders: $this->presenter->folders($folders),
            files: $this->presenter->files($files),
        );
    }

    /**
     * The path from the root to the folder, the folder included.
     *
     * @return list<DriveBreadcrumbData>
     */
    public function breadcrumbs(Folder $folder): array
    {
        $path = [];
        $current = $folder;

        while ($current !== null && count($path) < 100) {
            array_unshift($path, new DriveBreadcrumbData(id: $current->uuid, name: $current->name));
            $current = $current->parent_id === null
                ? null
                : OiLaravelDrive::folderModel()::query()->withTrashed()->find($current->parent_id);
        }

        return $path;
    }

    /**
     * Folders by name, files by name, title or description, case-insensitive
     * (`ilike` on PostgreSQL).
     */
    public function search(Drive $drive, string $term, int $limit = 50): DriveItemsData
    {
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        $folders = $this->folderQuery($drive)
            ->whereLike('name', $like)
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $files = $this->fileQuery($drive)
            ->where(fn (Builder $query) => $query
                ->whereLike('filename_download', $like)
                ->orWhereLike('title', $like)
                ->orWhereLike('description', $like))
            ->orderBy('filename_download')
            ->limit($limit)
            ->get();

        return new DriveItemsData(
            drive: $this->presenter->drive($drive),
            folders: $this->presenter->folders($folders),
            files: $this->presenter->files($files),
        );
    }

    /**
     * What was deleted directly; what went with a folder comes back with it.
     */
    public function trash(Drive $drive): DriveItemsData
    {
        $direct = fn (Builder $query) => $query->where('drive_id', $drive->getKey())->whereNull('trashed_with_folder_id');

        $folders = OiLaravelDrive::folderModel()::query()->onlyTrashed()
            ->whereHas('driveEntry', $direct)
            ->latest('deleted_at')
            ->get();

        $files = OiLaravelDrive::fileModel()::query()->onlyTrashed()
            ->whereHas('driveEntry', $direct)
            ->latest('deleted_at')
            ->get();

        return new DriveItemsData(
            drive: $this->presenter->drive($drive),
            folders: $this->presenter->folders($folders),
            files: $this->presenter->files($files),
        );
    }

    /**
     * Deletes for good everything in the trash of a drive.
     *
     * @return int The number of folders and files deleted directly
     */
    public function emptyTrash(Drive $drive): int
    {
        return $this->purge($drive, null);
    }

    /**
     * Deletes for good what has been in the trash for more than `$days` days
     * (`trash.purge_after_days` by default), in every drive.
     */
    public function purgeTrash(?int $days = null): int
    {
        $days ??= config('oi-laravel-drive.trash.purge_after_days');

        if ($days === null || $days === '') {
            return 0;
        }

        return $this->purge(null, now()->subDays((int) $days));
    }

    /**
     * Gives a folder or a file a new owner (null: no owner).
     *
     * @throws DriveException when the user is not a member of the drive's entity
     */
    public function setOwner(Folder|File $item, ?Authenticatable $owner): DriveEntry
    {
        $entry = $this->entryOf($item);
        $this->assertMember($entry->drive, $owner);

        $from = $entry->user_id;
        $entry->user_id = $owner?->getAuthIdentifier();

        if ($entry->isDirty('user_id')) {
            $entry->save();
            event(new DriveEntryOwnerChanged($item, $entry->drive, $from, $entry->user_id));
        }

        return $entry;
    }

    /**
     * Sets the date of creation of a folder or a file (e.g. on import).
     */
    public function setCreatedAt(Folder|File $item, CarbonInterface $createdAt): DriveEntry
    {
        $entry = $this->entryOf($item);
        $entry->created_at = $createdAt;
        $entry->save();

        $item->created_at = $createdAt;
        $item->saveQuietly();

        return $entry;
    }

    /**
     * @throws DriveException
     */
    public function assertMember(Drive $drive, ?Authenticatable $user): void
    {
        if ($user === null) {
            return;
        }

        $driveable = $drive->driveable;

        if ($driveable === null || ! $this->resolver->isMember($user, $driveable)) {
            throw DriveException::notMember();
        }
    }

    /**
     * Records a new folder or file in a drive.
     */
    public function createEntry(Drive $drive, Folder|File $item, ?Authenticatable $owner = null, ?CarbonInterface $createdAt = null): DriveEntry
    {
        $entry = OiLaravelDrive::entryModel()::query()->make([
            'drive_id' => $drive->getKey(),
            'entry_type' => $item->getMorphClass(),
            'entry_id' => $item->getKey(),
            'kind' => $item instanceof Folder ? DriveEntryKind::Folder : DriveEntryKind::File,
            'user_id' => $owner?->getAuthIdentifier(),
        ]);

        if ($createdAt !== null) {
            $entry->created_at = $createdAt;
        }

        $entry->save();
        $item->setRelation('driveEntry', $entry->setRelation('drive', $drive));

        return $entry;
    }

    /**
     * @return Builder<Drive>
     */
    protected function drivesQuery(Model $driveable): Builder
    {
        return OiLaravelDrive::driveModel()::query()
            ->where('driveable_type', $driveable->getMorphClass())
            ->where('driveable_id', $driveable->getKey());
    }

    protected function purge(?Drive $drive, ?CarbonInterface $before): int
    {
        $direct = fn (Builder $query) => $query
            ->when($drive, fn (Builder $query) => $query->where('drive_id', $drive->getKey()))
            ->whereNull('trashed_with_folder_id');

        $folders = OiLaravelDrive::folderModel()::query()->onlyTrashed()
            ->whereHas('driveEntry', $direct)
            ->when($before, fn (Builder $query) => $query->where('deleted_at', '<', $before))
            ->get();

        $files = OiLaravelDrive::fileModel()::query()->onlyTrashed()
            ->whereHas('driveEntry', $direct)
            ->when($before, fn (Builder $query) => $query->where('deleted_at', '<', $before))
            ->get();

        $folderService = app(DriveFolders::class);
        $fileService = app(DriveFiles::class);

        $folders->each(fn (Folder $folder) => $folderService->destroy($folder));
        $files->each(fn (File $file) => $fileService->destroy($file));

        return $folders->count() + $files->count();
    }
}
