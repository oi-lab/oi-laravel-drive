<?php

use Illuminate\Support\Facades\Event;
use OiLab\OiLaravelDrive\Events\DriveCreated;
use OiLab\OiLaravelDrive\Events\DriveDeleted;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Tests\Fixtures\Team;
use OiLab\OiLaravelDrive\Tests\Fixtures\User;

it('creates the default drive of an entity once, owned by the user', function () {
    Event::fake([DriveCreated::class]);
    ['team' => $team, 'user' => $user, 'drive' => $drive] = driveContext();

    expect(app(DriveManager::class)->driveFor($team)->is($drive))->toBeTrue()
        ->and($drive->name)->toBe('Documents')
        ->and($drive->user_id)->toBe($user->id)
        ->and($drive->driveable->is($team))->toBeTrue();

    Event::assertDispatchedTimes(DriveCreated::class, 1);
});

it('refuses a second drive unless multiple drives are on', function () {
    ['team' => $team] = driveContext();
    $drives = app(DriveManager::class);

    expect(fn () => $drives->createDrive($team, 'Archives'))->toThrow(DriveException::class);

    config(['oi-laravel-drive.drives.multiple' => true]);
    $drives->createDrive($team, 'Archives');

    expect($drives->drivesOf($team)->pluck('name')->all())->toBe(['Archives', 'Documents']);
});

it('refuses an owner who is not a member of the entity', function () {
    ['drive' => $drive] = driveContext();
    $stranger = User::factory()->create()->joinAndSwitch(Team::query()->create(['name' => 'Autre']));

    expect(fn () => app(DriveFolders::class)->create($drive, 'Contrats', owner: $stranger))
        ->toThrow(DriveException::class, 'membre');
});

it('deletes a drive with its folders and files', function () {
    Event::fake([DriveDeleted::class]);
    ['drive' => $drive] = driveContext();
    $folder = app(DriveFolders::class)->create($drive, 'Contrats');
    $file = app(DriveFiles::class)->storeContent($drive, 'bonjour', 'note.txt', [$folder]);

    app(DriveManager::class)->deleteDrive($drive);

    expect($drive->newQuery()->withTrashed()->count())->toBe(0)
        ->and($folder->newQuery()->withTrashed()->count())->toBe(0)
        ->and($file->newQuery()->withTrashed()->count())->toBe(0);
    Event::assertDispatched(DriveDeleted::class);
});
