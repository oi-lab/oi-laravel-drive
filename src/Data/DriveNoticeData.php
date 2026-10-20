<?php

namespace OiLab\OiLaravelDrive\Data;

use Spatie\LaravelData\Data;

/**
 * The notice of a folder: a markdown file explaining what the folder is for
 * and what to put in it, shown when the folder is empty.
 */
class DriveNoticeData extends Data
{
    public function __construct(
        public readonly DriveFileData $file,
        public readonly string $text,
    ) {}
}
