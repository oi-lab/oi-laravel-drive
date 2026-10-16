<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelAttachments\Models\Folder;

/**
 * The public link of a folder was closed.
 */
class DriveFolderLinkRevoked
{
    use SerializesModels;

    public function __construct(
        public readonly Folder $folder,
    ) {}
}
