---
title: "Access Rules"
description: "Resolve the current entity, its members and the drive policy"
section: configuration
order: 2
---

# Access Rules

## The resolver

`OiLab\OiLaravelDrive\Contracts\DriveableResolver` answers two questions:

```php
public function current(Authenticatable $user): ?Model;            // the entity the user works in
public function isMember(Authenticatable $user, Model $driveable): bool;
```

The default `ConfigurableDriveableResolver` reads `driveable.current` on the user and the `driveable.members` relation of the entity (`null`: everybody is a member). Bind your own class in `driveable.resolver` for other rules.

## The policy

`DrivePolicy` lets a member of the entity read and write its drives — from that entity only while `driveable.current_only` is on. `create` and `delete` (drives) need `drives.multiple`. Extend it to restrict writing by role:

```php
class DrivePolicy extends \OiLab\OiLaravelDrive\Policies\DrivePolicy
{
    public function update(Authenticatable $user, Drive $drive): bool
    {
        return parent::update($user, $drive) && ! $user->isReadOnly();
    }
}
```

```php
'policy' => App\Policies\DrivePolicy::class,
```
