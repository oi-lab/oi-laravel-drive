<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelDrive\Data\DriveFolderData;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A folder was deleted for good.
 */
class DriveFolderDeleted
{
    use SerializesModels;

    public function __construct(
        public readonly DriveFolderData $folder,
        public readonly Drive $drive,
    ) {}
}
