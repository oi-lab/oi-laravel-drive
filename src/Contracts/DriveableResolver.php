<?php

namespace OiLab\OiLaravelDrive\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Tells which entity (Team, Organization…) a user works in, and whether a
 * user belongs to an entity. Bound from `oi-laravel-drive.driveable.resolver`.
 */
interface DriveableResolver
{
    /**
     * The entity the user currently works in, whose drives the API and the
     * MCP tools reach by default.
     */
    public function current(Authenticatable $user): ?Model;

    /**
     * Whether the user belongs to the entity: may reach its drives, may own
     * one of its folders or files.
     */
    public function isMember(Authenticatable $user, Model $driveable): bool;
}
