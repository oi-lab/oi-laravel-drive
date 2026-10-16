---
title: "Events"
description: "The events dispatched by the drive and how to index content"
section: advanced
order: 2
---

# Events

All in `OiLab\OiLaravelDrive\Events`, serializable for queued listeners:

| Event | Payload |
|-------|---------|
| `DriveCreated`, `DriveUpdated` | `drive` |
| `DriveDeleted` | `DriveData $drive` |
| `DriveFolderCreated`, `DriveFolderUpdated`, `DriveFolderRestored` | `folder`, `drive` |
| `DriveFolderMoved` | `folder`, `drive`, `fromParentId`, `toParentId` |
| `DriveFolderTrashed` | `folder`, `drive`, `withFiles` |
| `DriveFolderDeleted` | `DriveFolderData $folder`, `drive` |
| `DriveFileCreated`, `DriveFileUpdated`, `DriveFileTrashed`, `DriveFileRestored` | `file`, `drive` |
| `DriveFileDeleted` | `DriveFileData $file`, `drive` |
| `DriveFileAttached`, `DriveFileDetached` | `file`, `folder`, `drive` |
| `DriveEntryOwnerChanged` | `entry`, `drive`, `fromUserId`, `toUserId` |

## Indexing

Search indexing (Laravel Scout, oi-laravel-raggable) is left to the host: listen to `DriveFileCreated` / `DriveFileUpdated` to index, `DriveFileTrashed` / `DriveFileDeleted` to remove, and use `DriveFiles::text()` for the content of textual files.

```php
Event::listen(DriveFileCreated::class, IndexDriveFile::class);
```
