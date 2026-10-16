<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use OiLab\OiLaravelDrive\Events\DriveFolderLinkRevoked;
use OiLab\OiLaravelDrive\Events\DriveFolderLinkSaved;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveLinks;
use OiLab\OiLaravelDrive\Services\DrivePresenter;

beforeEach(function () {
    ['team' => $this->team, 'user' => $this->user, 'drive' => $this->drive] = driveContext();
    $this->folders = app(DriveFolders::class);
    $this->links = app(DriveLinks::class);
    $this->folder = $this->folders->create($this->drive, 'Presse');
});

it('opens one link per folder and finds it by its token', function () {
    $link = $this->links->open($this->folder);

    expect($this->links->open($this->folder)->is($link))->toBeTrue()
        ->and(strlen($link->token))->toBe(40)
        ->and($this->links->find($link->token)->is($link))->toBeTrue()
        ->and($this->links->find('inconnu'))->toBeNull()
        ->and(app(DrivePresenter::class)->folder($this->folder)->has_link)->toBeTrue();
});

it('protects a link with a hashed password that locks visitors out again when changed', function () {
    $link = $this->links->open($this->folder);
    $this->links->setPassword($link, 'sésame-1');
    $key = $link->unlockKey();

    expect($link->password)->not->toBe('sésame-1')
        ->and($this->links->checkPassword($link, 'sésame-1'))->toBeTrue()
        ->and($this->links->checkPassword($link, 'mauvais'))->toBeFalse()
        ->and($this->links->present($link)->has_password)->toBeTrue()
        ->and($this->links->present($link)->toArray())->not->toHaveKey('password');

    $this->links->setPassword($link, 'sésame-2');
    expect($link->unlockKey())->not->toBe($key);

    $this->links->setPassword($link, null);
    expect($this->links->checkPassword($link, ''))->toBeTrue();

    expect(fn () => $this->links->setPassword($link, 'abc'))->toThrow(DriveException::class);
});

it('expires a link and gives a new address on regeneration', function () {
    $link = $this->links->open($this->folder);
    $token = $link->token;

    $this->links->setExpiry($link, now()->subMinute());
    expect($link->isExpired())->toBeTrue();

    $this->links->regenerate($link);
    expect($this->links->find($token))->toBeNull()
        ->and($this->links->find($link->token))->not->toBeNull();
});

it('counts the visits', function () {
    $link = $this->links->open($this->folder);

    $this->links->recordView($link);
    $this->links->recordView($link);

    expect($link->refresh()->views_count)->toBe(2)
        ->and($link->last_viewed_at)->not->toBeNull();
});

it('gives the URL of the host route', function () {
    Route::get('partage/{token}', fn () => 'ok')->name('drive-links.show');
    Route::getRoutes()->refreshNameLookups();
    config(['oi-laravel-drive.links.route' => 'drive-links.show']);
    $link = $this->links->open($this->folder);

    expect($this->links->present($link)->url)->toEndWith('/partage/'.$link->token);
});

it('stops answering once the folder is trashed, and fires events', function () {
    Event::fake([DriveFolderLinkSaved::class, DriveFolderLinkRevoked::class]);
    $link = $this->links->open($this->folder);

    $this->folders->delete($this->folder);
    expect($this->links->find($link->token))->toBeNull();

    $this->folders->restore($this->folder->refresh());
    expect($this->links->revoke($this->folder))->toBeTrue()
        ->and($this->links->find($link->token))->toBeNull();

    Event::assertDispatched(DriveFolderLinkSaved::class);
    Event::assertDispatched(DriveFolderLinkRevoked::class);
});
