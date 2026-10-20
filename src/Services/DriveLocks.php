<?php

namespace OiLab\OiLaravelDrive\Services;

use Illuminate\Database\Eloquent\Builder;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * Locks of the items the host application generates: a `locked` folder or
 * file cannot be renamed, moved or deleted, a `sealed` folder takes no folder
 * or file and none can be taken out of it. Both only stop users: the host
 * changes its own items inside `DriveLocks::bypass()`.
 */
class DriveLocks
{
    protected static int $bypassDepth = 0;

    /**
     * Runs the callback with the locks lifted, for the host application.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public static function bypass(callable $callback): mixed
    {
        static::$bypassDepth++;

        try {
            return $callback();
        } finally {
            static::$bypassDepth--;
        }
    }

    public static function bypassed(): bool
    {
        return static::$bypassDepth > 0;
    }

    public function isLocked(Folder|File $item): bool
    {
        return (bool) $item->driveEntry?->locked;
    }

    public function isSealed(Folder $folder): bool
    {
        return (bool) $folder->driveEntry?->sealed;
    }

    /**
     * @throws DriveException when the item is locked
     */
    public function assertUnlocked(Folder|File $item): void
    {
        if (! static::bypassed() && $this->isLocked($item)) {
            throw DriveException::locked();
        }
    }

    /**
     * @throws DriveException when the folder is sealed
     */
    public function assertUnsealed(?Folder $folder): void
    {
        if ($folder !== null && ! static::bypassed() && $this->isSealed($folder)) {
            throw DriveException::sealed($folder->name);
        }
    }

    /**
     * A folder tree can be deleted only if nothing in it is locked: its
     * folders, and with `$withFiles` the files that would go with them.
     *
     * @param  list<int>  $folderIds
     * @param  Builder<File>|null  $exclusiveFiles
     *
     * @throws DriveException
     */
    public function assertTreeUnlocked(array $folderIds, ?Builder $exclusiveFiles = null): void
    {
        if (static::bypassed()) {
            return;
        }

        $locked = fn (Builder $query) => $query->where('locked', true);

        if (OiLaravelDrive::folderModel()::query()->whereKey($folderIds)->whereHas('driveEntry', $locked)->exists()
            || $exclusiveFiles?->whereHas('driveEntry', $locked)->exists()) {
            throw DriveException::lockedContent();
        }
    }
}
