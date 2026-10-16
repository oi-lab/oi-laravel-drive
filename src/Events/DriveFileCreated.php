<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A file was added to a drive.
 */
class DriveFileCreated
{
    use SerializesModels;

    public function __construct(
        public readonly File $file,
        public readonly Drive $drive,
    ) {}
}
