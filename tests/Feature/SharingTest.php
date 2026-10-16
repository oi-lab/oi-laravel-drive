<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use OiLab\OiLaravelDrive\Events\DriveFolderShared;
use OiLab\OiLaravelDrive\Events\DriveFolderUnshared;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DrivePresenter;
use OiLab\OiLaravelDrive\Services\DriveShares;
use OiLab\OiLaravelDrive\Tests\Fixtures\Team;
use OiLab\OiLaravelDrive\Tests\Fixtures\User;

beforeEach(function () {
    ['team' => $this->team, 'user' => $this->user, 'drive' => $this->drive] = driveContext();
    $this->folders = app(DriveFolders::class);
    $this->files = app(DriveFiles::class);
    $this->shares = app(DriveShares::class);

    $this->partner = Team::query()->create(['name' => 'Partenaire']);
    $this->guest = User::factory()->create()->joinAndSwitch($this->partner);
});

it('lets the members of a team read a folder shared with it, its subfolders and their files', function () {
    $clients = $this->folders->create($this->drive, 'Clients');
    $acme = $this->folders->create($this->drive, 'Acme', parent: $clients);
    $inside = $this->files->storeContent($this->drive, 'contrat', 'contrat.txt', [$acme]);
    $elsewhere = $this->folders->create($this->drive, 'Interne');
    $outside = $this->files->storeContent($this->drive, 'secret', 'secret.txt', [$elsewhere]);

    $this->shares->share($clients, $this->partner);

    expect($this->shares->reaches($this->guest, $clients))->toBeTrue()
        ->and($this->shares->reaches($this->guest, $acme))->toBeTrue()
        ->and($this->shares->reaches($this->guest, $inside))->toBeTrue()
        ->and($this->shares->shareRoot($this->guest, $inside)->is($clients))->toBeTrue()
        ->and($this->shares->reaches($this->guest, $elsewhere))->toBeFalse()
        ->and($this->shares->reaches($this->guest, $outside))->toBeFalse()
        ->and($this->shares->reaches(User::factory()->create(), $clients))->toBeFalse();
});

it('only opens a shared folder from the shared entity while current_only is on', function () {
    $clients = $this->folders->create($this->drive, 'Clients');
    $this->shares->share($clients, $this->partner);
    $this->guest->joinAndSwitch(Team::query()->create(['name' => 'Ailleurs']));

    expect($this->shares->reaches($this->guest, $clients))->toBeFalse()
        ->and($this->shares->sharedWith($this->guest))->toBe([]);

    config(['oi-laravel-drive.driveable.current_only' => false]);

    expect($this->shares->reaches($this->guest, $clients))->toBeTrue();
});

it('lists the topmost folders shared with the current entity, with their sharing counts', function () {
    $clients = $this->folders->create($this->drive, 'Clients');
    $acme = $this->folders->create($this->drive, 'Acme', parent: $clients);
    $archives = $this->folders->create($this->drive, 'Archives');

    $this->shares->share($clients, $this->partner);
    $this->shares->share($acme, $this->partner);
    $this->shares->share($archives, $this->partner);

    $shared = $this->shares->sharedWith($this->guest);

    expect(collect($shared)->pluck('name')->all())->toBe(['Archives', 'Clients'])
        ->and($shared[1]->shares_count)->toBe(1)
        ->and(app(DrivePresenter::class)->folder($acme)->shares_count)->toBe(1);
});

it('shares nothing while the folder is in the trash', function () {
    $clients = $this->folders->create($this->drive, 'Clients');
    $this->shares->share($clients, $this->partner);
    $this->folders->delete($clients);

    expect($this->shares->sharedWith($this->guest))->toBe([])
        ->and($this->shares->reaches($this->guest, $clients->refresh()))->toBeFalse();
});

it('cuts the breadcrumbs at the shared folder and refuses a folder outside it', function () {
    $clients = $this->folders->create($this->drive, 'Clients');
    $acme = $this->folders->create($this->drive, 'Acme', parent: $clients);
    $devis = $this->folders->create($this->drive, 'Devis', parent: $acme);

    $listing = $this->shares->contents($acme, $devis);

    expect(collect($listing->breadcrumbs)->pluck('name')->all())->toBe(['Acme', 'Devis'])
        ->and(fn () => $this->shares->contents($acme, $clients))->toThrow(ModelNotFoundException::class);
});

it('syncs the shares and fires an event for each change', function () {
    Event::fake([DriveFolderShared::class, DriveFolderUnshared::class]);
    $clients = $this->folders->create($this->drive, 'Clients');
    $other = Team::query()->create(['name' => 'Autre']);

    $this->shares->share($clients, $this->partner);
    $this->shares->share($clients, $this->partner);
    $this->shares->sync($clients, [$other]);

    expect(collect($this->shares->sharesOf($clients))->pluck('shareable_name')->all())->toBe(['Autre']);
    Event::assertDispatchedTimes(DriveFolderShared::class, 2);
    Event::assertDispatchedTimes(DriveFolderUnshared::class, 1);
});

it('refuses to share a folder with the entity owning its drive or with another kind of entity', function () {
    $clients = $this->folders->create($this->drive, 'Clients');

    expect(fn () => $this->shares->share($clients, $this->team))->toThrow(DriveException::class)
        ->and(fn () => $this->shares->share($clients, $this->guest))->toThrow(DriveException::class);
});

it('finds an entity to share with by key or by name', function () {
    $clients = $this->folders->create($this->drive, 'Clients');

    expect($this->shares->findShareable($clients, $this->partner->id)->is($this->partner))->toBeTrue()
        ->and($this->shares->findShareable($clients, 'Partenaire')->is($this->partner))->toBeTrue();
});
