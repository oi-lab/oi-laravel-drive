<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A folder was created in a drive.
 */
class DriveFolderCreated
{
    use SerializesModels;

    public function __construct(
        public readonly Folder $folder,
        public readonly Drive $drive,
    ) {}
}
