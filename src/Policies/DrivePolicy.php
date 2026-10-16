<?php

namespace OiLab\OiLaravelDrive\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use OiLab\OiLaravelDrive\Contracts\DriveableResolver;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * Default access rules of a drive: a member of its entity may read and write
 * it, from that entity only when `driveable.current_only` is on. Folders and
 * files are authorized through their drive.
 */
class DrivePolicy
{
    public function __construct(protected DriveableResolver $resolver) {}

    public function create(Authenticatable $user): bool
    {
        return OiLaravelDrive::allowsMultipleDrives() && $this->resolver->current($user) !== null;
    }

    public function view(Authenticatable $user, Drive $drive): bool
    {
        return $this->reaches($user, $drive);
    }

    public function update(Authenticatable $user, Drive $drive): bool
    {
        return $this->reaches($user, $drive);
    }

    public function delete(Authenticatable $user, Drive $drive): bool
    {
        return OiLaravelDrive::allowsMultipleDrives() && $this->reaches($user, $drive);
    }

    protected function reaches(Authenticatable $user, Drive $drive): bool
    {
        $driveable = $drive->driveable;

        if ($driveable === null) {
            return false;
        }

        if (config('oi-laravel-drive.driveable.current_only', true)
            && ! $this->resolver->current($user)?->is($driveable)) {
            return false;
        }

        return $this->resolver->isMember($user, $driveable);
    }
}
