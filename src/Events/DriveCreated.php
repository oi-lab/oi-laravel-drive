<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A drive was created.
 */
class DriveCreated
{
    use SerializesModels;

    public function __construct(
        public readonly Drive $drive,
    ) {}
}
