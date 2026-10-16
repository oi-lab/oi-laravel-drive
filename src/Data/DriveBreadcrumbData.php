<?php

namespace OiLab\OiLaravelDrive\Data;

use Spatie\LaravelData\Data;

/**
 * One step of the path from the root of a drive to a folder.
 */
class DriveBreadcrumbData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}
}
