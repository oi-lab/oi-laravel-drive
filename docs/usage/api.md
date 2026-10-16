---
title: "JSON API"
description: "The routes of the drive API and how to switch them off"
section: usage
order: 6
---

# JSON API

With `api.enabled`, routes are registered under `api.prefix` (`api/drive`) with `api.middleware`. Identifiers are UUIDs; `drive` is optional and defaults to the drive of the user's current entity.

| Method | URI | Action |
|--------|-----|--------|
| GET | `drives` | Drives of the current entity |
| GET | `contents?folder=` | Content of a folder, or the root |
| GET | `search?q=` | Folders and files by name |
| GET / DELETE | `trash` | List / empty the trash |
| POST | `folders` | `name`, `parent_id`, `owner_id`, `created_at` |
| PATCH | `folders/{folder}` | `name`, `parent_id` (null: root), `owner_id` |
| DELETE | `folders/{folder}` | `?with_files=1`, `?force=1` |
| POST | `folders/{folder}/restore` | |
| POST | `files` | multipart `file`, `folder_ids[]`, `title`, `description`, `owner_id`, `created_at` |
| PATCH | `files/{file}` | `name`, `title`, `description`, `folder_ids`, `owner_id` |
| GET | `files/{file}/content` | Data, temporary link and text |
| GET | `files/{file}/download` | Redirect to the file |
| POST / DELETE | `files/{file}/folders[/{folder}]` | File in / out of folders |

Errors: `422`/`409`/`403` with `{"error": "folder_name_taken", "message": "…"}`.

## Using your own API

Set `api.enabled` to `false` and call `DriveManager`, `DriveFolders` and `DriveFiles` from your controllers. The signed routes (downloads, upload links) stay registered.
