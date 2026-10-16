<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Models\Drive;

/**
 * A folder came back from the trash, with what was trashed with it.
 */
class DriveFolderRestored
{
    use SerializesModels;

    public function __construct(
        public readonly Folder $folder,
        public readonly Drive $drive,
    ) {}
}
