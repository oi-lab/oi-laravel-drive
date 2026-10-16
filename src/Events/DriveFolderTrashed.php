<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A folder and its subfolders went to the trash, with its own files when `withFiles`.
 */
class DriveFolderTrashed
{
    use SerializesModels;

    public function __construct(
        public readonly Folder $folder,
        public readonly Drive $drive,
        public readonly bool $withFiles,
    ) {}
}
