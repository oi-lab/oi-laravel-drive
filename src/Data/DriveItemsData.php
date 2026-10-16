<?php

namespace OiLab\OiLaravelDrive\Data;

use Spatie\LaravelData\Data;

/**
 * Folders and files out of their tree: search results, trash.
 */
class DriveItemsData extends Data
{
    /**
     * @param  list<DriveFolderData>  $folders
     * @param  list<DriveFileData>  $files
     */
    public function __construct(
        public readonly DriveData $drive,
        public readonly array $folders,
        public readonly array $files,
    ) {}
}
