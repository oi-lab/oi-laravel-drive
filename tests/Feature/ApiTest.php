<?php

use Illuminate\Http\UploadedFile;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Tests\Fixtures\Team;
use OiLab\OiLaravelDrive\Tests\Fixtures\User;

beforeEach(function () {
    ['team' => $this->team, 'user' => $this->user, 'drive' => $this->drive] = driveContext();
});

it('lists the drives of the current entity', function () {
    $this->getJson('/api/drive/drives')
        ->assertOk()
        ->assertJsonPath('multiple', false)
        ->assertJsonPath('drives.0.id', $this->drive->uuid)
        ->assertJsonPath('drives.0.name', 'Documents');
});

it('creates a folder, uploads a file into it and lists the folder', function () {
    $folder = $this->postJson('/api/drive/folders', ['name' => 'Contrats'])
        ->assertCreated()
        ->assertJsonPath('name', 'Contrats')
        ->assertJsonPath('parent_id', null)
        ->assertJsonPath('owner.id', $this->user->id)
        ->json('id');

    $file = $this->post('/api/drive/files', [
        'file' => UploadedFile::fake()->createWithContent('bail.txt', 'Le bail'),
        'folder_ids' => [$folder],
        'title' => 'Bail',
        'created_at' => '2019-03-04T05:06:07+00:00',
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('folder_ids', [$folder])
        ->assertJsonPath('title', 'Bail')
        ->json('id');

    $this->getJson('/api/drive/contents?folder='.$folder)
        ->assertOk()
        ->assertJsonPath('folder.name', 'Contrats')
        ->assertJsonPath('breadcrumbs.0.name', 'Contrats')
        ->assertJsonPath('files.0.id', $file);

    $this->getJson('/api/drive/contents')
        ->assertOk()
        ->assertJsonPath('folders.0.id', $folder)
        ->assertJsonCount(0, 'files');

    $this->getJson("/api/drive/files/{$file}/content")
        ->assertOk()
        ->assertJsonPath('text', 'Le bail')
        ->assertJsonPath('file.created_at', fn (string $date) => str_starts_with($date, '2019-03-04T05:06:07'));
});

it('updates, files and moves through the API', function () {
    $folders = app(DriveFolders::class);
    $a = $folders->create($this->drive, 'A');
    $b = $folders->create($this->drive, 'B');
    $colleague = User::factory()->create()->joinAndSwitch($this->team);
    $file = app(DriveFiles::class)->storeContent($this->drive, 'x', 'x.txt', [$a]);

    $this->patchJson("/api/drive/files/{$file->uuid}", ['name' => 'y.txt', 'folder_ids' => [$b->uuid], 'owner_id' => $colleague->id])
        ->assertOk()
        ->assertJsonPath('name', 'y.txt')
        ->assertJsonPath('folder_ids', [$b->uuid])
        ->assertJsonPath('owner.id', $colleague->id);

    $this->postJson("/api/drive/files/{$file->uuid}/folders", ['folder_ids' => [$a->uuid]])
        ->assertOk()
        ->assertJsonCount(2, 'folder_ids');

    $this->deleteJson("/api/drive/files/{$file->uuid}/folders/{$b->uuid}")
        ->assertOk()
        ->assertJsonPath('folder_ids', [$a->uuid]);

    $this->patchJson("/api/drive/folders/{$b->uuid}", ['parent_id' => $a->uuid, 'name' => 'B bis'])
        ->assertOk()
        ->assertJsonPath('parent_id', $a->uuid)
        ->assertJsonPath('name', 'B bis');

    $this->patchJson("/api/drive/folders/{$b->uuid}", ['parent_id' => null])
        ->assertOk()
        ->assertJsonPath('parent_id', null);
});

it('deletes to the trash, restores and empties it', function () {
    $folder = app(DriveFolders::class)->create($this->drive, 'A');
    $file = app(DriveFiles::class)->storeContent($this->drive, 'x', 'x.txt', [$folder]);

    $this->deleteJson("/api/drive/folders/{$folder->uuid}?with_files=1")->assertNoContent();

    $this->getJson('/api/drive/trash')
        ->assertOk()
        ->assertJsonPath('folders.0.id', $folder->uuid)
        ->assertJsonCount(0, 'files');

    $this->postJson("/api/drive/folders/{$folder->uuid}/restore")->assertOk()->assertJsonPath('deleted_at', null);
    $this->getJson("/api/drive/files/{$file->uuid}")->assertOk()->assertJsonPath('deleted_at', null);

    $this->deleteJson("/api/drive/files/{$file->uuid}")->assertNoContent();
    $this->deleteJson('/api/drive/trash')->assertOk()->assertJsonPath('deleted', 1);
    $this->getJson("/api/drive/files/{$file->uuid}")->assertNotFound();
});

it('searches folders and files', function () {
    app(DriveFolders::class)->create($this->drive, 'Factures 2024');
    app(DriveFiles::class)->storeContent($this->drive, 'x', 'devis.txt', description: 'Facture du plombier');

    $this->getJson('/api/drive/search?q=FACTU')
        ->assertOk()
        ->assertJsonCount(1, 'folders')
        ->assertJsonCount(1, 'files');

    $this->getJson('/api/drive/search?q=f')->assertUnprocessable();
});

it('refuses the drive of another entity and explains rule violations', function () {
    $other = Team::query()->create(['name' => 'Autre']);
    $stranger = User::factory()->create()->joinAndSwitch($other);
    $folder = app(DriveFolders::class)->create($this->drive, 'Privé');

    $this->actingAs($stranger);
    $this->getJson("/api/drive/folders/{$folder->uuid}")->assertForbidden();
    $this->getJson('/api/drive/contents?drive='.$this->drive->uuid)->assertForbidden();
    $this->getJson('/api/drive/contents')->assertOk()->assertJsonCount(0, 'folders');

    $this->actingAs($this->user);
    $this->postJson('/api/drive/folders', ['name' => 'Enfant', 'parent_id' => $folder->uuid])->assertCreated();
    $this->patchJson("/api/drive/folders/{$folder->uuid}", ['parent_id' => $folder->uuid])
        ->assertUnprocessable()
        ->assertJsonPath('error', 'cyclic_move');
    $this->postJson('/api/drive/folders', [])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('requires authentication', function () {
    auth()->logout();

    $this->getJson('/api/drive/contents')->assertUnauthorized();
});

it('refuses a second drive unless multiple drives are on', function () {
    $this->postJson('/api/drive/drives', ['name' => 'Archives'])->assertForbidden();

    config(['oi-laravel-drive.drives.multiple' => true]);

    $drive = $this->postJson('/api/drive/drives', ['name' => 'Archives'])->assertCreated()->json('id');
    $this->getJson('/api/drive/drives')->assertJsonCount(2, 'drives');
    $this->postJson('/api/drive/folders', ['name' => 'Dans archives', 'drive_id' => $drive])->assertCreated()->assertJsonPath('drive_id', $drive);
    $this->deleteJson("/api/drive/drives/{$drive}")->assertNoContent();
});
