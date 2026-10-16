<?php

use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveManager;

beforeEach(function () {
    ['drive' => $this->drive] = driveContext();
    $this->folders = app(DriveFolders::class);
    $this->files = app(DriveFiles::class);
    $this->drives = app(DriveManager::class);
});

it('lists only what was deleted directly', function () {
    $a = $this->folders->create($this->drive, 'A');
    $this->folders->create($this->drive, 'B', $a);
    $this->files->storeContent($this->drive, '1', 'dans-a.txt', [$a]);
    $alone = $this->files->storeContent($this->drive, '2', 'seul.txt');

    $this->folders->delete($a, withFiles: true);
    $this->files->delete($alone);

    $trash = $this->drives->trash($this->drive);

    expect(collect($trash->folders)->pluck('name')->all())->toBe(['A'])
        ->and(collect($trash->files)->pluck('name')->all())->toBe(['seul.txt']);
});

it('empties the trash of a drive', function () {
    $a = $this->folders->create($this->drive, 'A');
    $this->files->storeContent($this->drive, '1', 'dans-a.txt', [$a]);
    $alone = $this->files->storeContent($this->drive, '2', 'seul.txt');
    $this->folders->delete($a, withFiles: true);
    $this->files->delete($alone);

    expect($this->drives->emptyTrash($this->drive))->toBe(2)
        ->and($this->drives->fileQuery($this->drive)->withTrashed()->count())->toBe(0)
        ->and($this->drives->folderQuery($this->drive)->withTrashed()->count())->toBe(0);
});

it('purges what has been in the trash for too long', function () {
    $old = $this->files->storeContent($this->drive, '1', 'ancien.txt');
    $recent = $this->files->storeContent($this->drive, '2', 'recent.txt');

    $this->travel(-40)->days();
    $this->files->delete($old);
    $this->travelBack();
    $this->files->delete($recent);

    expect($this->drives->purgeTrash())->toBe(0);

    config(['oi-laravel-drive.trash.purge_after_days' => 30]);
    $this->artisan('drive:purge-trash')->assertSuccessful();

    expect($old->newQuery()->withTrashed()->find($old->id))->toBeNull()
        ->and($recent->newQuery()->withTrashed()->find($recent->id))->not->toBeNull();
});
