---
title: "Trash"
description: "How deletions, restores and purges work"
section: usage
order: 4
---

# Trash

With `trash.enabled` (default), deleting a folder or a file sends it to the trash:

```php
$drives->trash($drive);       // what was deleted directly
$folders->restore($folder);   // with everything trashed with it
$files->restore($file);
$drives->emptyTrash($drive);  // for good
```

What goes to the trash with a folder (subfolders, files with `withFiles`) is marked and comes back with it. A folder whose parent no longer exists comes back at the root.

## Purge

Set `trash.purge_after_days` (`OI_DRIVE_TRASH_PURGE_DAYS`): `drive:purge-trash` runs daily and deletes for good what has been in the trash longer.

```bash
php artisan drive:purge-trash --days=30
```

With `trash.enabled = false`, every deletion is permanent.
