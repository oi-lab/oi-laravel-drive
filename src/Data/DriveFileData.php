<?php

namespace OiLab\OiLaravelDrive\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A file of a drive. `folder_ids` lists the folders it is filed in (empty:
 * the file is at the root). `deleted_at` is set while it is in the trash.
 * `key` is the stable key given by the host application, `role` is
 * `notice` for the notice of a folder; `locked`: it cannot be renamed or
 * deleted; `is_link`: it shows the stored object of `source_type` /
 * `source_id`, which deleting the link never touches.
 */
class DriveFileData extends Data
{
    /**
     * @param  list<string>  $folder_ids
     * @param  array<string, mixed>|null  $props
     */
    public function __construct(
        public readonly string $id,
        public readonly string $drive_id,
        public readonly string $name,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly string $mimetype,
        public readonly int $size,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?string $md5,
        public readonly array $folder_ids,
        public readonly ?UserSummaryData $owner,
        public readonly ?CarbonInterface $created_at,
        public readonly ?CarbonInterface $updated_at,
        public readonly ?CarbonInterface $deleted_at = null,
        public readonly ?array $props = null,
        public readonly ?string $key = null,
        public readonly ?string $role = null,
        public readonly bool $locked = false,
        public readonly bool $is_link = false,
        public readonly ?string $source_type = null,
        public readonly ?string $source_id = null,
    ) {}
}
