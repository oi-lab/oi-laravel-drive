---
title: "Configuration"
description: "The keys of config/oi-laravel-drive.php"
section: configuration
order: 1
---

# Configuration

```bash
php artisan vendor:publish --tag=oi-laravel-drive-config
```

| Key | Default | Purpose |
|-----|---------|---------|
| `driveable.*` | `currentTeam`, `users`, `current_only: true` | Current entity and members |
| `policy` | `DrivePolicy` | Abilities `view`, `update`, `create`, `delete` |
| `drives.multiple` | `false` | Several named drives per entity |
| `folders.unique_names` | `false` | Unique names per parent folder |
| `files.disk`, `files.directory` | attachments disk, `drive` | Storage |
| `files.max_bytes`, `files.allowed_mimes` | 50 MB, `null` | Upload limits |
| `files.disk_temporary_urls` | `true` | Use the disk's temporary URLs when it has some |
| `trash.enabled`, `trash.purge_after_days` | `true`, `null` | Trash and purge |
| `api.*` | `api/drive`, `['api', 'auth:sanctum']` | JSON API |
| `signed_routes.*` | `drive`, `['throttle:60,1']` | Download and upload links |
| `uploads.*` | 10 min, 1 MB inline | Upload links for assistants |
| `mcp.naming` | `kebab` | Tool names |

See [Access rules](./access.md).
