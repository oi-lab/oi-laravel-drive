---
title: "Managed items"
description: "Folders, links and notices the host application keeps: keys, locks, linked files, notices"
section: usage
order: 5
---

# Managed items

The host application can generate part of a drive (a tree per fiscal year, documents filed by month…) and keep it in step, whatever users do.

## Keys and `ensure()`

```php
use OiLab\OiLaravelDrive\Services\DriveFolders;

$folders = app(DriveFolders::class);

$year = $folders->ensure($drive, 'fy:2026', '2026');
$purchases = $folders->ensure($drive, 'fy:2026/purchases', 'Achats', parent: $year, sealed: true);

$drives->folderByKey($drive, 'fy:2026/purchases');
```

`ensure()` creates the folder once, then on each call brings it back from the trash, renames and moves it to match. The key is unique in the drive.

## Locks

| Flag | Effect for users (API, MCP, services) |
|------|---------------------------------------|
| `locked` (default of `ensure()` and `link()`) | The folder or file cannot be renamed, moved or deleted; a folder holding a locked item cannot be deleted |
| `sealed` | No folder or file can be added to the folder or taken out of it |

Refusals throw `DriveException` (`locked`, `locked_content`, `sealed`, HTTP 403). The host changes its own items inside a bypass:

```php
use OiLab\OiLaravelDrive\Services\DriveLocks;

DriveLocks::bypass(fn () => $folders->delete($oldYear));
```

## Linked files

A link shows the stored object of another model — no copy, and deleting the link (trash purge included) never deletes the object.

```php
$files->link($drive, $invoice, $invoice->documentPath(), '20260115_acme_F-2026-0001.pdf', folders: [$january], mimetype: 'application/pdf');
$files->link(...);                     // same source: name, object and folders kept in step
$drives->linksOf($invoice);            // the links of a model
$files->unlink($invoice);              // deletes the links, never the object
```

`DriveFileData` gives `is_link`, `source_type` and `source_id`.

## Notices

A notice is a locked markdown file (`Notice.md`) explaining a folder: what it is for, what to put in it.

```php
$files->notice($drive, $banks, "# Banques\n\nUn dossier par compte : déposez-y vos relevés.");
```

Rewritten only when its text changed. The listing of the folder gives it in `notice` (`file` and `text`) — show it when the folder is empty — and keeps it in `files`, so users can still open it.
