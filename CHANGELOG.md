# Changelog

All notable changes to `oi-lab/oi-laravel-drive` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-17

### Added

- Folders shared read-only with other entities (`drive_folder_shares`, `DriveShares`): subfolders and files included, `sharedWith()`, `reaches()`, listings whose breadcrumbs start at the shared folder
- `ShareTargets` contract (`sharing.targets`) limiting the entities a user may share with, to keep tenants apart
- Public link of a folder (`drive_folder_links`, `DriveLinks`): token, hashed password, expiry date, regeneration, visit counter, `unlockKey()` for the visitor's session, URL through the host route `links.route`
- ZIP download of a folder tree (`DriveArchives`), limited by `links.archive_max_bytes`
- `shares_count` and `has_link` on `DriveFolderData`
- MCP tools `get-drive-folder-sharing`, `share-drive-folder`, `update-drive-folder-link`
- Events `DriveFolderShared`, `DriveFolderUnshared`, `DriveFolderLinkSaved`, `DriveFolderLinkRevoked`

## [1.0.0] - 2026-10-16

### Added

- Drives belonging to any entity (`driveable` morph), single or multiple per entity
- Folders and files built on oi-laravel-attachments, a file filed in several folders, root files
- Owner and settable date of creation for every folder and file (`drive_entries`)
- Trash with restore, folder deletion with or without its exclusive files, `drive:purge-trash`
- Optional unique folder names per parent folder
- `DriveManager`, `DriveFolders`, `DriveFiles`, `DriveUploads` services and laravel-data DTOs
- JSON API (can be switched off), temporary download links, signed upload links
- 13 MCP tools for laravel/mcp ^0.9|^1.0, kebab or snake case names
- 17 events for indexing and auditing
- `DriveableResolver` contract and replaceable `DrivePolicy`
- PHP 8.2+, Laravel 11/12/13 — 44 Pest tests
