<?php

use Illuminate\Support\Facades\Storage;
use OiLab\OiLaravelDrive\Mcp\DriveTools;
use OiLab\OiLaravelDrive\Mcp\Tools\CreateDriveFolder;
use OiLab\OiLaravelDrive\Mcp\Tools\DeleteDriveFile;
use OiLab\OiLaravelDrive\Mcp\Tools\DeleteDriveFolder;
use OiLab\OiLaravelDrive\Mcp\Tools\GetDriveFile;
use OiLab\OiLaravelDrive\Mcp\Tools\GetDriveUploadStatus;
use OiLab\OiLaravelDrive\Mcp\Tools\ListDriveFolder;
use OiLab\OiLaravelDrive\Mcp\Tools\ListDriveTrash;
use OiLab\OiLaravelDrive\Mcp\Tools\RequestDriveUpload;
use OiLab\OiLaravelDrive\Mcp\Tools\RestoreDriveItem;
use OiLab\OiLaravelDrive\Mcp\Tools\SearchDrive;
use OiLab\OiLaravelDrive\Mcp\Tools\UpdateDriveFile;
use OiLab\OiLaravelDrive\Mcp\Tools\UpdateDriveFolder;
use OiLab\OiLaravelDrive\Mcp\Tools\UploadDriveFile;
use OiLab\OiLaravelDrive\Models\DriveUpload;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DriveUploads;
use OiLab\OiLaravelDrive\Tests\Fixtures\DriveServer;
use OiLab\OiLaravelDrive\Tests\Fixtures\Team;
use OiLab\OiLaravelDrive\Tests\Fixtures\User;

beforeEach(function () {
    ['team' => $this->team, 'user' => $this->user, 'drive' => $this->drive] = driveContext();
});

it('names the tools in kebab or snake case', function () {
    expect((new ListDriveFolder)->name())->toBe('list-drive-folder');

    config(['oi-laravel-drive.mcp.naming' => 'snake']);

    expect((new ListDriveFolder)->name())->toBe('list_drive_folder')
        ->and(DriveTools::all())->toHaveCount(13);
});

it('creates a folder, writes a text file in it, then reads it back', function () {
    $folder = DriveServer::actingAs($this->user)
        ->tool(CreateDriveFolder::class, ['name' => 'Comptes rendus'])
        ->assertOk()
        ->assertSee('Comptes rendus');

    $folderId = app(DriveManager::class)->folderQuery($this->drive)->value('uuid');

    DriveServer::actingAs($this->user)
        ->tool(UploadDriveFile::class, ['filename' => 'cr.md', 'content_text' => "# Réunion\n\nDécisions", 'folder_ids' => [$folderId]])
        ->assertOk()
        ->assertSee('cr.md');

    $fileId = app(DriveManager::class)->fileQuery($this->drive)->value('uuid');

    DriveServer::actingAs($this->user)
        ->tool(ListDriveFolder::class, ['folder_id' => $folderId])
        ->assertOk()
        ->assertSee($fileId);

    DriveServer::actingAs($this->user)
        ->tool(GetDriveFile::class, ['file_id' => $fileId])
        ->assertOk()
        ->assertSee('Décisions')
        ->assertSee('download_url');

    DriveServer::actingAs($this->user)
        ->tool(SearchDrive::class, ['query' => 'cr.m'])
        ->assertOk()
        ->assertSee($fileId);
});

it('uploads a base64 file inline and refuses a bad encoding', function () {
    DriveServer::actingAs($this->user)
        ->tool(UploadDriveFile::class, ['filename' => 'a.txt', 'content_base64' => base64_encode('bonjour')])
        ->assertOk()
        ->assertSee('a.txt');

    DriveServer::actingAs($this->user)
        ->tool(UploadDriveFile::class, ['filename' => 'b.txt', 'content_base64' => '***'])
        ->assertHasErrors(['base 64']);
});

it('opens an upload link, receives the file by PUT once, and reports its status', function () {
    $folder = app(DriveFolders::class)->create($this->drive, 'Reçus');

    DriveServer::actingAs($this->user)
        ->tool(RequestDriveUpload::class, ['filename' => 'scan.txt', 'folder_ids' => [$folder->uuid], 'title' => 'Scan'])
        ->assertOk()
        ->assertSee('upload_url')
        ->assertSee('page_url');

    $upload = DriveUpload::query()->sole();
    $uploads = app(DriveUploads::class);

    $this->get($uploads->pageUrl($upload))->assertOk()->assertSee('scan.txt');

    $this->call('PUT', $uploads->uploadUrl($upload), content: 'contenu scanné')->assertCreated()->assertJsonPath('status', 'completed');
    $this->call('PUT', $uploads->uploadUrl($upload), content: 'encore')->assertStatus(409);

    DriveServer::actingAs($this->user)
        ->tool(GetDriveUploadStatus::class, ['upload_id' => $upload->id])
        ->assertOk()
        ->assertSee('completed')
        ->assertSee('Scan');

    $file = $upload->refresh()->file;
    expect($file->driveEntry->user_id)->toBe($this->user->id)
        ->and($file->created_by)->toBe($this->user->id)
        ->and(Storage::disk('drive')->get($file->filename_disk))->toBe('contenu scanné');
});

it('refuses an unsigned or expired upload link', function () {
    $upload = app(DriveUploads::class)->open($this->drive, $this->user, 'x.txt');

    $this->call('PUT', route('drive.uploads.put', ['upload' => $upload->id]), content: 'x')->assertForbidden();

    $url = app(DriveUploads::class)->uploadUrl($upload);
    $this->travel(11)->minutes();
    $this->call('PUT', $url, content: 'x')->assertForbidden();
});

it('renames, moves, refiles, deletes and restores', function () {
    $folders = app(DriveFolders::class);
    $a = $folders->create($this->drive, 'A');
    $b = $folders->create($this->drive, 'B');
    $file = app(DriveFiles::class)->storeContent($this->drive, 'x', 'x.txt', [$a]);

    DriveServer::actingAs($this->user)
        ->tool(UpdateDriveFolder::class, ['folder_id' => $b->uuid, 'name' => 'B2', 'parent_id' => $a->uuid])
        ->assertOk()
        ->assertSee('B2');

    DriveServer::actingAs($this->user)
        ->tool(UpdateDriveFile::class, ['file_id' => $file->uuid, 'add_folder_ids' => [$b->uuid], 'title' => 'Titre'])
        ->assertOk()
        ->assertSee($b->uuid)
        ->assertSee('Titre');

    DriveServer::actingAs($this->user)
        ->tool(DeleteDriveFile::class, ['file_id' => $file->uuid])
        ->assertOk();

    DriveServer::actingAs($this->user)
        ->tool(DeleteDriveFolder::class, ['folder_id' => $a->uuid])
        ->assertOk();

    DriveServer::actingAs($this->user)
        ->tool(ListDriveTrash::class)
        ->assertOk()
        ->assertSee('x.txt')
        ->assertSee($a->uuid);

    DriveServer::actingAs($this->user)
        ->tool(RestoreDriveItem::class, ['folder_id' => $a->uuid])
        ->assertOk();

    DriveServer::actingAs($this->user)
        ->tool(RestoreDriveItem::class, ['file_id' => $file->uuid])
        ->assertOk();

    expect($file->refresh()->trashed())->toBeFalse()
        ->and($b->refresh()->trashed())->toBeFalse();
});

it('refuses the folders of another entity', function () {
    $folder = app(DriveFolders::class)->create($this->drive, 'Privé');
    $stranger = User::factory()->create()->joinAndSwitch(Team::query()->create(['name' => 'Autre']));

    DriveServer::actingAs($stranger)
        ->tool(ListDriveFolder::class, ['folder_id' => $folder->uuid])
        ->assertHasErrors(['Accès refusé']);

    DriveServer::actingAs($stranger)
        ->tool(GetDriveFile::class, ['file_id' => '00000000-0000-0000-0000-000000000000'])
        ->assertHasErrors(['introuvable']);
});
