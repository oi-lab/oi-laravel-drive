<?php

namespace OiLab\OiLaravelDrive\Data;

use Spatie\LaravelData\Data;

/**
 * Who a folder is open to: the entities it is shared with and its public
 * link (null: none).
 */
class DriveFolderSharingData extends Data
{
    /**
     * @param  list<DriveShareData>  $shares
     */
    public function __construct(
        public readonly DriveFolderData $folder,
        public readonly array $shares,
        public readonly ?DriveFolderLinkData $link,
    ) {}
}
