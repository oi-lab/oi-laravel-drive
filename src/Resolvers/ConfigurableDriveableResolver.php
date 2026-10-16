<?php

namespace OiLab\OiLaravelDrive\Resolvers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use OiLab\OiLaravelDrive\Contracts\DriveableResolver;

/**
 * Reads the current entity in the `driveable.current` relation or attribute
 * of the user (`currentTeam`, `currentOrganization`) and the members of an
 * entity in its `driveable.members` relation (`users`, `members`). A null
 * `members` relation makes every user a member.
 */
class ConfigurableDriveableResolver implements DriveableResolver
{
    public function current(Authenticatable $user): ?Model
    {
        $attribute = config('oi-laravel-drive.driveable.current');

        if (! $attribute) {
            return null;
        }

        $current = data_get($user, $attribute);

        return $current instanceof Model ? $current : null;
    }

    public function isMember(Authenticatable $user, Model $driveable): bool
    {
        $relation = config('oi-laravel-drive.driveable.members');

        if (! $relation) {
            return true;
        }

        if (! method_exists($driveable, $relation)) {
            return false;
        }

        $members = $driveable->{$relation}();

        if (! $members instanceof Relation) {
            return false;
        }

        return $members->whereKey($user->getAuthIdentifier())->exists();
    }
}
