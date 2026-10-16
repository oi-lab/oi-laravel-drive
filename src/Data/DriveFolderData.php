<?php

namespace OiLab\OiLaravelDrive\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A folder of a drive. Identifiers are UUIDs; `parent_id` is null at the
 * root. `deleted_at` is set while the folder is in the trash.
 * `shares_count` counts the entities the folder is shared with;
 * `has_link` tells whether it has a public link.
 */
class DriveFolderData extends Data
{
    /**
     * @param  array<string, mixed>|null  $props
     */
    public function __construct(
        public readonly string $id,
        public readonly string $drive_id,
        public readonly string $name,
        public readonly ?string $parent_id,
        public readonly ?UserSummaryData $owner,
        public readonly ?CarbonInterface $created_at,
        public readonly ?CarbonInterface $updated_at,
        public readonly ?CarbonInterface $deleted_at = null,
        public readonly ?array $props = null,
        public readonly int $shares_count = 0,
        public readonly bool $has_link = false,
    ) {}
}
