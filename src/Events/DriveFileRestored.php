<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A file came back from the trash.
 */
class DriveFileRestored
{
    use SerializesModels;

    public function __construct(
        public readonly File $file,
        public readonly Drive $drive,
    ) {}
}
