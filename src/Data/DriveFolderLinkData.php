<?php

namespace OiLab\OiLaravelDrive\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * The public link of a folder. `url` is null when no host route serves the
 * links (`links.route`). The password itself is never given back.
 */
class DriveFolderLinkData extends Data
{
    public function __construct(
        public readonly string $folder_id,
        public readonly string $token,
        public readonly ?string $url,
        public readonly bool $has_password,
        public readonly ?CarbonInterface $expires_at,
        public readonly bool $is_expired,
        public readonly int $views_count,
        public readonly ?CarbonInterface $last_viewed_at,
        public readonly ?UserSummaryData $created_by,
        public readonly ?CarbonInterface $created_at,
    ) {}
}
