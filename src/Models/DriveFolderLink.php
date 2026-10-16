<?php

namespace OiLab\OiLaravelDrive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * The public link of a folder: anybody holding its token reads the folder,
 * its subfolders and its files, after its password when it has one, until it
 * expires.
 *
 * @property int $id
 * @property int $folder_id
 * @property string $token
 * @property string|null $password Hashed
 * @property Carbon|null $expires_at
 * @property int $views_count
 * @property Carbon|null $last_viewed_at
 * @property int|null $user_id User who opened the link
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Folder|null $folder
 * @property-read Model|null $user
 */
class DriveFolderLink extends Model
{
    protected $table = 'drive_folder_links';

    /** @var list<string> */
    protected $fillable = [
        'folder_id',
        'token',
        'password',
        'expires_at',
        'user_id',
    ];

    /** @var list<string> */
    protected $hidden = ['password'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'views_count' => 0,
    ];

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(OiLaravelDrive::folderModel());
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(OiLaravelDrive::userModel(), 'user_id');
    }

    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * A key to keep in the visitor's session once the password is given: it
     * changes with the token and the password, so regenerating the link or
     * changing its password locks every visitor out again.
     */
    public function unlockKey(): string
    {
        return hash_hmac('sha256', $this->token.'|'.($this->password ?? ''), (string) config('app.key'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'folder_id' => 'integer',
            'password' => 'hashed',
            'expires_at' => 'datetime',
            'views_count' => 'integer',
            'last_viewed_at' => 'datetime',
            'user_id' => 'integer',
        ];
    }
}
