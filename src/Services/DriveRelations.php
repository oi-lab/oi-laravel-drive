<?php

namespace OiLab\OiLaravelDrive\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * Relations the drive adds to the folders and files of oi-laravel-attachments,
 * without touching their classes (registered on the base models, so the
 * host's subclasses inherit them):
 *
 * - `driveEntry` (both): the drive, the owner and the date of the item;
 * - `driveFolders` (file): the folders a file is filed in, through the
 *   `attachments` table (collection `files.collection`);
 * - `driveFiles` (folder): the files filed in a folder.
 */
class DriveRelations
{
    public static function register(): void
    {
        $entry = static fn (Model $model): MorphOne => $model->morphOne(OiLaravelDrive::entryModel(), 'entry');

        Folder::resolveRelationUsing('driveEntry', $entry);
        File::resolveRelationUsing('driveEntry', $entry);

        File::resolveRelationUsing('driveFolders', static fn (Model $file): MorphToMany => $file
            ->morphedByMany(OiLaravelDrive::folderModel(), 'attachable', 'attachments', 'file_id', 'attachable_id')
            ->wherePivot('collection', OiLaravelDrive::collection()));

        Folder::resolveRelationUsing('driveFiles', static fn (Model $folder): MorphToMany => $folder
            ->morphToMany(OiLaravelDrive::fileModel(), 'attachable', 'attachments', 'attachable_id', 'file_id')
            ->wherePivot('collection', OiLaravelDrive::collection()));
    }
}
