<?php

namespace OiLab\OiLaravelDrive\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * An entity a folder is shared with, read-only.
 */
class DriveShareData extends Data
{
    public function __construct(
        public readonly string $folder_id,
        public readonly string $shareable_type,
        public readonly int|string $shareable_id,
        public readonly string $shareable_name,
        public readonly ?UserSummaryData $shared_by,
        public readonly ?CarbonInterface $created_at,
    ) {}
}
