<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use OiLab\OiLaravelDrive\Events\DriveEntryOwnerChanged;
use OiLab\OiLaravelDrive\Events\DriveFolderCreated;
use OiLab\OiLaravelDrive\Events\DriveFolderDeleted;
use OiLab\OiLaravelDrive\Events\DriveFolderMoved;
use OiLab\OiLaravelDrive\Events\DriveFolderRestored;
use OiLab\OiLaravelDrive\Events\DriveFolderTrashed;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DrivePresenter;
use OiLab\OiLaravelDrive\Tests\Fixtures\Team;
use OiLab\OiLaravelDrive\Tests\Fixtures\User;

beforeEach(function () {
    ['team' => $this->team, 'user' => $this->user, 'drive' => $this->drive] = driveContext();
    $this->folders = app(DriveFolders::class);
    $this->files = app(DriveFiles::class);
    $this->drives = app(DriveManager::class);
});

it('creates nested folders with their owner, date and path', function () {
    Event::fake([DriveFolderCreated::class]);
    $colleague = User::factory()->create()->joinAndSwitch($this->team);

    $clients = $this->folders->create($this->drive, 'Clients');
    $acme = $this->folders->create($this->drive, 'Acme', $clients, $colleague, Carbon::parse('2020-01-02 03:04:05'));

    expect($acme->parent_id)->toBe($clients->id)
        ->and($acme->driveEntry->user_id)->toBe($colleague->id)
        ->and($acme->driveEntry->created_at->toDateTimeString())->toBe('2020-01-02 03:04:05')
        ->and($clients->driveEntry->user_id)->toBe($this->user->id)
        ->and(collect($this->drives->breadcrumbs($acme))->pluck('name')->all())->toBe(['Clients', 'Acme']);

    $listing = $this->drives->contents($this->drive);
    expect(collect($listing->folders)->pluck('name')->all())->toBe(['Clients'])
        ->and($this->drives->contents($this->drive, $clients)->folders[0]->owner->name)->toBe($colleague->name);

    Event::assertDispatchedTimes(DriveFolderCreated::class, 2);
});

it('allows duplicate folder names by default and refuses them when configured', function () {
    $this->folders->create($this->drive, 'Factures');
    $this->folders->create($this->drive, 'Factures');

    config(['oi-laravel-drive.folders.unique_names' => true]);

    expect(fn () => $this->folders->create($this->drive, 'factures'))->toThrow(DriveException::class, 'existe déjà');

    $other = $this->folders->create($this->drive, 'Autre');
    $this->folders->create($this->drive, 'Factures', $other);

    expect(fn () => $this->folders->update($other, 'Factures'))->toThrow(DriveException::class);
});

it('moves a folder, but never into itself or another drive', function () {
    Event::fake([DriveFolderMoved::class]);
    $a = $this->folders->create($this->drive, 'A');
    $b = $this->folders->create($this->drive, 'B', $a);
    $c = $this->folders->create($this->drive, 'C');

    $this->folders->move($c, $b);
    expect($c->parent_id)->toBe($b->id);
    Event::assertDispatched(DriveFolderMoved::class, fn ($event) => $event->toParentId === $b->id);

    expect(fn () => $this->folders->move($a, $c))->toThrow(DriveException::class, 'lui-même')
        ->and(fn () => $this->folders->move($a, $a))->toThrow(DriveException::class);

    $otherTeam = Team::query()->create(['name' => 'Autre']);
    $stranger = User::factory()->create()->joinAndSwitch($otherTeam);
    $foreign = $this->folders->create($this->drives->driveFor($otherTeam), 'Ailleurs', owner: $stranger);

    expect(fn () => $this->folders->move($a, $foreign))->toThrow(DriveException::class, 'autre drive');

    $this->folders->move($c, null);
    expect($c->refresh()->parent_id)->toBeNull();
});

it('trashes a folder tree and leaves its files at the root, then restores everything', function () {
    Event::fake([DriveFolderTrashed::class, DriveFolderRestored::class]);
    $a = $this->folders->create($this->drive, 'A');
    $b = $this->folders->create($this->drive, 'B', $a);
    $file = $this->files->storeContent($this->drive, 'x', 'x.txt', [$b]);

    $this->folders->delete($a);

    expect($a->refresh()->trashed())->toBeTrue()
        ->and($b->newQuery()->withTrashed()->find($b->id)->trashed())->toBeTrue()
        ->and($file->refresh()->trashed())->toBeFalse()
        ->and(collect($this->drives->contents($this->drive)->files)->pluck('id')->all())->toBe([$file->uuid])
        ->and(collect($this->drives->trash($this->drive)->folders)->pluck('name')->all())->toBe(['A']);

    $this->folders->restore($a);

    expect($b->newQuery()->find($b->id))->not->toBeNull()
        ->and($this->drives->contents($this->drive)->files)->toBe([])
        ->and(collect($this->drives->contents($this->drive, $b)->files)->pluck('id')->all())->toBe([$file->uuid]);

    Event::assertDispatched(DriveFolderTrashed::class, fn ($event) => $event->withFiles === false);
    Event::assertDispatched(DriveFolderRestored::class);
});

it('trashes the files filed only in the folder with it, and only takes the others out', function () {
    $a = $this->folders->create($this->drive, 'A');
    $other = $this->folders->create($this->drive, 'Autre');
    $only = $this->files->storeContent($this->drive, '1', 'seul.txt', [$a]);
    $shared = $this->files->storeContent($this->drive, '2', 'partage.txt', [$a, $other]);

    $this->folders->delete($a, withFiles: true);

    expect($only->refresh()->trashed())->toBeTrue()
        ->and($shared->refresh()->trashed())->toBeFalse()
        ->and($this->drives->trash($this->drive)->files)->toBe([])
        ->and(folderIdsOf($shared))->toBe([$other->uuid]);

    $this->folders->restore($a);

    expect($only->refresh()->trashed())->toBeFalse()
        ->and(folderIdsOf($shared))->toContain($a->uuid, $other->uuid);
});

it('deletes a folder for good, with its exclusive files when asked', function () {
    Event::fake([DriveFolderDeleted::class]);
    $a = $this->folders->create($this->drive, 'A');
    $b = $this->folders->create($this->drive, 'B', $a);
    $other = $this->folders->create($this->drive, 'Autre');
    $only = $this->files->storeContent($this->drive, '1', 'seul.txt', [$b]);
    $shared = $this->files->storeContent($this->drive, '2', 'partage.txt', [$b, $other]);
    $kept = $this->files->storeContent($this->drive, '3', 'garde.txt', [$a]);

    $this->folders->delete($a, withFiles: true, force: true);

    expect($a->newQuery()->withTrashed()->whereKey([$a->id, $b->id])->count())->toBe(0)
        ->and($only->newQuery()->withTrashed()->find($only->id))->toBeNull()
        ->and($kept->newQuery()->withTrashed()->find($kept->id))->toBeNull()
        ->and(folderIdsOf($shared))->toBe([$other->uuid]);

    Event::assertDispatchedTimes(DriveFolderDeleted::class, 2);
});

it('deletes for good when the trash is off, keeping the files at the root', function () {
    config(['oi-laravel-drive.trash.enabled' => false]);
    $a = $this->folders->create($this->drive, 'A');
    $file = $this->files->storeContent($this->drive, '1', 'seul.txt', [$a]);

    $this->folders->delete($a);

    expect($a->newQuery()->withTrashed()->find($a->id))->toBeNull()
        ->and(collect($this->drives->contents($this->drive)->files)->pluck('id')->all())->toBe([$file->uuid]);
});

it('restores a folder at the root when its parent is gone, renaming it when the name is taken', function () {
    config(['oi-laravel-drive.folders.unique_names' => true]);
    $a = $this->folders->create($this->drive, 'A');
    $b = $this->folders->create($this->drive, 'B', $a);

    $this->folders->delete($b);
    $this->folders->delete($a, force: true);
    $this->folders->create($this->drive, 'B');

    $restored = $this->folders->restore($b->refresh());

    expect($restored->parent_id)->toBeNull()
        ->and($restored->name)->toBe('B (2)');
});

it('changes the owner of a folder and tells it', function () {
    Event::fake([DriveEntryOwnerChanged::class]);
    $colleague = User::factory()->create()->joinAndSwitch($this->team);
    $folder = $this->folders->create($this->drive, 'A');

    $this->drives->setOwner($folder, $colleague);

    expect($folder->driveEntry->refresh()->user_id)->toBe($colleague->id);
    Event::assertDispatched(DriveEntryOwnerChanged::class, fn ($event) => $event->toUserId === $colleague->id);
});

it('refuses to work on a trashed folder', function () {
    $folder = $this->folders->create($this->drive, 'A');
    $this->folders->delete($folder);

    expect(fn () => $this->folders->update($folder, 'B'))->toThrow(DriveException::class, 'corbeille')
        ->and(fn () => $this->files->storeContent($this->drive, 'x', 'x.txt', [$folder]))->toThrow(DriveException::class)
        ->and(fn () => $this->folders->restore($this->folders->create($this->drive, 'C')))->toThrow(DriveException::class);
});

/**
 * @return list<string>
 */
function folderIdsOf($file): array
{
    return app(DrivePresenter::class)->file($file->fresh())->folder_ids;
}
