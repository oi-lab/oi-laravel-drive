<?php

namespace OiLab\OiLaravelDrive\Data;

use Carbon\CarbonInterface;
use OiLab\OiLaravelDrive\Models\Drive;
use Spatie\LaravelData\Data;

/**
 * A drive. `id` is its public identifier (UUID).
 */
class DriveData extends Data
{
    /**
     * @param  array<string, mixed>|null  $props
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $driveable_type,
        public readonly int|string $driveable_id,
        public readonly ?UserSummaryData $owner,
        public readonly ?CarbonInterface $created_at,
        public readonly ?CarbonInterface $updated_at,
        public readonly ?array $props = null,
    ) {}

    public static function fromModel(Drive $drive): self
    {
        return new self(
            id: $drive->uuid,
            name: $drive->name,
            driveable_type: $drive->driveable_type,
            driveable_id: $drive->driveable_id,
            owner: $drive->user ? UserSummaryData::fromUser($drive->user) : null,
            created_at: $drive->created_at,
            updated_at: $drive->updated_at,
            props: $drive->props,
        );
    }
}
