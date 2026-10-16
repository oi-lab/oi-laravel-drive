<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A drive was renamed or its props changed.
 */
class DriveUpdated
{
    use SerializesModels;

    public function __construct(
        public readonly Drive $drive,
    ) {}
}
