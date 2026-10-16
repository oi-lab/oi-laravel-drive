<?php

namespace OiLab\OiLaravelDrive\Data;

use Spatie\LaravelData\Data;

/**
 * The content of a folder, or of the root when `folder` is null.
 */
class DriveListingData extends Data
{
    /**
     * @param  list<DriveBreadcrumbData>  $breadcrumbs
     * @param  list<DriveFolderData>  $folders
     * @param  list<DriveFileData>  $files
     */
    public function __construct(
        public readonly DriveData $drive,
        public readonly ?DriveFolderData $folder,
        public readonly array $breadcrumbs,
        public readonly array $folders,
        public readonly array $files,
    ) {}
}
