<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * The owner of a folder or a file changed.
 */
class DriveEntryOwnerChanged
{
    use SerializesModels;

    public function __construct(
        public readonly Model $entry,
        public readonly Drive $drive,
        public readonly int|string|null $fromUserId,
        public readonly int|string|null $toUserId,
    ) {}
}
