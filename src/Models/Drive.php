<?php

namespace OiLab\OiLaravelDrive\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OiLab\OiLaravelDrive\Data\DriveData;
use OiLab\OiLaravelDrive\Database\Factories\DriveFactory;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * A space of folders and files belonging to an entity (Team, Organization…).
 *
 * @property int $id
 * @property string $uuid
 * @property string $driveable_type
 * @property int $driveable_id
 * @property string $name
 * @property int|null $user_id Owner of the drive
 * @property array<string, mixed>|null $props
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model $driveable
 */
class Drive extends Model
{
    /** @use HasFactory<DriveFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $table = 'drives';

    /** @var list<string> */
    protected $fillable = [
        'driveable_type',
        'driveable_id',
        'name',
        'user_id',
        'props',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function driveable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(OiLaravelDrive::userModel(), 'user_id');
    }

    /**
     * @return HasMany<DriveEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(OiLaravelDrive::entryModel());
    }

    public function toData(): DriveData
    {
        return DriveData::fromModel($this);
    }

    protected static function newFactory(): DriveFactory
    {
        return DriveFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driveable_id' => 'integer',
            'user_id' => 'integer',
            'props' => 'array',
        ];
    }
}
