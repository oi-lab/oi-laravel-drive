<?php

namespace OiLab\OiLaravelDrive\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OiLab\OiLaravelDrive\Enums\DriveUploadStatus;
use OiLab\OiLaravelDrive\OiLaravelDrive;

/**
 * An upload opened by an assistant: the file arrives later, through a
 * one-time signed link, and becomes a drive file with the attributes given
 * when the upload was opened.
 *
 * @property string $id
 * @property int $drive_id
 * @property int|null $user_id
 * @property string $filename
 * @property string|null $mime
 * @property int|null $size
 * @property DriveUploadStatus $status
 * @property array{folder_ids?: list<int>, title?: string|null, description?: string|null}|null $payload
 * @property int|null $file_id
 * @property string|null $error
 * @property Carbon $expires_at
 * @property-read Drive $drive
 * @property-read Model|null $user
 * @property-read Model|null $file
 */
class DriveUpload extends Model
{
    use HasUuids;

    protected $table = 'drive_uploads';

    /** @var list<string> */
    protected $fillable = [
        'drive_id',
        'user_id',
        'filename',
        'mime',
        'size',
        'status',
        'payload',
        'file_id',
        'error',
        'expires_at',
    ];

    public function isOpen(): bool
    {
        return $this->status === DriveUploadStatus::Awaiting && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Drive, $this>
     */
    public function drive(): BelongsTo
    {
        return $this->belongsTo(OiLaravelDrive::driveModel());
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(OiLaravelDrive::userModel(), 'user_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(OiLaravelDrive::fileModel(), 'file_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'drive_id' => 'integer',
            'user_id' => 'integer',
            'size' => 'integer',
            'file_id' => 'integer',
            'status' => DriveUploadStatus::class,
            'payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
