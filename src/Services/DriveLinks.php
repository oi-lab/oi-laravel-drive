<?php

namespace OiLab\OiLaravelDrive\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Data\DriveFolderLinkData;
use OiLab\OiLaravelDrive\Data\UserSummaryData;
use OiLab\OiLaravelDrive\Events\DriveFolderLinkRevoked;
use OiLab\OiLaravelDrive\Events\DriveFolderLinkSaved;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Models\DriveFolderLink;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * The public link of a folder: one per folder, read-only, optionally behind
 * a password and an expiry date. The token is the address; regenerating it
 * kills the previous address. The package keeps the link, the host serves
 * the page (`links.route`).
 */
class DriveLinks
{
    public function __construct(
        protected DriveManager $drives,
        protected DriveFolders $folders,
    ) {}

    public function linkOf(Folder $folder): ?DriveFolderLink
    {
        return OiLaravelDrive::linkModel()::query()->where('folder_id', $folder->getKey())->first();
    }

    /**
     * Opens the public link of a folder, or gives back the one it has.
     *
     * @throws DriveException when the folder is in the trash
     */
    public function open(Folder $folder, ?Authenticatable $by = null): DriveFolderLink
    {
        $this->folders->assertUsable($folder, $this->drives->driveOf($folder));

        $link = $this->linkOf($folder);

        if ($link !== null) {
            return $link;
        }

        $link = OiLaravelDrive::linkModel()::query()->create([
            'folder_id' => $folder->getKey(),
            'token' => $this->newToken(),
            'user_id' => ($by ?? Auth::user())?->getAuthIdentifier(),
        ]);

        event(new DriveFolderLinkSaved($link));

        return $link;
    }

    /**
     * Sets the password of the link (null: no password).
     *
     * @throws DriveException when the password is too short
     */
    public function setPassword(DriveFolderLink $link, ?string $password): DriveFolderLink
    {
        $minLength = (int) config('oi-laravel-drive.links.password_min_length', 6);

        if ($password !== null && mb_strlen($password) < $minLength) {
            throw DriveException::passwordTooShort($minLength);
        }

        $link->password = $password;

        return $this->save($link);
    }

    /**
     * Sets the date after which the link stops working (null: never).
     */
    public function setExpiry(DriveFolderLink $link, ?CarbonInterface $expiresAt): DriveFolderLink
    {
        $link->expires_at = $expiresAt;

        return $this->save($link);
    }

    /**
     * Gives the link a new address: the previous one stops working.
     */
    public function regenerate(DriveFolderLink $link): DriveFolderLink
    {
        $link->token = $this->newToken();

        return $this->save($link);
    }

    /**
     * Closes the public link of a folder.
     */
    public function revoke(Folder $folder): bool
    {
        $deleted = OiLaravelDrive::linkModel()::query()->where('folder_id', $folder->getKey())->delete() > 0;

        if ($deleted) {
            event(new DriveFolderLinkRevoked($folder));
        }

        return $deleted;
    }

    /**
     * The link of a token, whose folder is still in a drive and out of the
     * trash — expired or not: check isExpired() to tell a visitor why it
     * no longer works.
     */
    public function find(string $token): ?DriveFolderLink
    {
        if ($token === '' || strlen($token) > 64) {
            return null;
        }

        $link = OiLaravelDrive::linkModel()::query()->where('token', $token)->first();

        if ($link === null || $link->folder === null || $link->folder->driveEntry === null) {
            return null;
        }

        return $link;
    }

    public function checkPassword(DriveFolderLink $link, string $password): bool
    {
        return ! $link->hasPassword() || Hash::check($password, $link->password);
    }

    /**
     * Counts a visit of the link.
     */
    public function recordView(DriveFolderLink $link): void
    {
        $link->newQuery()->whereKey($link->getKey())->increment('views_count', 1, ['last_viewed_at' => now()]);

        $link->views_count++;
        $link->last_viewed_at = now();
    }

    /**
     * The URL of the link, through the host route (`links.route`).
     */
    public function url(DriveFolderLink $link): ?string
    {
        $route = config('oi-laravel-drive.links.route');

        return $route && Route::has($route) ? route($route, ['token' => $link->token]) : null;
    }

    public function present(DriveFolderLink $link): DriveFolderLinkData
    {
        $link->loadMissing(['folder', 'user']);

        return new DriveFolderLinkData(
            folder_id: (string) $link->folder?->uuid,
            token: $link->token,
            url: $this->url($link),
            has_password: $link->hasPassword(),
            expires_at: $link->expires_at,
            is_expired: $link->isExpired(),
            views_count: $link->views_count,
            last_viewed_at: $link->last_viewed_at,
            created_by: $link->user ? UserSummaryData::fromUser($link->user) : null,
            created_at: $link->created_at,
        );
    }

    protected function save(DriveFolderLink $link): DriveFolderLink
    {
        if ($link->isDirty()) {
            $link->save();
            event(new DriveFolderLinkSaved($link));
        }

        return $link;
    }

    protected function newToken(): string
    {
        do {
            $token = Str::random(40);
        } while (OiLaravelDrive::linkModel()::query()->where('token', $token)->exists());

        return $token;
    }
}
