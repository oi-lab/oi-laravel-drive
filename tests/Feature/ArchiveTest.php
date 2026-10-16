<?php

use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Services\DriveArchives;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;

beforeEach(function () {
    ['drive' => $this->drive] = driveContext();
    $this->folders = app(DriveFolders::class);
    $this->files = app(DriveFiles::class);
});

it('packs a folder tree into a ZIP, leaving out what is trashed or outside', function () {
    $root = $this->folders->create($this->drive, 'Projet');
    $plans = $this->folders->create($this->drive, 'Plans', parent: $root);
    $vide = $this->folders->create($this->drive, 'Vide', parent: $root);
    $ailleurs = $this->folders->create($this->drive, 'Ailleurs');

    $this->files->storeContent($this->drive, 'a', 'note.txt', [$root]);
    $this->files->storeContent($this->drive, 'b', 'note.txt', [$root]);
    $this->files->storeContent($this->drive, 'c', 'plan.txt', [$plans, $ailleurs]);
    $trashed = $this->files->storeContent($this->drive, 'd', 'jete.txt', [$plans]);
    $this->files->delete($trashed);
    $this->files->storeContent($this->drive, 'e', 'hors.txt', [$ailleurs]);

    $path = app(DriveArchives::class)->build($root);
    $zip = new ZipArchive;
    $zip->open($path);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i) => $zip->getNameIndex($i))->sort()->values()->all();
    $plan = $zip->getFromName('Plans/plan.txt');
    $zip->close();
    unlink($path);

    expect($names)->toBe(['Plans/', 'Plans/plan.txt', 'Vide/', 'note (2).txt', 'note.txt'])
        ->and($plan)->toBe('c')
        ->and(app(DriveArchives::class)->filename($root))->toBe('Projet.zip');
});

it('refuses a folder larger than the archive limit', function () {
    $root = $this->folders->create($this->drive, 'Projet');
    $this->files->storeContent($this->drive, str_repeat('x', 100), 'gros.txt', [$root]);
    config(['oi-laravel-drive.links.archive_max_bytes' => 10]);

    expect(fn () => app(DriveArchives::class)->build($root))->toThrow(DriveException::class);
});
