<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A file was renamed or its title, description or props changed.
 */
class DriveFileUpdated
{
    use SerializesModels;

    public function __construct(
        public readonly File $file,
        public readonly Drive $drive,
    ) {}
}
