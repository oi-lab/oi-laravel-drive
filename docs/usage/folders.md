---
title: "Folders"
description: "Create, rename, move, delete and restore folders"
section: usage
order: 2
---

# Folders

```php
use OiLab\OiLaravelDrive\Services\DriveFolders;

$folders = app(DriveFolders::class);

$clients = $folders->create($drive, 'Clients');
$acme = $folders->create($drive, 'Acme', parent: $clients, owner: $colleague, createdAt: now()->subYear());

$folders->update($acme, name: 'Acme SAS', props: ['color' => 'blue']);
$folders->move($acme, null); // to the root
```

A folder cannot be moved into itself or one of its subfolders, nor into another drive.

## Unique names

Names are free by default. With `folders.unique_names`, two folders of the same parent cannot share a name (case-insensitive); a restored folder whose name is taken gets a number (`Acme (2)`).

## Deleting

```php
$folders->delete($clients);                   // the tree goes to the trash, the files stay
$folders->delete($clients, withFiles: true);  // the files filed only in this tree go too
$folders->delete($clients, force: true);      // for good
```

A file also filed in a folder outside the deleted tree is never deleted: it is only taken out of the deleted folders. Without `withFiles`, files filed nowhere else show at the root.

## Listing

```php
$listing = app(DriveManager::class)->contents($drive, $clients); // null: the root
$listing->folders; $listing->files; $listing->breadcrumbs;
```
