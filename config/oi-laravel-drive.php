<?php

use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\Models\DriveEntry;
use OiLab\OiLaravelDrive\Models\DriveFolderLink;
use OiLab\OiLaravelDrive\Models\DriveFolderShare;
use OiLab\OiLaravelDrive\Models\DriveUpload;
use OiLab\OiLaravelDrive\Policies\DrivePolicy;
use OiLab\OiLaravelDrive\Resolvers\ConfigurableDriveableResolver;
use OiLab\OiLaravelDrive\Sharing\AnyShareTarget;

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model owning folders and files. `user_name_attribute` is read to
    | label an owner; when it is empty the label falls back to
    | "firstname lastname", then to the email.
    |
    */
    'user_model' => 'App\Models\User',

    'user_name_attribute' => 'name',

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Folders and files are the models of oi-laravel-attachments (configured
    | there). Override these classes with your own subclasses if needed.
    |
    */
    'models' => [
        'drive' => Drive::class,
        'entry' => DriveEntry::class,
        'upload' => DriveUpload::class,
        'share' => DriveFolderShare::class,
        'link' => DriveFolderLink::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Driveable (the entity a drive belongs to)
    |--------------------------------------------------------------------------
    |
    | The resolver tells which entity (Team, Organization…) the user works in
    | and whether a user belongs to an entity. The default resolver reads the
    | `current` relation/attribute of the user and the `members` relation of
    | the entity. Bind your own DriveableResolver class for other rules.
    |
    | `current_only`: a user only reaches the drives of their current entity.
    |
    */
    'driveable' => [
        'resolver' => ConfigurableDriveableResolver::class,
        'current' => env('OI_DRIVE_CURRENT_ATTRIBUTE', 'currentTeam'),
        'members' => env('OI_DRIVE_MEMBERS_RELATION', 'users'),
        'current_only' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy
    |--------------------------------------------------------------------------
    |
    | Registered for the Drive model. Abilities: view, update, delete.
    |
    */
    'policy' => DrivePolicy::class,

    /*
    |--------------------------------------------------------------------------
    | Drives
    |--------------------------------------------------------------------------
    |
    | `multiple`: false gives every entity a single drive, created on first
    | use and named `default_name`; true lets an entity create named drives.
    |
    */
    'drives' => [
        'multiple' => (bool) env('OI_DRIVE_MULTIPLE', false),
        'default_name' => env('OI_DRIVE_DEFAULT_NAME', 'Documents'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Folders
    |--------------------------------------------------------------------------
    |
    | `unique_names`: refuse two folders with the same name (case-insensitive)
    | in the same parent folder of a drive.
    |
    */
    'folders' => [
        'unique_names' => (bool) env('OI_DRIVE_UNIQUE_FOLDER_NAMES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    |
    | `allowed_mimes`: null accepts every type; otherwise a list of MIME types
    | (read in the content, never in the name). `collection` is the
    | attachments collection linking a file to its folders.
    | `disk_temporary_urls`: download links use the disk's own temporary URLs
    | when it has some (S3…); false always uses the signed route of the
    | package.
    |
    */
    'files' => [
        'disk' => env('OI_DRIVE_DISK', env('ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local'))),
        'directory' => env('OI_DRIVE_DIRECTORY', 'drive'),
        'max_bytes' => (int) env('OI_DRIVE_MAX_BYTES', 50 * 1024 * 1024),
        'allowed_mimes' => null,
        'collection' => 'drive',
        'disk_temporary_urls' => (bool) env('OI_DRIVE_DISK_TEMPORARY_URLS', true),
        'temporary_url_minutes' => (int) env('OI_DRIVE_TEMPORARY_URL_MINUTES', 30),
        'text_preview_bytes' => 100_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trash
    |--------------------------------------------------------------------------
    |
    | `enabled`: false makes every deletion permanent. `purge_after_days`:
    | items left that many days in the trash are deleted for good by
    | `drive:purge-trash`, scheduled daily (null keeps them forever).
    |
    */
    'trash' => [
        'enabled' => (bool) env('OI_DRIVE_TRASH', true),
        'purge_after_days' => env('OI_DRIVE_TRASH_PURGE_DAYS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sharing
    |--------------------------------------------------------------------------
    |
    | A folder can be shared, read-only, with other entities: their members
    | read it, its subfolders and its files. `model`: the class a folder is
    | shared with (null: the class of the entity owning the drive).
    | `name_attribute` labels it. `targets`: a ShareTargets class listing the
    | entities a user may share with — the default allows every entity of
    | the class; bind your own to keep tenants apart.
    |
    */
    'sharing' => [
        'model' => null,
        'name_attribute' => 'name',
        'targets' => AnyShareTarget::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Public links
    |--------------------------------------------------------------------------
    |
    | A folder can have one public link, optionally protected by a password
    | and an expiry date. The package stores the link; the host serves the
    | page. `route`: the name of the host route showing a link, taking a
    | `token` parameter, used to give its URL (null: no URL).
    | `archive_max_bytes`: the largest folder downloaded as a ZIP.
    |
    */
    'links' => [
        'route' => env('OI_DRIVE_LINK_ROUTE'),
        'password_min_length' => 6,
        'archive_max_bytes' => (int) env('OI_DRIVE_ARCHIVE_MAX_BYTES', 500 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | API routes
    |--------------------------------------------------------------------------
    |
    | JSON endpoints of the package. Disable them to expose the DriveManager
    | through the host's own controllers.
    |
    */
    'api' => [
        'enabled' => (bool) env('OI_DRIVE_API', true),
        'prefix' => 'api/drive',
        'name' => 'drive.api.',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Signed routes
    |--------------------------------------------------------------------------
    |
    | Always registered: the download of a file on a disk without temporary
    | URLs, and the upload links opened by the MCP tools (PUT of the raw body
    | or a drag-and-drop page). The signature is the whole credential; keep
    | `web` (CSRF) out of the middleware, the PUT comes from outside.
    |
    */
    'signed_routes' => [
        'prefix' => 'drive',
        'name' => 'drive.',
        'middleware' => ['throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads by link (MCP)
    |--------------------------------------------------------------------------
    |
    | MCP carries binaries badly: an assistant opens an upload, then sends the
    | file to a one-time signed URL (or gives the page to the user). Small
    | files may travel inline, base64-encoded in the tool call.
    |
    */
    'uploads' => [
        'enabled' => (bool) env('OI_DRIVE_UPLOADS', true),
        'ttl_minutes' => (int) env('OI_DRIVE_UPLOAD_TTL', 10),
        'inline_max_bytes' => (int) env('OI_DRIVE_INLINE_MAX_BYTES', 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP tools
    |--------------------------------------------------------------------------
    |
    | `naming`: `kebab` (list-drive-folder) or `snake` (list_drive_folder),
    | to match the other tools of the host server.
    |
    */
    'mcp' => [
        'naming' => env('OI_DRIVE_MCP_NAMING', 'kebab'),
    ],
];
