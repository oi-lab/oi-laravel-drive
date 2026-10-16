<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use OiLab\OiLaravelDrive\Events\DriveFileAttached;
use OiLab\OiLaravelDrive\Events\DriveFileCreated;
use OiLab\OiLaravelDrive\Events\DriveFileDeleted;
use OiLab\OiLaravelDrive\Events\DriveFileDetached;
use OiLab\OiLaravelDrive\Events\DriveFileRestored;
use OiLab\OiLaravelDrive\Events\DriveFileTrashed;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DrivePresenter;

beforeEach(function () {
    ['team' => $this->team, 'user' => $this->user, 'drive' => $this->drive] = driveContext();
    $this->folders = app(DriveFolders::class);
    $this->files = app(DriveFiles::class);
    $this->drives = app(DriveManager::class);
});

it('stores an upload at the root with its owner, date, type read in the content and md5', function () {
    Event::fake([DriveFileCreated::class]);

    $file = $this->files->store(
        $this->drive,
        UploadedFile::fake()->createWithContent('rapport.pdf', 'Bonjour le monde'),
        createdAt: Carbon::parse('2021-05-06 07:08:09'),
        title: 'Rapport',
    );

    $data = app(DrivePresenter::class)->file($file);

    expect($data->name)->toBe('rapport.pdf')
        ->and($data->mimetype)->toBe('text/plain')
        ->and($data->size)->toBe(16)
        ->and($data->md5)->toBe(md5('Bonjour le monde'))
        ->and($data->title)->toBe('Rapport')
        ->and($data->folder_ids)->toBe([])
        ->and($data->owner->id)->toBe($this->user->id)
        ->and($data->created_at->toDateTimeString())->toBe('2021-05-06 07:08:09')
        ->and($data->drive_id)->toBe($this->drive->uuid);

    Storage::disk('drive')->assertExists($file->filename_disk);
    expect($file->filename_disk)->toStartWith('drive/'.$this->drive->uuid.'/');
    Event::assertDispatched(DriveFileCreated::class);
});

it('files one file in several folders, and puts it back at the root out of every folder', function () {
    Event::fake([DriveFileAttached::class, DriveFileDetached::class]);
    $a = $this->folders->create($this->drive, 'A');
    $b = $this->folders->create($this->drive, 'B');
    $file = $this->files->storeContent($this->drive, 'x', 'x.txt', [$a]);

    $this->files->attach($file, [$a, $b]);
    expect(app(DrivePresenter::class)->file($file->fresh())->folder_ids)->toEqualCanonicalizing([$a->uuid, $b->uuid])
        ->and(collect($this->drives->contents($this->drive, $b)->files)->pluck('id')->all())->toBe([$file->uuid])
        ->and($this->drives->contents($this->drive)->files)->toBe([]);

    $this->files->detach($file, [$a, $b]);
    expect(collect($this->drives->contents($this->drive)->files)->pluck('id')->all())->toBe([$file->uuid]);

    $this->files->sync($file, [$b]);
    expect(app(DrivePresenter::class)->file($file->fresh())->folder_ids)->toBe([$b->uuid]);

    Event::assertDispatchedTimes(DriveFileAttached::class, 3);
    Event::assertDispatchedTimes(DriveFileDetached::class, 2);
});

it('trashes a file from every folder at once, restores it, and deletes it for good with its content', function () {
    Event::fake([DriveFileTrashed::class, DriveFileRestored::class, DriveFileDeleted::class]);
    $a = $this->folders->create($this->drive, 'A');
    $file = $this->files->storeContent($this->drive, 'x', 'x.txt', [$a]);

    $this->files->delete($file);
    expect($this->drives->contents($this->drive, $a)->files)->toBe([])
        ->and(collect($this->drives->trash($this->drive)->files)->pluck('id')->all())->toBe([$file->uuid]);

    $this->files->restore($file);
    expect(collect($this->drives->contents($this->drive, $a)->files)->pluck('id')->all())->toBe([$file->uuid]);

    $this->files->delete($file, force: true);
    expect($file->newQuery()->withTrashed()->find($file->id))->toBeNull();
    Storage::disk('drive')->assertMissing($file->filename_disk);

    Event::assertDispatched(DriveFileTrashed::class);
    Event::assertDispatched(DriveFileRestored::class);
    Event::assertDispatched(DriveFileDeleted::class, fn ($event) => $event->file->id === $file->uuid);
});

it('renames and describes a file', function () {
    $file = $this->files->storeContent($this->drive, 'x', 'x.txt');

    $this->files->update($file, ['name' => '../../secret/notes.md', 'description' => 'Mes notes']);

    expect($file->refresh()->filename_download)->toBe('notes.md')
        ->and($file->description)->toBe('Mes notes');
});

it('refuses empty, too large and unaccepted files', function () {
    expect(fn () => $this->files->storeContent($this->drive, '', 'vide.txt'))->toThrow(DriveException::class, 'vide');

    config(['oi-laravel-drive.files.max_bytes' => 4]);
    expect(fn () => $this->files->storeContent($this->drive, '12345', 'gros.txt'))->toThrow(DriveException::class, 'taille');

    config(['oi-laravel-drive.files.max_bytes' => 1000, 'oi-laravel-drive.files.allowed_mimes' => ['application/pdf']]);
    expect(fn () => $this->files->storeContent($this->drive, 'texte', 'faux.pdf'))->toThrow(DriveException::class, 'text/plain');
});

it('gives the text of a textual file, cut when too long, and none for a binary one', function () {
    $text = $this->files->storeContent($this->drive, "# Titre\n\nContenu", 'notes.md');
    expect($this->files->text($text))->toBe(['text' => "# Titre\n\nContenu", 'truncated' => false])
        ->and($this->files->text($text, 4))->toBe(['text' => '# Ti', 'truncated' => true]);

    $png = $this->files->storeContent($this->drive, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='), 'pixel.png');
    expect($png->mimetype)->toBe('image/png')
        ->and($png->width)->toBe(1)
        ->and($this->files->text($png))->toBeNull();
});

it('downloads a file through a temporary signed link', function () {
    config(['oi-laravel-drive.files.disk_temporary_urls' => false]);
    $file = $this->files->storeContent($this->drive, 'contenu', 'note.txt');
    $link = $this->files->downloadUrl($file);

    $this->get($link['url'])->assertOk()->assertDownload('note.txt');
    $this->get(route('drive.files.download', ['file' => $file->uuid]))->assertForbidden();
});

it('uses the temporary URLs of the disk when it has some', function () {
    $file = $this->files->storeContent($this->drive, 'contenu', 'note.txt');

    expect($this->files->downloadUrl($file)['url'])->toContain($file->filename_disk);
});
