<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelDrive\Data\DriveData;

/**
 * A drive was deleted for good, with all its folders and files.
 */
class DriveDeleted
{
    use SerializesModels;

    public function __construct(
        public readonly DriveData $drive,
    ) {}
}
