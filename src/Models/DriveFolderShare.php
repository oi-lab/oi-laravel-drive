<?php

namespace OiLab\OiLaravelDrive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * A folder of a drive shared, read-only, with another entity (a Team, an
 * Organization…): its members read the folder, its subfolders and its files.
 *
 * @property int $id
 * @property int $folder_id
 * @property string $shareable_type
 * @property int $shareable_id
 * @property int|null $user_id User who shared the folder
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Folder $folder
 * @property-read Model|null $shareable
 * @property-read Model|null $user
 */
class DriveFolderShare extends Model
{
    protected $table = 'drive_folder_shares';

    /** @var list<string> */
    protected $fillable = [
        'folder_id',
        'shareable_type',
        'shareable_id',
        'user_id',
    ];

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(OiLaravelDrive::folderModel());
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function shareable(): MorphTo
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'folder_id' => 'integer',
            'shareable_id' => 'integer',
            'user_id' => 'integer',
        ];
    }
}
