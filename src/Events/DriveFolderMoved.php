<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A folder moved to another parent (null: the root).
 */
class DriveFolderMoved
{
    use SerializesModels;

    public function __construct(
        public readonly Folder $folder,
        public readonly Drive $drive,
        public readonly ?int $fromParentId,
        public readonly ?int $toParentId,
    ) {}
}
