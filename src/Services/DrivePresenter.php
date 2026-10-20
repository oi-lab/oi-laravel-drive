<?php

namespace OiLab\OiLaravelDrive\Services;

use Illuminate\Support\Collection;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Data\DriveData;
use OiLab\OiLaravelDrive\Data\DriveFileData;
use OiLab\OiLaravelDrive\Data\DriveFolderData;
use OiLab\OiLaravelDrive\Data\DriveNoticeData;
use OiLab\OiLaravelDrive\Data\UserSummaryData;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * Turns drives, folders and files into their Data objects, with the
 * relations they need loaded once for a whole list.
 */
class DrivePresenter
{
    public function drive(Drive $drive): DriveData
    {
        return DriveData::fromModel($drive->loadMissing('user'));
    }

    public function folder(Folder $folder): DriveFolderData
    {
        return $this->folders(collect([$folder]))[0];
    }

    public function file(File $file): DriveFileData
    {
        return $this->files(collect([$file]))[0];
    }

    /**
     * @param  Collection<int, Folder>  $folders
     * @return list<DriveFolderData>
     */
    public function folders(Collection $folders): array
    {
        $folders = $folders->values();

        if ($folders->isEmpty()) {
            return [];
        }

        $collection = $folders->first()->newCollection($folders->all())->loadMissing([
            'driveEntry.user',
            'driveEntry.drive',
            'parent' => fn ($query) => $query->withTrashed(),
        ]);

        $collection->loadCount('driveShares');
        $linked = OiLaravelDrive::linkModel()::query()
            ->whereIn('folder_id', $collection->modelKeys())
            ->pluck('folder_id')
            ->flip();

        return $folders->map(fn (Folder $folder): DriveFolderData => new DriveFolderData(
            id: $folder->uuid,
            drive_id: (string) $folder->driveEntry?->drive?->uuid,
            name: $folder->name,
            parent_id: $folder->parent?->uuid,
            owner: $folder->driveEntry?->user ? UserSummaryData::fromUser($folder->driveEntry->user) : null,
            created_at: $folder->driveEntry?->created_at ?? $folder->created_at,
            updated_at: $folder->updated_at,
            deleted_at: $folder->deleted_at,
            props: $folder->props,
            shares_count: (int) $folder->drive_shares_count,
            has_link: $linked->has($folder->getKey()),
            key: $folder->driveEntry?->key,
            locked: (bool) $folder->driveEntry?->locked,
            sealed: (bool) $folder->driveEntry?->sealed,
        ))->all();
    }

    /**
     * @param  Collection<int, File>  $files
     * @return list<DriveFileData>
     */
    public function files(Collection $files): array
    {
        $files = $files->values();

        if ($files->isEmpty()) {
            return [];
        }

        $files->first()->newCollection($files->all())->loadMissing([
            'driveEntry.user',
            'driveEntry.drive',
            'driveFolders',
        ]);

        return $files->map(fn (File $file): DriveFileData => new DriveFileData(
            id: $file->uuid,
            drive_id: (string) $file->driveEntry?->drive?->uuid,
            name: $file->filename_download,
            title: $file->title,
            description: $file->description,
            mimetype: $file->mimetype,
            size: (int) $file->filesize,
            width: $file->width,
            height: $file->height,
            md5: $file->md5,
            folder_ids: $file->driveFolders->pluck('uuid')->values()->all(),
            owner: $file->driveEntry?->user ? UserSummaryData::fromUser($file->driveEntry->user) : null,
            created_at: $file->driveEntry?->created_at ?? $file->created_at,
            updated_at: $file->updated_at,
            deleted_at: $file->deleted_at,
            props: $file->props,
            key: $file->driveEntry?->key,
            role: $file->driveEntry?->role,
            locked: (bool) $file->driveEntry?->locked,
            is_link: (bool) $file->driveEntry?->isLink(),
            source_type: $file->driveEntry?->source_type,
            source_id: $file->driveEntry?->source_id !== null ? (string) $file->driveEntry->source_id : null,
        ))->all();
    }

    /**
     * The notice of a folder with its markdown text.
     */
    public function notice(File $file): DriveNoticeData
    {
        return new DriveNoticeData(
            file: $this->file($file),
            text: app(DriveFiles::class)->text($file)['text'] ?? '',
        );
    }
}
