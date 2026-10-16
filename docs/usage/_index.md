---
title: "Usage Overview"
description: "The services and Data objects of the drive"
section: usage
order: 1
---

# Usage Overview

Three services cover everything, all resolved from the container:

| Service | Role |
|---------|------|
| `DriveManager` | Drives, lookups by UUID, listings, search, trash, owners |
| `DriveFolders` | Create, rename, move, delete, restore folders |
| `DriveFiles` | Store, describe, file in folders, delete, restore, download, read text |

They return models; `DrivePresenter` turns them into Data objects (`DriveFolderData`, `DriveFileData`), whose `id` is the UUID.

- [Folders](./folders.md)
- [Files](./files.md)
- [Trash](./trash.md)
- [JSON API](./api.md)
- [MCP tools](./mcp.md)
