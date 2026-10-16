<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelDrive\Data\DriveFileData;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A file was deleted for good, its content removed from the disk.
 */
class DriveFileDeleted
{
    use SerializesModels;

    public function __construct(
        public readonly DriveFileData $file,
        public readonly Drive $drive,
    ) {}
}
