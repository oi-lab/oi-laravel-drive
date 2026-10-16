<?php

namespace OiLab\OiLaravelDrive\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelAttachments\OiLaravelAttachments;
use OiLab\OiLaravelDrive\Events\DriveFolderCreated;
use OiLab\OiLaravelDrive\Events\DriveFolderDeleted;
use OiLab\OiLaravelDrive\Events\DriveFolderMoved;
use OiLab\OiLaravelDrive\Events\DriveFolderRestored;
use OiLab\OiLaravelDrive\Events\DriveFolderTrashed;
use OiLab\OiLaravelDrive\Events\DriveFolderUpdated;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * Creates, renames, moves, trashes, restores and deletes the folders of a
 * drive. Deleting a folder takes its subfolders; its files go with it only
 * when asked, and only those filed nowhere else: a file also filed in
 * another folder is simply taken out of the deleted ones.
 */
class DriveFolders
{
    public function __construct(
        protected DriveManager $drives,
        protected DriveFiles $files,
        protected DrivePresenter $presenter,
    ) {}

    /**
     * @param  array<string, mixed>|null  $props
     *
     * @throws DriveException
     */
    public function create(
        Drive $drive,
        string $name,
        ?Folder $parent = null,
        ?Authenticatable $owner = null,
        ?CarbonInterface $createdAt = null,
        ?array $props = null,
    ): Folder {
        $name = $this->cleanName($name);

        if ($parent !== null) {
            $this->assertUsable($parent, $drive);
        }

        $this->assertNameAvailable($drive, $name, $parent?->getKey());

        $owner ??= Auth::user();
        $this->drives->assertMember($drive, $owner);

        $folder = DB::transaction(function () use ($drive, $name, $parent, $owner, $createdAt, $props): Folder {
            $folder = OiLaravelDrive::folderModel()::query()->make([
                'name' => $name,
                'parent_id' => $parent?->getKey(),
                'props' => $props,
            ]);

            if ($createdAt !== null) {
                $folder->created_at = $createdAt;
            }

            $folder->save();
            $this->drives->createEntry($drive, $folder, $owner, $createdAt);

            return $folder;
        });

        event(new DriveFolderCreated($folder, $drive));

        return $folder;
    }

    /**
     * Renames a folder or replaces its props.
     *
     * @param  array<string, mixed>|null  $props
     *
     * @throws DriveException
     */
    public function update(Folder $folder, ?string $name = null, ?array $props = null): Folder
    {
        $this->assertNotTrashed($folder);
        $drive = $this->drives->driveOf($folder);

        if ($name !== null) {
            $name = $this->cleanName($name);

            if ($name !== $folder->name) {
                $this->assertNameAvailable($drive, $name, $folder->parent_id, $folder->getKey());
            }

            $folder->name = $name;
        }

        if ($props !== null) {
            $folder->props = $props;
        }

        if ($folder->isDirty()) {
            $folder->save();
            event(new DriveFolderUpdated($folder, $drive));
        }

        return $folder;
    }

    /**
     * Moves a folder into another one, or to the root when `$parent` is null.
     *
     * @throws DriveException
     */
    public function move(Folder $folder, ?Folder $parent): Folder
    {
        $this->assertNotTrashed($folder);
        $drive = $this->drives->driveOf($folder);

        if ($parent !== null) {
            $this->assertUsable($parent, $drive);

            if (in_array($parent->getKey(), $this->subtreeIds($folder, withTrashed: true), true)) {
                throw DriveException::cyclicMove();
            }
        }

        $from = $folder->parent_id;
        $to = $parent?->getKey();

        if ($from === $to) {
            return $folder;
        }

        $this->assertNameAvailable($drive, $folder->name, $to, $folder->getKey());

        $folder->parent_id = $to;
        $folder->save();

        event(new DriveFolderMoved($folder, $drive, $from, $to));

        return $folder;
    }

    /**
     * Sends a folder and its subfolders to the trash (deletes them for good
     * when `$force` or when the trash is off). With `$withFiles`, the files
     * filed only in these folders go too; the others are taken out of them.
     *
     * @throws DriveException
     */
    public function delete(Folder $folder, bool $withFiles = false, bool $force = false): void
    {
        if ($force || ! OiLaravelDrive::usesTrash()) {
            $this->destroy($folder, $withFiles);

            return;
        }

        $this->assertNotTrashed($folder);
        $drive = $this->drives->driveOf($folder);
        $ids = $this->subtreeIds($folder);

        DB::transaction(function () use ($drive, $folder, $ids, $withFiles): void {
            if ($withFiles) {
                $this->exclusiveFiles($drive, $ids)->get()->each(function (File $file) use ($folder): void {
                    $file->delete();
                    $file->driveEntry?->update(['trashed_with_folder_id' => $folder->getKey()]);
                });
            }

            OiLaravelDrive::folderModel()::query()
                ->whereKey(array_values(array_diff($ids, [$folder->getKey()])))
                ->get()
                ->each(function (Folder $descendant) use ($folder): void {
                    $descendant->delete();
                    $descendant->driveEntry?->update(['trashed_with_folder_id' => $folder->getKey()]);
                });

            $folder->delete();
            $folder->driveEntry?->update(['trashed_with_folder_id' => null]);
        });

        event(new DriveFolderTrashed($folder, $drive, $withFiles));
    }

    /**
     * Brings a folder back from the trash with what was trashed with it. A
     * folder whose parent is gone comes back at the root; a name already
     * taken gets a number.
     *
     * @throws DriveException
     */
    public function restore(Folder $folder): Folder
    {
        if (! $folder->trashed()) {
            throw DriveException::notTrashed();
        }

        $drive = $this->drives->driveOf($folder);

        DB::transaction(function () use ($drive, $folder): void {
            if ($folder->parent_id !== null
                && ! OiLaravelDrive::folderModel()::query()->whereKey($folder->parent_id)->exists()) {
                $folder->parent_id = null;
            }

            $folder->name = $this->availableName($drive, $folder->name, $folder->parent_id, $folder->getKey());
            $folder->restore();
            $folder->driveEntry?->update(['trashed_with_folder_id' => null]);

            $trashedWith = fn (Builder $query) => $query->where('trashed_with_folder_id', $folder->getKey());

            OiLaravelDrive::folderModel()::query()->onlyTrashed()->whereHas('driveEntry', $trashedWith)->get()
                ->each(function (Folder $descendant): void {
                    $descendant->restore();
                    $descendant->driveEntry?->update(['trashed_with_folder_id' => null]);
                });

            OiLaravelDrive::fileModel()::query()->onlyTrashed()->whereHas('driveEntry', $trashedWith)->get()
                ->each(function (File $file): void {
                    $file->restore();
                    $file->driveEntry?->update(['trashed_with_folder_id' => null]);
                });
        });

        event(new DriveFolderRestored($folder, $drive));

        return $folder;
    }

    /**
     * Deletes a folder and its subfolders for good, trashed or not. The files
     * trashed with them are deleted too, and with `$withFiles` those filed
     * only in them; the others are taken out of them.
     */
    public function destroy(Folder $folder, bool $withFiles = false): void
    {
        $drive = $this->drives->driveOf($folder);
        $this->releaseIndependentlyTrashed($folder);
        $ids = $this->subtreeIds($folder, withTrashed: true);

        $trashedWith = $this->drives->fileQuery($drive)->onlyTrashed()
            ->whereHas('driveEntry', fn (Builder $query) => $query->whereIn('trashed_with_folder_id', $ids))
            ->get();

        $exclusive = $withFiles ? $this->exclusiveFiles($drive, $ids, withTrashedFolders: true)->withTrashed()->get() : collect();

        $trashedWith->merge($exclusive)->unique(fn (File $file) => $file->getKey())
            ->each(fn (File $file) => $this->files->destroy($file));

        $folders = OiLaravelDrive::folderModel()::query()->withTrashed()->whereKey($ids)->get()->keyBy(fn (Folder $folder) => $folder->getKey());
        $events = [];

        DB::transaction(function () use ($ids, $folders, $drive, &$events): void {
            OiLaravelAttachments::attachmentModel()::query()
                ->where('collection', OiLaravelDrive::collection())
                ->where('attachable_type', (new (OiLaravelDrive::folderModel()))->getMorphClass())
                ->whereIn('attachable_id', $ids)
                ->delete();

            foreach (array_reverse($ids) as $id) {
                $descendant = $folders->get($id);

                if ($descendant === null) {
                    continue;
                }

                $events[] = new DriveFolderDeleted($this->presenter->folder($descendant), $drive);
                $descendant->driveEntry?->delete();
                $descendant->forceDelete();
            }
        });

        foreach ($events as $event) {
            event($event);
        }
    }

    /**
     * Subfolders sent to the trash on their own stay there, restorable at the
     * root, when an ancestor is deleted for good (the `parent_id` foreign key
     * would take them with it).
     */
    protected function releaseIndependentlyTrashed(Folder $folder): void
    {
        OiLaravelDrive::folderModel()::query()->onlyTrashed()
            ->whereKey(array_values(array_diff($this->subtreeIds($folder, withTrashed: true), [$folder->getKey()])))
            ->whereHas('driveEntry', fn (Builder $query) => $query->whereNull('trashed_with_folder_id'))
            ->get()
            ->each(function (Folder $descendant): void {
                $descendant->parent_id = null;
                $descendant->saveQuietly();
            });
    }

    /**
     * The folder and every folder below it, parents before children.
     *
     * @return list<int>
     */
    public function subtreeIds(Folder $folder, bool $withTrashed = false): array
    {
        $ids = [$folder->getKey()];
        $level = [$folder->getKey()];

        while ($level !== [] && count($ids) < 100_000) {
            $level = OiLaravelDrive::folderModel()::query()
                ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
                ->whereIn('parent_id', $level)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->diff($ids)
                ->values()
                ->all();

            array_push($ids, ...$level);
        }

        return $ids;
    }

    /**
     * @throws DriveException when the folder is trashed or in another drive
     */
    public function assertUsable(Folder $folder, Drive $drive): void
    {
        $this->assertNotTrashed($folder);

        if ($this->drives->driveOf($folder)->getKey() !== $drive->getKey()) {
            throw DriveException::otherDrive();
        }
    }

    /**
     * The files of the drive filed in these folders and in no other folder.
     *
     * @param  list<int>  $ids
     * @return Builder<File>
     */
    protected function exclusiveFiles(Drive $drive, array $ids, bool $withTrashedFolders = false): Builder
    {
        $key = (new (OiLaravelDrive::folderModel()))->getQualifiedKeyName();

        $scope = fn (bool $inside) => function (Builder $query) use ($ids, $inside, $withTrashedFolders, $key): void {
            if ($withTrashedFolders) {
                $query->withTrashed();
            }

            $inside ? $query->whereIn($key, $ids) : $query->whereNotIn($key, $ids);
        };

        return $this->drives->fileQuery($drive)
            ->whereHas('driveFolders', $scope(true))
            ->whereDoesntHave('driveFolders', $scope(false));
    }

    /**
     * @throws DriveException
     */
    protected function assertNameAvailable(Drive $drive, string $name, ?int $parentId, ?int $ignoreId = null): void
    {
        if (OiLaravelDrive::requiresUniqueFolderNames() && $this->nameTaken($drive, $name, $parentId, $ignoreId)) {
            throw DriveException::folderNameTaken($name);
        }
    }

    protected function availableName(Drive $drive, string $name, ?int $parentId, ?int $ignoreId = null): string
    {
        if (! OiLaravelDrive::requiresUniqueFolderNames()) {
            return $name;
        }

        $candidate = $name;

        for ($number = 2; $this->nameTaken($drive, $candidate, $parentId, $ignoreId); $number++) {
            $candidate = "{$name} ({$number})";
        }

        return $candidate;
    }

    protected function nameTaken(Drive $drive, string $name, ?int $parentId, ?int $ignoreId): bool
    {
        return $this->drives->folderQuery($drive)
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * @throws DriveException
     */
    protected function assertNotTrashed(Folder $folder): void
    {
        if ($folder->trashed()) {
            throw DriveException::trashed();
        }
    }

    protected function cleanName(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $name));

        return mb_substr($name !== '' ? $name : 'Sans titre', 0, 255);
    }
}
