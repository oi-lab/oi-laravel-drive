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
 * @property int $id
 * @property int $drive_id
 * @property string $entry_type
 * @property int $entry_id
 * @property DriveEntryKind $kind
 * @property int|null $user_id Owner of the entry
 * @property int|null $trashed_with_folder_id Folder whose deletion sent this entry to the trash
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Drive $drive
 * @property-read Model|null $entry
 * @property-read Model|null $user
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
        'created_at',
    ];

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
        ];
    }
}
