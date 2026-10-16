<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\Folder;

/**
 * A folder no longer shared with an entity.
 */
class DriveFolderUnshared
{
    use SerializesModels;

    public function __construct(
        public readonly Folder $folder,
        public readonly Model $shareable,
    ) {}
}
