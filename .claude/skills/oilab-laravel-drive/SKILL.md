# OI Laravel Drive — AI Context

`oi-lab/oi-laravel-drive` gives any entity of the app (a Team, an Organization…) a drive: a tree of folders
and files. It reuses the `Folder`/`File`/`Attachment` models of oi-laravel-attachments and adds the entity,
an owner, a date of creation, a trash, a JSON API, MCP tools and events.

## Core Concepts

| Model | Table | Notes |
|-------|-------|-------|
| `OiLab\OiLaravelDrive\Models\Drive` | `drives` | `driveable` morph (the entity), `name`, `user_id` (owner), UUID route key |
| `OiLab\OiLaravelDrive\Models\DriveEntry` | `drive_entries` | One per folder/file: `drive_id`, `entry` morph, `kind`, `user_id` (owner), `created_at`, `trashed_with_folder_id` |
| attachments `Folder` | `folders` | Tree via `parent_id`; soft deletes = trash |
| attachments `File` | `files` | Stored on `files.disk` under `drive/{drive uuid}/`; soft deletes = trash |
| attachments `Attachment` | `attachments` | Collection `drive`: files a file in a folder (`attachable` = folder) |
| `OiLab\OiLaravelDrive\Models\DriveUpload` | `drive_uploads` | One-time upload link opened by an assistant |

Runtime relations added to the attachments models (no change to their classes):
`$folder->driveEntry`, `$file->driveEntry`, `$file->driveFolders` (active folders only), `$folder->driveFiles`.

Rules:
- A file filed in no active folder is at the **root**. One file may be in several folders.
- Deleting a file removes it from every folder. Taking it out of one folder is `detach`.
- Deleting a folder takes its subfolders; `withFiles` also takes the files filed **only** in that tree;
  shared files are only taken out. Without `withFiles`, the files stay (at the root if filed nowhere else).
- With the trash on, deletions are soft; what is trashed with a folder (`trashed_with_folder_id`) comes back
  with it. `force` or `trash.enabled = false` deletes for good (storage included).
- Public identifiers (API, MCP, Data `id`) are **UUIDs**.

## Public API

```php
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DrivePresenter;

$drives = app(DriveManager::class);
$drive = $drives->driveFor($team);              // default drive, created on first use
$drive = $drives->resolveDrive($user, $uuid);   // by UUID, or the user's current entity's default drive

$folder = app(DriveFolders::class)->create($drive, 'Clients', parent: null, owner: $user, createdAt: null);
app(DriveFolders::class)->update($folder, name: 'Clients 2026');
app(DriveFolders::class)->move($folder, $parentOrNull);
app(DriveFolders::class)->delete($folder, withFiles: false, force: false);
app(DriveFolders::class)->restore($folder);

$files = app(DriveFiles::class);
$file = $files->store($drive, $uploadedFile, folders: [$folder], owner: null, createdAt: null, title: null, description: null);
$file = $files->storeContent($drive, $content, 'notes.md', folders: []);
$files->update($file, ['name' => 'x.md', 'title' => '…', 'description' => '…']);
$files->attach($file, [$a, $b]); $files->detach($file, [$a]); $files->sync($file, []);
$files->delete($file); $files->restore($file);
$files->downloadUrl($file);                     // ['url' => …, 'expires_at' => …]
$files->text($file);                            // ['text' => …, 'truncated' => bool] or null (binary)

$drives->contents($drive, $folderOrNull);       // DriveListingData
$drives->search($drive, 'term');                // DriveItemsData
$drives->trash($drive); $drives->emptyTrash($drive); $drives->purgeTrash($days);
$drives->setOwner($folderOrFile, $userOrNull); $drives->setCreatedAt($folderOrFile, $date);
$drives->findFolder($uuid, withTrashed: false); $drives->findFile($uuid); $drives->driveOf($item);

app(DrivePresenter::class)->file($file);        // DriveFileData (folder_ids, owner, created_at…)
```

Failures throw `OiLab\OiLaravelDrive\Exceptions\DriveException` (`rule`, `status`, French message), rendered
as `{error, message}` JSON on API requests.

Authorization: every folder/file is authorized through its drive — `Gate::authorize('view'|'update', $drive)`,
`create` (class) and `delete` only in multiple mode. Default `DrivePolicy`: member of the entity, from that
entity when `driveable.current_only`.

Access contract (`OiLab\OiLaravelDrive\Contracts\DriveableResolver`): `current(User): ?Model` and
`isMember(User, Model): bool`. The default `ConfigurableDriveableResolver` reads `driveable.current` on the
user and `driveable.members` on the entity.

## MCP Tools

```php
use OiLab\OiLaravelDrive\Mcp\DriveTools;

$this->tools = [...DriveTools::all(), ...];     // in the host's laravel/mcp Server::boot()
// add DriveTools::INSTRUCTIONS to the server instructions
```

Tools (base class `Mcp\Tools\DriveTool`, caller = token user, `drive_id` optional): `list-drive-folder`,
`search-drive`, `get-drive-file`, `list-drive-trash`, `get-drive-upload-status`, `create-drive-folder`,
`update-drive-folder`, `delete-drive-folder`, `request-drive-upload`, `upload-drive-file`, `update-drive-file`,
`delete-drive-file`, `restore-drive-item` (+ `list-drives` in multiple mode). `mcp.naming = snake` gives
`list_drive_folder`. A host with its own tool base class can wrap them: each tool's `name()`, `description()`,
`schema()` and `handle()` are public.

Uploads: `request-drive-upload` opens a `DriveUpload`; the file is PUT raw to the signed `drive.uploads.put`
route (or dropped on the signed `drive.uploads.page` page) and becomes a drive file at once.

## Events

`OiLab\OiLaravelDrive\Events\*`: `DriveCreated|Updated|Deleted`, `DriveFolderCreated|Updated|Moved|Trashed|
Restored|Deleted`, `DriveFileCreated|Updated|Trashed|Restored|Deleted`, `DriveFileAttached|Detached`,
`DriveEntryOwnerChanged`. `*Deleted` events carry Data snapshots (`DriveFolderData`, `DriveFileData`,
`DriveData`). Use them for Scout / oi-laravel-raggable indexing.

## Commands

```bash
php artisan drive:purge-trash [--days=N]   # daily when trash.purge_after_days is set
php artisan drive:prune-uploads [--days=7] # hourly when uploads.enabled
```

## Configuration

`config/oi-laravel-drive.php` (`php artisan vendor:publish --tag=oi-laravel-drive-config`):

| Key | Default | Env |
|-----|---------|-----|
| `user_model`, `user_name_attribute` | `App\Models\User`, `name` | |
| `driveable.resolver` / `current` / `members` / `current_only` | configurable resolver, `currentTeam`, `users`, `true` | `OI_DRIVE_CURRENT_ATTRIBUTE`, `OI_DRIVE_MEMBERS_RELATION` |
| `policy` | `DrivePolicy` | |
| `drives.multiple` / `default_name` | `false` / `Documents` | `OI_DRIVE_MULTIPLE`, `OI_DRIVE_DEFAULT_NAME` |
| `folders.unique_names` | `false` | `OI_DRIVE_UNIQUE_FOLDER_NAMES` |
| `files.disk` / `directory` / `max_bytes` / `allowed_mimes` | attachments disk, `drive`, 50 MB, `null` | `OI_DRIVE_DISK`, `OI_DRIVE_MAX_BYTES` |
| `files.disk_temporary_urls` / `temporary_url_minutes` | `true` / `30` | |
| `trash.enabled` / `purge_after_days` | `true` / `null` | `OI_DRIVE_TRASH`, `OI_DRIVE_TRASH_PURGE_DAYS` |
| `api.enabled` / `prefix` / `name` / `middleware` | `true`, `api/drive`, `drive.api.`, `['api','auth:sanctum']` | `OI_DRIVE_API` |
| `signed_routes.prefix` / `name` / `middleware` | `drive`, `drive.`, `['throttle:60,1']` | |
| `uploads.enabled` / `ttl_minutes` / `inline_max_bytes` | `true`, `10`, 1 MB | `OI_DRIVE_UPLOADS` |
| `mcp.naming` | `kebab` | `OI_DRIVE_MCP_NAMING` |

## Host Integration Checklist

1. `composer require oi-lab/oi-laravel-drive` (pulls oi-laravel-attachments), publish config, `php artisan migrate`.
2. Set `driveable.current` / `driveable.members` (or bind a `DriveableResolver`), and the `user_model`.
3. Add morph map entries for `Drive`, the folder/file models and the entity if the app enforces a morph map.
4. Keep the package API (`api.middleware` = the app's API stack) or set `api.enabled = false` and call the services.
5. Register `DriveTools::all()` + `DriveTools::INSTRUCTIONS` in the MCP server.
6. Override the policy (`policy`) when roles restrict writing.
7. Listen to the `Drive*` events for indexing, notifications or audit.

## Updating the AI Skill

After changing the package, update `resources/stubs/ai-skill.md`, run `composer sync-ai-skills`, then in host
apps run `php artisan oi:skills oilab-laravel-drive --project`.
