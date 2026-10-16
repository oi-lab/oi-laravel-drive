---
title: "Sharing"
description: "Share a folder with other entities, open a public link with a password, download a folder as a ZIP"
section: usage
order: 5
---

# Sharing

A folder can be opened, **read-only**, in two ways that add up: shared with other entities, and through a public link. Either way, the folder, its subfolders and the files filed in them are readable; nothing can be written. A folder in the trash is open to nobody until it is restored.

## With other entities

```php
use OiLab\OiLaravelDrive\Services\DriveShares;

$shares = app(DriveShares::class);

$shares->share($folder, $partnerTeam);           // DriveFolderShare
$shares->unshare($folder, $partnerTeam);
$shares->sync($folder, [$teamA, $teamB]);        // exactly these

$shares->sharesOf($folder);                      // list<DriveShareData>
$shares->sharedWith($user);                      // the topmost folders shared with the user's current entity
$shares->reaches($user, $folderOrFile);          // read access through a share
$shares->shareRoot($user, $folderOrFile);        // the shared folder granting it
$shares->contents($root, $folder);               // a listing whose breadcrumbs start at $root
```

A folder is shared with entities of `sharing.model` — by default the class of the entity owning the drive (a Team drive is shared with other teams). Sharing with the owning entity itself is refused. A member of the entity reads the folder from that entity only while `driveable.current_only` is on, as for drives.

`findShareable($user, $folder, $keyOrName)` finds an entity the user may share with, by key or by its `sharing.name_attribute`; `findShared($folder, $keyOrName)` one the folder is shared with.

## Who may be shared with

`share()` and `sync()` only accept the entities `targets($user, $folder)` gives: the query of the bound `OiLab\OiLaravelDrive\Contracts\ShareTargets` (`sharing.targets`), the entity owning the drive left out. The default `AnyShareTarget` allows every entity of the class — fine for a single tenant. In a multi-tenant application, bind your own:

```php
class TeamShareTargets implements ShareTargets
{
    public function query(Authenticatable $user, Folder $folder, string $model): Builder
    {
        return $model::query()->whereIn('id', $user->teams()->pluck('teams.id'));
    }
}
```

The check is skipped when nobody is signed in and no `$by` user is given (a command, a job).

The package does not authorize the share itself: check `Gate::authorize('update', $drive)` (or stricter) before sharing, and `reaches()` before showing a shared item to a non-member.

## Public link

```php
use OiLab\OiLaravelDrive\Services\DriveLinks;

$links = app(DriveLinks::class);

$link = $links->open($folder);                   // one per folder, 40-character token
$links->setPassword($link, 'secret');            // hashed; null removes it
$links->setExpiry($link, now()->addMonth());     // null: never
$links->regenerate($link);                       // new address, the old one dies
$links->revoke($folder);

$link = $links->find($token);                    // null when unknown, or the folder is trashed
$link->isExpired(); $link->hasPassword();
$links->checkPassword($link, $input);
$links->recordView($link);                       // views_count, last_viewed_at
$links->present($link);                          // DriveFolderLinkData (never the password)
```

The package keeps the link; the host serves the page. Name the host route in `links.route` (it takes a `token` parameter) and `DriveFolderLinkData::$url` gives the address.

Keep `$link->unlockKey()` in the visitor's session once the password is given: it changes with the token and the password, so regenerating the link or changing its password locks every visitor out again. Throttle the password form.

## ZIP download

```php
use OiLab\OiLaravelDrive\Services\DriveArchives;

return app(DriveArchives::class)->download($folder);  // BinaryFileResponse, deleted after sending
```

The tree is kept as directories, a file filed in several folders of the tree is packed in each, trashed items are left out. A folder over `links.archive_max_bytes` (500 MB) is refused with a `DriveException` (`archive_too_large`). Needs the `zip` PHP extension.

## Listings

`DriveFolderData` carries `shares_count` and `has_link`, to mark shared folders in a listing.

## Events

`DriveFolderShared`, `DriveFolderUnshared` (folder, entity), `DriveFolderLinkSaved` (link), `DriveFolderLinkRevoked` (folder).
