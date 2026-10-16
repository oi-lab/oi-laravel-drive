<?php

namespace OiLab\OiLaravelDrive\Events;

use Illuminate\Queue\SerializesModels;
use OiLab\OiLaravelDrive\Models\DriveFolderLink;

/**
 * The public link of a folder was opened, regenerated, or got a new password
 * or expiry date.
 */
class DriveFolderLinkSaved
{
    use SerializesModels;

    public function __construct(
        public readonly DriveFolderLink $link,
    ) {}
}
