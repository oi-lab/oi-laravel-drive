<?php

use Illuminate\Support\Facades\Gate;
use OiLab\OiLaravelDrive\Contracts\DriveableResolver;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Tests\Fixtures\Team;
use OiLab\OiLaravelDrive\Tests\Fixtures\User;

it('resolves the current entity and the members through the configured relations', function () {
    ['team' => $team, 'user' => $user] = driveContext();
    $resolver = app(DriveableResolver::class);
    $outsider = User::factory()->create();

    expect($resolver->current($user)->is($team))->toBeTrue()
        ->and($resolver->current($outsider))->toBeNull()
        ->and($resolver->isMember($user, $team))->toBeTrue()
        ->and($resolver->isMember($outsider, $team))->toBeFalse();

    config(['oi-laravel-drive.driveable.members' => null]);
    expect($resolver->isMember($outsider, $team))->toBeTrue();

    config(['oi-laravel-drive.driveable.members' => 'missingRelation']);
    expect($resolver->isMember($user, $team))->toBeFalse();
});

it('limits a member to the drive of their current entity unless current_only is off', function () {
    ['team' => $team, 'user' => $user, 'drive' => $drive] = driveContext();
    $other = Team::query()->create(['name' => 'Autre']);
    $user->joinAndSwitch($other);

    expect(Gate::forUser($user)->allows('view', $drive))->toBeFalse();

    config(['oi-laravel-drive.driveable.current_only' => false]);
    expect(Gate::forUser($user)->allows('update', $drive))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('view', $drive))->toBeFalse();
});

it('only lets a drive be created or deleted when multiple drives are on', function () {
    ['user' => $user, 'drive' => $drive] = driveContext();

    expect(Gate::forUser($user)->allows('create', $drive::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $drive))->toBeFalse();

    config(['oi-laravel-drive.drives.multiple' => true]);

    expect(Gate::forUser($user)->allows('create', $drive::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $drive))->toBeTrue();
});

it('refuses to find a drive for a user without a current entity', function () {
    $user = User::factory()->create();

    expect(fn () => app(DriveManager::class)->resolveDrive($user))->toThrow(DriveException::class);
});
