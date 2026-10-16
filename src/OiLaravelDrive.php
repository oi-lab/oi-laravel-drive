<?php

namespace OiLab\OiLaravelDrive;

use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelAttachments\OiLaravelAttachments;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\Models\DriveEntry;
use OiLab\OiLaravelDrive\Models\DriveUpload;

/**
 * Static resolver of the configurable classes and settings of the package.
 */
class OiLaravelDrive
{
    /**
     * @return class-string<Drive>
     */
    public static function driveModel(): string
    {
        return config('oi-laravel-drive.models.drive', Drive::class);
    }

    /**
     * @return class-string<DriveEntry>
     */
    public static function entryModel(): string
    {
        return config('oi-laravel-drive.models.entry', DriveEntry::class);
    }

    /**
     * @return class-string<DriveUpload>
     */
    public static function uploadModel(): string
    {
        return config('oi-laravel-drive.models.upload', DriveUpload::class);
    }

    /**
     * @return class-string<Folder>
     */
    public static function folderModel(): string
    {
        return OiLaravelAttachments::folderModel();
    }

    /**
     * @return class-string<File>
     */
    public static function fileModel(): string
    {
        return OiLaravelAttachments::fileModel();
    }

    public static function userModel(): string
    {
        return config('oi-laravel-drive.user_model', 'App\\Models\\User');
    }

    public static function disk(): string
    {
        return (string) config('oi-laravel-drive.files.disk', 'local');
    }

    public static function directory(): string
    {
        return trim((string) config('oi-laravel-drive.files.directory', 'drive'), '/');
    }

    /**
     * The attachments collection linking a file to its folders.
     */
    public static function collection(): string
    {
        return (string) config('oi-laravel-drive.files.collection', 'drive');
    }

    public static function allowsMultipleDrives(): bool
    {
        return (bool) config('oi-laravel-drive.drives.multiple', false);
    }

    public static function usesTrash(): bool
    {
        return (bool) config('oi-laravel-drive.trash.enabled', true);
    }

    public static function requiresUniqueFolderNames(): bool
    {
        return (bool) config('oi-laravel-drive.folders.unique_names', false);
    }
}
