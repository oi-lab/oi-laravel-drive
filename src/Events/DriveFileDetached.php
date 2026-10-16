<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A file was taken out of a folder.
 */
class DriveFileDetached
{
    use SerializesModels;

    public function __construct(
        public readonly File $file,
        public readonly Folder $folder,
        public readonly Drive $drive,
    ) {}
}
