---
title: "Files"
description: "Store files, file them in folders, read and download them"
section: usage
order: 3
---

# Files

```php
use OiLab\OiLaravelDrive\Services\DriveFiles;

$files = app(DriveFiles::class);

$file = $files->store($drive, $request->file('file'), folders: [$contracts, $acme], title: 'Contrat');
$note = $files->storeContent($drive, "# Compte rendu", 'cr.md', owner: $user, createdAt: $date);
```

The type is read in the content (`finfo`), never in the name; `files.max_bytes` and `files.allowed_mimes` are enforced.

## Folders of a file

```php
$files->attach($file, [$archive]);  // one folder more
$files->detach($file, [$acme]);     // one folder less
$files->sync($file, []);            // exactly these folders: none = the root
```

## Reading

```php
$files->downloadUrl($file);  // ['url' => …, 'expires_at' => …] — disk temporary URL or signed route
$files->text($note);         // ['text' => …, 'truncated' => false], null for a binary file
$files->content($file);      // DriveFileContentData: file, link, text
```

## Owner and date

```php
$drives->setOwner($file, $colleague); // a member of the drive's entity
$drives->setCreatedAt($file, Carbon::parse('2020-01-01'));
```
