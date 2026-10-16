<?php

namespace OiLab\OiLaravelDrive\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A file with a temporary download link and, for a textual file, its text
 * (cut after `files.text_preview_bytes`).
 */
class DriveFileContentData extends Data
{
    public function __construct(
        public readonly DriveFileData $file,
        public readonly string $download_url,
        public readonly CarbonInterface $download_expires_at,
        public readonly ?string $text = null,
        public readonly bool $text_truncated = false,
    ) {}
}
