# OI Laravel Drive

Use the `oi-laravel-drive` package to give an entity (Team, Organization…) a file space with folders. It
builds on oi-laravel-attachments: folders and files are the attachments `Folder`/`File` models, a
`drive_entries` row gives each one its drive, owner and date of creation, and a file is filed in folders
through attachments (no folder: the root). Work through `DriveManager`, `DriveFolders` and `DriveFiles`
(never write `drive_entries` or the `drive` attachments by hand), register `DriveTools::all()` in the MCP
server, and listen to the `Drive*` events to index or audit. Folder sharing (other entities, public link with
password, ZIP) goes through `DriveShares`, `DriveLinks` and `DriveArchives`.

- IMPORTANT: Activate `oilab-laravel-drive` when working on drives, folders or files of an entity, the
  drive API or MCP tools, the trash, upload links, folder sharing or public links, or search indexing of drive content in this application.
