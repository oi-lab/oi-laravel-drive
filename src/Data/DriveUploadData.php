<?php

namespace OiLab\OiLaravelDrive\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * An upload by link. `upload_url` (PUT of the raw body) and `page_url`
 * (drag-and-drop page) are only given while the upload is open.
 */
class DriveUploadData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly string $filename,
        public readonly CarbonInterface $expires_at,
        public readonly int $max_bytes,
        public readonly ?string $upload_url = null,
        public readonly ?string $page_url = null,
        public readonly ?DriveFileData $file = null,
        public readonly ?string $error = null,
    ) {}
}
