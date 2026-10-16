---
title: "Installation"
description: "Install, migrate and wire the package into a host application"
section: getting-started
order: 2
---

# Installation

## Require the package

```bash
composer require oi-lab/oi-laravel-drive
```

It pulls `oi-lab/oi-laravel-attachments`, whose `folders`, `files` and `attachments` tables it uses.

## Publish and migrate

```bash
php artisan vendor:publish --tag=oi-laravel-drive-config
php artisan migrate
```

Three tables are added: `drives`, `drive_entries`, `drive_uploads`.

## Tell the drive who works where

```php
// config/oi-laravel-drive.php
'driveable' => [
    'current' => 'currentTeam',   // relation or attribute of the user
    'members' => 'members',       // relation of the entity listing its users
],
```

For an application where users work in organizations: `current` = `currentOrganization`, `members` = `users`.
See [Access rules](../configuration/access.md) for custom rules.

## First drive

```php
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DriveFolders;

$drive = app(DriveManager::class)->driveFor($team);
app(DriveFolders::class)->create($drive, 'Contrats');
```

If your application enforces a morph map, add entries for `Drive`, the folder and file models, and the entity.
