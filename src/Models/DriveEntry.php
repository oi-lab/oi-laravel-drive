<?php

namespace OiLab\OiLaravelDrive\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use OiLab\OiLaravelDrive\Enums\DriveEntryKind;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * The place of a folder or a file in a drive, with its owner and its date of
 * creation (both settable, e.g. on import).
 *
 * The host application may also give it a stable `key` (unique in the drive,
 * to find the items it generates whatever their name), a `role` (`notice`:
 * the notice of its folder), a `source` (a linked file: it shows the stored
 * object of another model, never deleted with it), and locks: a `locked`
 * item cannot be renamed, moved or deleted, a `sealed` folder takes no
 * folder or file from users (DriveLocks).
 *
 * @property int $id
 * @property int $drive_id
 * @property string $entry_type
 * @property int $entry_id
 * @property DriveEntryKind $kind
 * @property int|null $user_id Owner of the entry
 * @property int|null $trashed_with_folder_id Folder whose deletion sent this entry to the trash
 * @property string|null $key Stable key given by the host application, unique in the drive
 * @property string|null $role `notice` for the notice of a folder
 * @property string|null $source_type Model shown by a linked file
 * @property int|string|null $source_id
 * @property bool $locked
 * @property bool $sealed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Drive $drive
 * @property-read Model|null $entry
 * @property-read Model|null $user
 * @property-read Model|null $source
 */
class DriveEntry extends Model
{
    protected $table = 'drive_entries';

    /** @var list<string> */
    protected $fillable = [
        'drive_id',
        'entry_type',
        'entry_id',
        'kind',
        'user_id',
        'trashed_with_folder_id',
        'key',
        'role',
        'source_type',
        'source_id',
        'locked',
        'sealed',
        'created_at',
    ];

    public const ROLE_NOTICE = 'notice';

    /**
     * @return BelongsTo<Drive, $this>
     */
    public function drive(): BelongsTo
    {
        return $this->belongsTo(OiLaravelDrive::driveModel());
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entry(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * The model a linked file shows.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A linked file: its stored object belongs to its source.
     */
    public function isLink(): bool
    {
        return $this->source_type !== null;
    }

    public function isNotice(): bool
    {
        return $this->role === self::ROLE_NOTICE;
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(OiLaravelDrive::userModel(), 'user_id');
    }

    /**
     * @param  Builder<DriveEntry>  $query
     * @return Builder<DriveEntry>
     */
    public function scopeOfKind(Builder $query, DriveEntryKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'drive_id' => 'integer',
            'entry_id' => 'integer',
            'kind' => DriveEntryKind::class,
            'user_id' => 'integer',
            'trashed_with_folder_id' => 'integer',
            'locked' => 'boolean',
            'sealed' => 'boolean',
        ];
    }
}
