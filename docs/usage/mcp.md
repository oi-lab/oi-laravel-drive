---
title: "MCP Tools"
description: "Register the drive tools in a laravel/mcp server"
section: usage
order: 8
---

# MCP Tools

```php
use OiLab\OiLaravelDrive\Mcp\DriveTools;

class AppServer extends Server
{
    protected function boot(): void
    {
        $this->tools = [...DriveTools::all(), ...$this->tools];
    }
}
```

Append `DriveTools::INSTRUCTIONS` to the server instructions. The caller is the user of the token; every item is authorized through its drive.

| Tool | Does |
|------|------|
| `list-drive-folder` | Content of a folder or the root, with breadcrumbs |
| `search-drive` | Folders and files by name, title, description |
| `get-drive-file` | Metadata, temporary link, text of a textual file |
| `create-drive-folder`, `update-drive-folder`, `delete-drive-folder` | Folders |
| `request-drive-upload`, `get-drive-upload-status` | One-time upload link (PUT or drag-and-drop page) |
| `upload-drive-file` | Small inline file (text or base64) |
| `update-drive-file`, `delete-drive-file` | Files and their folders |
| `list-drive-trash`, `restore-drive-item` | Trash |
| `get-drive-folder-sharing` | Entities a folder is shared with, its public link |
| `share-drive-folder` | Share or unshare a folder (entity key or name) |
| `update-drive-folder-link` | Open, protect, expire, regenerate or close the public link |
| `list-drives` | Only with `drives.multiple` |

`mcp.naming = snake` names them `list_drive_folder`… A server with its own tool base class can wrap each tool: `name()`, `description()`, `schema()` and `handle()` are public.
