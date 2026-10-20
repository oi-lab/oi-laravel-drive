<?php

namespace OiLab\OiLaravelDrive\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Contracts\DriveableResolver;
use OiLab\OiLaravelDrive\Contracts\ShareTargets;
use OiLab\OiLaravelDrive\Data\DriveFolderData;
use OiLab\OiLaravelDrive\Data\DriveFolderSharingData;
use OiLab\OiLaravelDrive\Data\DriveListingData;
use OiLab\OiLaravelDrive\Data\DriveShareData;
use OiLab\OiLaravelDrive\Data\UserSummaryData;
use OiLab\OiLaravelDrive\Events\DriveFolderShared;
use OiLab\OiLaravelDrive\Events\DriveFolderUnshared;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Models\DriveFolderShare;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * Shares folders, read-only, with other entities. A member of an entity a
 * folder is shared with reads that folder, its subfolders and the files filed
 * in them — from that entity only while `driveable.current_only` is on. A
 * trashed folder is shared with nobody until it is restored.
 */
class DriveShares
{
    public function __construct(
        protected DriveManager $drives,
        protected DriveFolders $folders,
        protected DriveLinks $links,
        protected DriveableResolver $resolver,
        protected DrivePresenter $presenter,
        protected ShareTargets $targets,
    ) {}

    /**
     * Shares the folder with the entity, on behalf of `$by` (the signed-in
     * user by default), who must be allowed to reach it (ShareTargets);
     * sharing again with an entity already shared with changes nothing.
     *
     * @throws DriveException when the entity owns the drive, is of another class or out of reach
     */
    public function share(Folder $folder, Model $shareable, ?Authenticatable $by = null): DriveFolderShare
    {
        $by ??= Auth::user();

        $share = OiLaravelDrive::shareModel()::query()->firstOrNew([
            'folder_id' => $folder->getKey(),
            'shareable_type' => $shareable->getMorphClass(),
            'shareable_id' => $shareable->getKey(),
        ]);

        if (! $share->exists) {
            $this->assertShareable($folder, $shareable, $by);
            $share->user_id = $by?->getAuthIdentifier();
            $share->save();
            event(new DriveFolderShared($folder, $shareable));
        }

        return $share;
    }

    public function unshare(Folder $folder, Model $shareable): bool
    {
        $deleted = $this->sharesQuery($folder)
            ->where('shareable_type', $shareable->getMorphClass())
            ->where('shareable_id', $shareable->getKey())
            ->delete() > 0;

        if ($deleted) {
            event(new DriveFolderUnshared($folder, $shareable));
        }

        return $deleted;
    }

    /**
     * Shares the folder with exactly these entities. An entity it is already
     * shared with is kept even when it is no longer among the targets.
     *
     * @param  iterable<Model>  $shareables
     *
     * @throws DriveException
     */
    public function sync(Folder $folder, iterable $shareables, ?Authenticatable $by = null): void
    {
        $by ??= Auth::user();
        $wanted = collect($shareables)->keyBy(fn (Model $shareable): string => $this->keyOf($shareable));
        $existing = $this->sharesQuery($folder)->get()->map(fn (DriveFolderShare $share): string => $share->shareable_type.':'.$share->shareable_id);

        $wanted->except($existing->all())->each(fn (Model $shareable) => $this->assertShareable($folder, $shareable, $by));

        $this->sharesQuery($folder)->with('shareable')->get()
            ->reject(fn (DriveFolderShare $share): bool => $wanted->has($share->shareable_type.':'.$share->shareable_id))
            ->each(function (DriveFolderShare $share) use ($folder): void {
                $share->delete();

                if ($share->shareable !== null) {
                    event(new DriveFolderUnshared($folder, $share->shareable));
                }
            });

        $wanted->each(fn (Model $shareable) => $this->share($folder, $shareable, $by));
    }

    /**
     * The class a folder of this drive is shared with.
     *
     * @return class-string<Model>
     */
    public function shareableModel(Folder $folder): string
    {
        return OiLaravelDrive::shareableModel() ?? $this->drives->driveOf($folder)->driveable::class;
    }

    /**
     * The entities the user may share the folder with, the entity owning the
     * drive left out.
     *
     * @return Builder<Model>
     */
    public function targets(Authenticatable $user, Folder $folder): Builder
    {
        $model = $this->shareableModel($folder);
        $owner = $this->drives->driveOf($folder)->driveable;

        return $this->targets->query($user, $folder, $model)
            ->when($owner instanceof $model, fn (Builder $query) => $query->whereKeyNot($owner->getKey()));
    }

    /**
     * An entity the user may share the folder with, by key or by name.
     *
     * @throws ModelNotFoundException
     */
    public function findShareable(Authenticatable $user, Folder $folder, int|string $keyOrName): Model
    {
        return $this->targets($user, $folder)
            ->where(fn (Builder $query) => $query
                ->when(is_numeric($keyOrName), fn (Builder $query) => $query->whereKey((int) $keyOrName))
                ->orWhere(OiLaravelDrive::shareableNameAttribute(), (string) $keyOrName))
            ->firstOrFail();
    }

    /**
     * An entity the folder is shared with, by key or by name.
     *
     * @throws ModelNotFoundException
     */
    public function findShared(Folder $folder, int|string $keyOrName): Model
    {
        $model = $this->shareableModel($folder);
        $keys = $this->sharesQuery($folder)->where('shareable_type', (new $model)->getMorphClass())->pluck('shareable_id');

        return $model::query()
            ->whereKey($keys)
            ->where(fn (Builder $query) => $query
                ->when(is_numeric($keyOrName), fn (Builder $query) => $query->whereKey((int) $keyOrName))
                ->orWhere(OiLaravelDrive::shareableNameAttribute(), (string) $keyOrName))
            ->firstOrFail();
    }

    /**
     * @return list<DriveShareData>
     */
    public function sharesOf(Folder $folder): array
    {
        $nameAttribute = OiLaravelDrive::shareableNameAttribute();

        return $this->sharesQuery($folder)->with(['shareable', 'user'])->oldest('id')->get()
            ->filter(fn (DriveFolderShare $share): bool => $share->shareable !== null)
            ->map(fn (DriveFolderShare $share): DriveShareData => new DriveShareData(
                folder_id: $folder->uuid,
                shareable_type: $share->shareable_type,
                shareable_id: $share->shareable_id,
                shareable_name: (string) ($share->shareable->getAttribute($nameAttribute) ?? '#'.$share->shareable_id),
                shared_by: $share->user ? UserSummaryData::fromUser($share->user) : null,
                created_at: $share->created_at,
            ))
            ->values()
            ->all();
    }

    /**
     * The entities a folder is shared with and its public link.
     */
    public function sharing(Folder $folder): DriveFolderSharingData
    {
        $link = $this->links->linkOf($folder);

        return new DriveFolderSharingData(
            folder: $this->presenter->folder($folder),
            shares: $this->sharesOf($folder),
            link: $link ? $this->links->present($link) : null,
        );
    }

    /**
     * The folders shared with the entity the user works in, the topmost
     * ones only (a subfolder shared as well is reached through its parent).
     *
     * @return list<DriveFolderData>
     */
    public function sharedWith(Authenticatable $user): array
    {
        $entity = $this->resolver->current($user);

        if ($entity === null || ! $this->resolver->isMember($user, $entity)) {
            return [];
        }

        $folderIds = OiLaravelDrive::shareModel()::query()
            ->where('shareable_type', $entity->getMorphClass())
            ->where('shareable_id', $entity->getKey())
            ->pluck('folder_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $folders = OiLaravelDrive::folderModel()::query()
            ->whereKey($folderIds)
            ->has('driveEntry')
            ->orderBy('name')
            ->get()
            ->reject(fn (Folder $folder): bool => array_intersect(array_slice($this->ancestorIds($folder), 1), $folderIds) !== []);

        return $this->presenter->folders($folders);
    }

    /**
     * The shared folder through which the user reads the item: the topmost
     * folder above it (or itself) shared with an entity of the user. Null
     * when nothing shared reaches the item.
     */
    public function shareRoot(Authenticatable $user, Folder|File $item): ?Folder
    {
        if ($item->trashed()) {
            return null;
        }

        $folders = $item instanceof Folder ? collect([$item]) : $item->driveFolders()->get();

        foreach ($folders as $folder) {
            $ancestors = $this->ancestorIds($folder);

            $shares = OiLaravelDrive::shareModel()::query()
                ->whereIn('folder_id', $ancestors)
                ->with('shareable')
                ->get()
                ->filter(fn (DriveFolderShare $share): bool => $share->shareable !== null && $this->reachesEntity($user, $share->shareable));

            if ($shares->isNotEmpty()) {
                $rootId = collect($ancestors)->reverse()->first(fn (int $id): bool => $shares->contains('folder_id', $id));

                return OiLaravelDrive::folderModel()::query()->find($rootId);
            }
        }

        return null;
    }

    /**
     * Whether the user reads the item through a folder shared with them.
     */
    public function reaches(Authenticatable $user, Folder|File $item): bool
    {
        return $this->shareRoot($user, $item) !== null;
    }

    /**
     * Whether the item is the root folder or lies below it.
     */
    public function within(Folder $root, Folder|File $item): bool
    {
        if ($item->trashed()) {
            return false;
        }

        $folders = $item instanceof Folder ? collect([$item]) : $item->driveFolders()->get();

        return $folders->contains(fn (Folder $folder): bool => in_array($root->getKey(), $this->ancestorIds($folder), true));
    }

    /**
     * The content of a folder seen from a shared root: the breadcrumbs start
     * at the root, never showing the folders above it.
     *
     * @throws ModelNotFoundException when the folder is not below the root
     */
    public function contents(Folder $root, ?Folder $folder = null): DriveListingData
    {
        $folder ??= $root;

        if (! $this->within($root, $folder)) {
            throw (new ModelNotFoundException)->setModel($folder::class, [$folder->getKey()]);
        }

        $listing = $this->drives->contents($this->drives->driveOf($folder), $folder);
        $start = collect($listing->breadcrumbs)->search(fn ($crumb): bool => $crumb->id === $root->uuid);

        return new DriveListingData(
            drive: $listing->drive,
            folder: $listing->folder,
            breadcrumbs: array_values(array_slice($listing->breadcrumbs, $start === false ? 0 : $start)),
            folders: $listing->folders,
            files: $listing->files,
            notice: $listing->notice,
        );
    }

    /**
     * The folder and every folder above it, the folder first.
     *
     * @return list<int>
     */
    public function ancestorIds(Folder $folder): array
    {
        $ids = [$folder->getKey()];
        $parentId = $folder->parent_id;

        while ($parentId !== null && count($ids) < 100 && ! in_array($parentId, $ids, true)) {
            $ids[] = (int) $parentId;
            $parentId = OiLaravelDrive::folderModel()::query()->whereKey($parentId)->value('parent_id');
        }

        return $ids;
    }

    /**
     * @throws DriveException
     */
    protected function assertShareable(Folder $folder, Model $shareable, ?Authenticatable $by): void
    {
        if (! is_a($shareable, $this->shareableModel($folder))) {
            throw DriveException::shareTargetRefused();
        }

        if ($this->drives->driveOf($folder)->driveable?->is($shareable)) {
            throw DriveException::shareWithOwner();
        }

        if ($by !== null && ! $this->targets($by, $folder)->whereKey($shareable->getKey())->exists()) {
            throw DriveException::shareTargetRefused();
        }

        $this->folders->assertUsable($folder, $this->drives->driveOf($folder));
    }

    protected function reachesEntity(Authenticatable $user, Model $entity): bool
    {
        if (config('oi-laravel-drive.driveable.current_only', true)
            && ! $this->resolver->current($user)?->is($entity)) {
            return false;
        }

        return $this->resolver->isMember($user, $entity);
    }

    /**
     * @return Builder<DriveFolderShare>
     */
    protected function sharesQuery(Folder $folder): Builder
    {
        return OiLaravelDrive::shareModel()::query()->where('folder_id', $folder->getKey());
    }

    protected function keyOf(Model $shareable): string
    {
        return $shareable->getMorphClass().':'.$shareable->getKey();
    }
}
