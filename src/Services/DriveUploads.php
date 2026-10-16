<?php

namespace OiLab\OiLaravelDrive\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\URL;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Data\DriveUploadData;
use OiLab\OiLaravelDrive\Enums\DriveUploadStatus;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\Models\DriveUpload;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use Throwable;

/**
 * Uploads by link, for assistants: MCP carries binaries badly, so a tool
 * opens an upload and the file arrives through a one-time signed URL (a PUT
 * of the raw body, or a drag-and-drop page given to the user). The file then
 * becomes a drive file with the attributes chosen when the upload was opened.
 */
class DriveUploads
{
    public function __construct(
        protected DriveManager $drives,
        protected DriveFiles $files,
        protected DrivePresenter $presenter,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('oi-laravel-drive.uploads.enabled', true);
    }

    /**
     * Opens an upload, valid `uploads.ttl_minutes` minutes.
     *
     * @param  iterable<Folder>  $folders
     *
     * @throws DriveException
     */
    public function open(
        Drive $drive,
        Authenticatable $user,
        string $filename,
        iterable $folders = [],
        ?string $title = null,
        ?string $description = null,
        ?Authenticatable $owner = null,
    ): DriveUpload {
        if (! $this->enabled()) {
            throw DriveException::uploadsDisabled();
        }

        $folders = collect($folders);
        $folders->each(fn (Folder $folder) => app(DriveFolders::class)->assertUsable($folder, $drive));
        $this->drives->assertMember($drive, $owner);

        return OiLaravelDrive::uploadModel()::query()->create([
            'drive_id' => $drive->getKey(),
            'user_id' => $user->getAuthIdentifier(),
            'filename' => mb_substr(trim($filename), 0, 255),
            'status' => DriveUploadStatus::Awaiting,
            'payload' => [
                'folder_ids' => $folders->map(fn (Folder $folder) => $folder->getKey())->values()->all(),
                'title' => $title,
                'description' => $description,
                'owner_id' => $owner?->getAuthIdentifier() ?? $user->getAuthIdentifier(),
            ],
            'expires_at' => now()->addMinutes((int) config('oi-laravel-drive.uploads.ttl_minutes', 10)),
        ]);
    }

    /**
     * Signed address of the raw-body PUT, valid until the upload expires.
     */
    public function uploadUrl(DriveUpload $upload): string
    {
        return URL::temporarySignedRoute($this->routeName('uploads.put'), $upload->expires_at, ['upload' => $upload->getKey()]);
    }

    /**
     * Signed drag-and-drop page, for whoever cannot send a PUT.
     */
    public function pageUrl(DriveUpload $upload): string
    {
        return URL::temporarySignedRoute($this->routeName('uploads.page'), $upload->expires_at, ['upload' => $upload->getKey()]);
    }

    /**
     * Receives the file of an open upload and turns it into a drive file.
     *
     * @throws DriveException
     */
    public function receive(DriveUpload $upload, string $content): DriveUpload
    {
        if (! $this->enabled()) {
            throw DriveException::uploadsDisabled();
        }

        if (! $upload->isOpen()) {
            throw DriveException::uploadClosed();
        }

        $claimed = OiLaravelDrive::uploadModel()::query()
            ->whereKey($upload->getKey())
            ->where('status', DriveUploadStatus::Awaiting)
            ->update(['status' => DriveUploadStatus::Completed, 'size' => strlen($content), 'updated_at' => now()]);

        if ($claimed !== 1) {
            throw DriveException::uploadClosed();
        }

        $upload->refresh();

        try {
            $file = $this->files->storeContent(
                $upload->drive,
                $content,
                $upload->filename,
                $this->usableFolders($upload),
                $this->owner($upload),
                title: $upload->payload['title'] ?? null,
                description: $upload->payload['description'] ?? null,
            );
        } catch (Throwable $exception) {
            $upload->update(['status' => DriveUploadStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 500)]);

            throw $exception;
        }

        $upload->update(['file_id' => $file->getKey(), 'mime' => $file->mimetype]);

        return $upload->setRelation('file', $file);
    }

    /**
     * Marks as expired the uploads left without a file.
     */
    public function expireStale(): int
    {
        return OiLaravelDrive::uploadModel()::query()
            ->where('status', DriveUploadStatus::Awaiting)
            ->where('expires_at', '<=', now())
            ->update(['status' => DriveUploadStatus::Expired, 'updated_at' => now()]);
    }

    public function data(DriveUpload $upload): DriveUploadData
    {
        $open = $upload->isOpen();
        $file = $upload->file_id ? OiLaravelDrive::fileModel()::query()->withTrashed()->find($upload->file_id) : null;

        return new DriveUploadData(
            id: $upload->getKey(),
            status: $upload->status === DriveUploadStatus::Awaiting && ! $open ? DriveUploadStatus::Expired->value : $upload->status->value,
            filename: $upload->filename,
            expires_at: $upload->expires_at,
            max_bytes: (int) config('oi-laravel-drive.files.max_bytes'),
            upload_url: $open ? $this->uploadUrl($upload) : null,
            page_url: $open ? $this->pageUrl($upload) : null,
            file: $file instanceof File ? $this->presenter->file($file) : null,
            error: $upload->error,
        );
    }

    /**
     * The folders chosen at the opening that still exist out of the trash.
     *
     * @return list<Folder>
     */
    protected function usableFolders(DriveUpload $upload): array
    {
        $ids = $upload->payload['folder_ids'] ?? [];

        if ($ids === []) {
            return [];
        }

        return $this->drives->folderQuery($upload->drive)->whereKey($ids)->get()->all();
    }

    protected function owner(DriveUpload $upload): ?Authenticatable
    {
        $ownerId = $upload->payload['owner_id'] ?? $upload->user_id;

        return $ownerId ? OiLaravelDrive::userModel()::query()->find($ownerId) : null;
    }

    protected function routeName(string $name): string
    {
        return config('oi-laravel-drive.signed_routes.name', 'drive.').$name;
    }
}
