<?php

namespace OiLab\OiLaravelDrive\Services;

use Carbon\CarbonInterface;
use finfo;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelAttachments\OiLaravelAttachments;
use OiLab\OiLaravelDrive\Data\DriveFileContentData;
use OiLab\OiLaravelDrive\Events\DriveFileAttached;
use OiLab\OiLaravelDrive\Events\DriveFileCreated;
use OiLab\OiLaravelDrive\Events\DriveFileDeleted;
use OiLab\OiLaravelDrive\Events\DriveFileDetached;
use OiLab\OiLaravelDrive\Events\DriveFileRestored;
use OiLab\OiLaravelDrive\Events\DriveFileTrashed;
use OiLab\OiLaravelDrive\Events\DriveFileUpdated;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\Models\DriveEntry;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use Throwable;

/**
 * Stores the files of a drive and files them in folders. A file filed in no
 * folder is at the root. Its type is read in its content, never in its name.
 *
 * Two kinds of files are kept by the host application: links (`link()`),
 * which show the stored object of another model without copying it and never
 * delete it, and notices (`notice()`), the markdown file explaining a folder.
 * Locked files and sealed folders are refused to users (DriveLocks).
 */
class DriveFiles
{
    /**
     * Types read as text besides `text/*`.
     */
    public const TEXT_MIMES = [
        'application/json',
        'application/xml',
        'application/x-yaml',
        'application/yaml',
        'application/javascript',
        'application/x-httpd-php',
        'application/sql',
        'image/svg+xml',
    ];

    public function __construct(
        protected DriveManager $drives,
        protected DrivePresenter $presenter,
        protected DriveLocks $locks,
    ) {}

    /**
     * Stores an uploaded file.
     *
     * @param  iterable<Folder>  $folders
     * @param  array<string, mixed>|null  $props
     *
     * @throws DriveException
     */
    public function store(
        Drive $drive,
        UploadedFile $upload,
        iterable $folders = [],
        ?Authenticatable $owner = null,
        ?CarbonInterface $createdAt = null,
        ?string $title = null,
        ?string $description = null,
        ?string $filename = null,
        ?array $props = null,
    ): File {
        return $this->storePath(
            $drive,
            (string) $upload->getRealPath(),
            $filename ?? $upload->getClientOriginalName(),
            $folders,
            $owner,
            $createdAt,
            $title,
            $description,
            $props,
        );
    }

    /**
     * Stores a file given by its content.
     *
     * @param  iterable<Folder>  $folders
     * @param  array<string, mixed>|null  $props
     *
     * @throws DriveException
     */
    public function storeContent(
        Drive $drive,
        string $content,
        string $filename,
        iterable $folders = [],
        ?Authenticatable $owner = null,
        ?CarbonInterface $createdAt = null,
        ?string $title = null,
        ?string $description = null,
        ?array $props = null,
    ): File {
        $path = (string) tempnam(sys_get_temp_dir(), 'oi-drive-');
        file_put_contents($path, $content);

        try {
            return $this->storePath($drive, $path, $filename, $folders, $owner, $createdAt, $title, $description, $props);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Changes the name, title, description or props of a file.
     *
     * @param  array{name?: string, title?: string|null, description?: string|null, props?: array<string, mixed>|null}  $attributes
     *
     * @throws DriveException
     */
    public function update(File $file, array $attributes): File
    {
        $this->assertNotTrashed($file);
        $this->locks->assertUnlocked($file);

        if (array_key_exists('name', $attributes)) {
            $file->filename_download = $this->cleanFilename((string) $attributes['name']);
        }

        foreach (['title', 'description', 'props'] as $attribute) {
            if (array_key_exists($attribute, $attributes)) {
                $file->{$attribute} = $attributes[$attribute];
            }
        }

        if ($file->isDirty()) {
            $file->save();
            event(new DriveFileUpdated($file, $this->drives->driveOf($file)));
        }

        return $file;
    }

    /**
     * Files a file in more folders (those it is already in are skipped).
     *
     * @param  iterable<Folder>  $folders
     *
     * @throws DriveException
     */
    public function attach(File $file, iterable $folders): File
    {
        $this->assertNotTrashed($file);
        $drive = $this->drives->driveOf($file);
        $attached = [];

        DB::transaction(function () use ($file, $folders, $drive, &$attached): void {
            $existing = $this->folderIds($file);

            foreach ($folders as $folder) {
                $this->folderService()->assertUsable($folder, $drive);

                if (in_array($folder->getKey(), $existing, true)) {
                    continue;
                }

                $this->locks->assertUnsealed($folder);

                OiLaravelAttachments::attachmentModel()::query()->create([
                    'file_id' => $file->getKey(),
                    'attachable_type' => $folder->getMorphClass(),
                    'attachable_id' => $folder->getKey(),
                    'collection' => OiLaravelDrive::collection(),
                    'sort' => 0,
                ]);

                $existing[] = $folder->getKey();
                $attached[] = $folder;
            }
        });

        $file->unsetRelation('driveFolders');

        foreach ($attached as $folder) {
            event(new DriveFileAttached($file, $folder, $drive));
        }

        return $file;
    }

    /**
     * Takes a file out of folders; out of every folder, it is at the root.
     *
     * @param  iterable<Folder>  $folders
     */
    public function detach(File $file, iterable $folders): File
    {
        $drive = $this->drives->driveOf($file);
        $filedIn = $this->folderIds($file);
        $folders = collect($folders)->filter(fn (Folder $folder): bool => in_array($folder->getKey(), $filedIn, true));
        $folders->each(fn (Folder $folder) => $this->locks->assertUnsealed($folder->loadMissing('driveEntry')));

        foreach ($folders as $folder) {
            $deleted = OiLaravelAttachments::attachmentModel()::query()
                ->where('file_id', $file->getKey())
                ->where('collection', OiLaravelDrive::collection())
                ->where('attachable_type', $folder->getMorphClass())
                ->where('attachable_id', $folder->getKey())
                ->delete();

            if ($deleted > 0) {
                event(new DriveFileDetached($file, $folder, $drive));
            }
        }

        $file->unsetRelation('driveFolders');

        return $file;
    }

    /**
     * Files a file in exactly these folders (none: the root).
     *
     * @param  iterable<Folder>  $folders
     *
     * @throws DriveException
     */
    public function sync(File $file, iterable $folders): File
    {
        $wanted = collect($folders)->keyBy(fn (Folder $folder) => $folder->getKey());
        $current = $file->driveFolders()->get();

        $this->detach($file, $current->reject(fn (Folder $folder) => $wanted->has($folder->getKey())));

        return $this->attach($file, $wanted->values());
    }

    /**
     * Sends a file to the trash (deletes it for good when `$force` or when the
     * trash is off).
     *
     * @throws DriveException
     */
    public function delete(File $file, bool $force = false): void
    {
        $this->locks->assertUnlocked($file);
        $file->driveFolders()->with('driveEntry')->get()->each(fn (Folder $folder) => $this->locks->assertUnsealed($folder));

        if ($force || ! OiLaravelDrive::usesTrash()) {
            $this->destroy($file);

            return;
        }

        $this->assertNotTrashed($file);
        $file->delete();
        $file->driveEntry?->update(['trashed_with_folder_id' => null]);

        event(new DriveFileTrashed($file, $this->drives->driveOf($file)));
    }

    /**
     * @throws DriveException
     */
    public function restore(File $file): File
    {
        if (! $file->trashed()) {
            throw DriveException::notTrashed();
        }

        $file->restore();
        $file->driveEntry?->update(['trashed_with_folder_id' => null]);

        event(new DriveFileRestored($file, $this->drives->driveOf($file)));

        return $file;
    }

    /**
     * Deletes a file for good, its content removed from the disk (never the
     * object of a link, which belongs to its source).
     */
    public function destroy(File $file): void
    {
        $drive = $this->drives->driveOf($file);
        $data = $this->presenter->file($file);
        $isLink = $this->drives->entryOf($file)->isLink();

        DB::transaction(function () use ($file): void {
            OiLaravelAttachments::attachmentModel()::query()->where('file_id', $file->getKey())->delete();
            $file->driveEntry?->delete();
            $file->forceDelete();
        });

        if (! $isLink) {
            rescue(fn () => Storage::disk($file->storage)->delete($file->filename_disk), report: false);
        }

        event(new DriveFileDeleted($data, $drive));
    }

    /**
     * The link showing `$source` in the drive: the stored object at `$path`,
     * never copied, never deleted with the link. Created when missing, then
     * kept in step (name, object, folders: exactly `$folders`) on each call,
     * brought back from the trash. Locked by default.
     *
     * @param  iterable<Folder>  $folders
     *
     * @throws DriveException
     */
    public function link(
        Drive $drive,
        Model $source,
        string $path,
        string $name,
        iterable $folders = [],
        ?string $disk = null,
        ?string $mimetype = null,
        ?int $size = null,
        ?string $title = null,
        ?CarbonInterface $createdAt = null,
        bool $locked = true,
        ?Authenticatable $owner = null,
    ): File {
        return DriveLocks::bypass(function () use ($drive, $source, $path, $name, $folders, $disk, $mimetype, $size, $title, $createdAt, $locked, $owner): File {
            $disk ??= OiLaravelDrive::disk();
            $file = $this->drives->linksOf($source, $drive, withTrashed: true)->first();

            if ($file === null) {
                $this->drives->assertMember($drive, $owner);

                $file = DB::transaction(function () use ($drive, $source, $path, $name, $disk, $mimetype, $size, $title, $createdAt, $locked, $owner): File {
                    $file = OiLaravelDrive::fileModel()::query()->make([
                        'filename_disk' => $path,
                        'filename_download' => $this->cleanFilename($name),
                        'title' => $title,
                        'mimetype' => $mimetype ?? $this->storedMime($disk, $path),
                        'filesize' => $size ?? $this->storedSize($disk, $path),
                        'storage' => $disk,
                    ]);

                    if ($createdAt !== null) {
                        $file->created_at = $createdAt;
                    }

                    $file->save();
                    $this->drives->createEntry($drive, $file, $owner, $createdAt)->fill([
                        'source_type' => $source->getMorphClass(),
                        'source_id' => $source->getKey(),
                        'locked' => $locked,
                    ])->save();

                    return $file;
                });

                event(new DriveFileCreated($file, $drive));
            } else {
                if ($file->trashed()) {
                    $this->restore($file);
                }

                $moved = $file->filename_disk !== $path || $file->storage !== $disk;
                $file->fill([
                    'filename_disk' => $path,
                    'storage' => $disk,
                    'filename_download' => $this->cleanFilename($name),
                    'title' => $title,
                ]);

                if ($moved) {
                    $file->mimetype = $mimetype ?? $this->storedMime($disk, $path);
                    $file->filesize = $size ?? $this->storedSize($disk, $path);
                }

                if ($file->isDirty()) {
                    $file->save();
                    event(new DriveFileUpdated($file, $drive));
                }

                $entry = $this->drives->entryOf($file)->fill(['locked' => $locked]);

                if ($entry->isDirty()) {
                    $entry->save();
                }
            }

            return $this->sync($file, $folders);
        });
    }

    /**
     * Deletes for good the links showing `$source` (in one drive or all);
     * the stored object stays with its source.
     *
     * @return int The number of links deleted
     */
    public function unlink(Model $source, ?Drive $drive = null): int
    {
        $links = $this->drives->linksOf($source, $drive, withTrashed: true);

        DriveLocks::bypass(fn () => $links->each(fn (File $file) => $this->destroy($file)));

        return $links->count();
    }

    /**
     * Writes the notice of a folder (null: the root of the drive), a locked
     * markdown file explaining what the folder is for. Created when missing,
     * rewritten when its text or name changed.
     *
     * @throws DriveException
     */
    public function notice(Drive $drive, ?Folder $folder, string $markdown, string $name = 'Notice.md', ?Authenticatable $owner = null): File
    {
        return DriveLocks::bypass(function () use ($drive, $folder, $markdown, $name, $owner): File {
            $file = $this->drives->noticeOf($drive, $folder);

            if ($file === null) {
                $file = $this->storeContent($drive, $markdown, $name, $folder ? [$folder] : [], $owner);
                $this->drives->entryOf($file)->fill(['role' => DriveEntry::ROLE_NOTICE, 'locked' => true])->save();

                return $file;
            }

            if ($file->md5 !== md5($markdown)) {
                $this->replaceContent($file, $markdown);
            }

            return $this->update($file, ['name' => $name]);
        });
    }

    /**
     * Replaces the stored content of a file of the drive (not of a link).
     *
     * @throws DriveException
     */
    protected function replaceContent(File $file, string $content): void
    {
        if ($this->drives->entryOf($file)->isLink()) {
            throw DriveException::locked();
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'oi-drive-');
        file_put_contents($path, $content);

        try {
            $mime = $this->assertAcceptable($path);
            $old = $file->filename_disk;
            $extension = pathinfo($old, PATHINFO_EXTENSION);
            $directory = OiLaravelDrive::directory().'/'.$this->drives->driveOf($file)->uuid;
            $diskName = Str::uuid().($extension !== '' ? '.'.$extension : '');

            Storage::disk($file->storage)->putFileAs($directory, new HttpFile($path), $diskName);

            $file->fill([
                'filename_disk' => $directory.'/'.$diskName,
                'mimetype' => $mime,
                'filesize' => (int) filesize($path),
                'md5' => md5_file($path) ?: null,
            ])->save();

            rescue(fn () => Storage::disk($file->storage)->delete($old), report: false);
        } finally {
            @unlink($path);
        }

        event(new DriveFileUpdated($file, $this->drives->driveOf($file)));
    }

    protected function storedSize(string $disk, string $path): int
    {
        return (int) rescue(fn () => Storage::disk($disk)->size($path), 0, report: false);
    }

    protected function storedMime(string $disk, string $path): string
    {
        $mime = rescue(fn () => Storage::disk($disk)->mimeType($path), null, report: false);

        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }

    /**
     * A temporary link to download the file: the disk's own temporary URL
     * when it has one (S3…), a signed route of the package otherwise.
     *
     * @return array{url: string, expires_at: CarbonInterface}
     */
    public function downloadUrl(File $file, ?int $minutes = null): array
    {
        $expiresAt = now()->addMinutes($minutes ?? (int) config('oi-laravel-drive.files.temporary_url_minutes', 30));
        $disk = Storage::disk($file->storage);

        if (config('oi-laravel-drive.files.disk_temporary_urls', true)
            && method_exists($disk, 'providesTemporaryUrls') && $disk->providesTemporaryUrls()) {
            $url = rescue(fn () => $disk->temporaryUrl($file->filename_disk, $expiresAt, [
                'ResponseContentDisposition' => 'attachment; filename="'.addslashes($file->filename_download).'"',
            ]), report: false);

            if (is_string($url)) {
                return ['url' => $url, 'expires_at' => $expiresAt];
            }
        }

        return [
            'url' => URL::temporarySignedRoute(config('oi-laravel-drive.signed_routes.name', 'drive.').'files.download', $expiresAt, ['file' => $file->uuid]),
            'expires_at' => $expiresAt,
        ];
    }

    public function isText(File $file): bool
    {
        return str_starts_with($file->mimetype, 'text/') || in_array($file->mimetype, self::TEXT_MIMES, true);
    }

    /**
     * The text of a textual file, cut after `$maxBytes`; null for other types.
     *
     * @return array{text: string, truncated: bool}|null
     */
    public function text(File $file, ?int $maxBytes = null): ?array
    {
        if (! $this->isText($file)) {
            return null;
        }

        $maxBytes ??= (int) config('oi-laravel-drive.files.text_preview_bytes', 100_000);
        $stream = Storage::disk($file->storage)->readStream($file->filename_disk);

        if (! is_resource($stream)) {
            return null;
        }

        try {
            $content = (string) stream_get_contents($stream, $maxBytes + 1);
        } finally {
            fclose($stream);
        }

        $truncated = strlen($content) > $maxBytes;
        $text = $truncated ? mb_strcut($content, 0, $maxBytes, 'UTF-8') : $content;

        return ['text' => mb_scrub($text, 'UTF-8'), 'truncated' => $truncated];
    }

    /**
     * A file with its download link and, for a textual file, its text.
     */
    public function content(File $file, bool $withText = true): DriveFileContentData
    {
        $link = $this->downloadUrl($file);
        $text = $withText ? $this->text($file) : null;

        return new DriveFileContentData(
            file: $this->presenter->file($file),
            download_url: $link['url'],
            download_expires_at: $link['expires_at'],
            text: $text['text'] ?? null,
            text_truncated: $text['truncated'] ?? false,
        );
    }

    /**
     * Checks a file before it is stored: not empty, not too large, of an
     * accepted type. Returns the type read in the content.
     *
     * @throws DriveException
     */
    public function assertAcceptable(string $path, ?int $maxBytes = null): string
    {
        $size = (int) @filesize($path);
        $maxBytes ??= (int) config('oi-laravel-drive.files.max_bytes');

        if ($size === 0) {
            throw DriveException::emptyFile();
        }

        if ($maxBytes > 0 && $size > $maxBytes) {
            throw DriveException::fileTooLarge($maxBytes);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        $allowed = config('oi-laravel-drive.files.allowed_mimes');

        if (is_array($allowed) && ! in_array($mime, $allowed, true)) {
            throw DriveException::mimeRefused($mime);
        }

        return $mime;
    }

    /**
     * @param  iterable<Folder>  $folders
     * @param  array<string, mixed>|null  $props
     *
     * @throws DriveException
     */
    protected function storePath(
        Drive $drive,
        string $path,
        string $filename,
        iterable $folders,
        ?Authenticatable $owner,
        ?CarbonInterface $createdAt,
        ?string $title,
        ?string $description,
        ?array $props,
    ): File {
        $mime = $this->assertAcceptable($path);
        $folders = collect($folders)->values();
        $folders->each(function (Folder $folder) use ($drive): void {
            $this->folderService()->assertUsable($folder, $drive);
            $this->locks->assertUnsealed($folder->loadMissing('driveEntry'));
        });

        $owner ??= Auth::user();
        $this->drives->assertMember($drive, $owner);

        $filename = $this->cleanFilename($filename);
        $extension = Str::lower((string) preg_replace('/[^A-Za-z0-9]/', '', pathinfo($filename, PATHINFO_EXTENSION)));
        $directory = OiLaravelDrive::directory().'/'.$drive->uuid;
        $diskName = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $disk = OiLaravelDrive::disk();

        Storage::disk($disk)->putFileAs($directory, new HttpFile($path), $diskName);

        [$width, $height] = str_starts_with($mime, 'image/') ? (@getimagesize($path) ?: [null, null]) : [null, null];

        try {
            $file = DB::transaction(function () use ($drive, $path, $filename, $mime, $directory, $diskName, $disk, $width, $height, $owner, $createdAt, $title, $description, $props): File {
                $file = OiLaravelDrive::fileModel()::query()->make([
                    'filename_disk' => $directory.'/'.$diskName,
                    'filename_download' => $filename,
                    'title' => $title,
                    'description' => $description,
                    'mimetype' => $mime,
                    'filesize' => (int) filesize($path),
                    'width' => $width,
                    'height' => $height,
                    'storage' => $disk,
                    'md5' => md5_file($path) ?: null,
                    'props' => $props,
                ]);

                if (! Auth::check() && $owner !== null) {
                    $file->created_by = $owner->getAuthIdentifier();
                }

                if ($createdAt !== null) {
                    $file->created_at = $createdAt;
                }

                $file->save();
                $this->drives->createEntry($drive, $file, $owner, $createdAt);

                return $file;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($directory.'/'.$diskName);

            throw $exception;
        }

        event(new DriveFileCreated($file, $drive));

        if ($folders->isNotEmpty()) {
            $this->attach($file, $folders);
        }

        return $file;
    }

    /**
     * @return list<int>
     */
    protected function folderIds(File $file): array
    {
        return OiLaravelAttachments::attachmentModel()::query()
            ->where('file_id', $file->getKey())
            ->where('collection', OiLaravelDrive::collection())
            ->where('attachable_type', (new (OiLaravelDrive::folderModel()))->getMorphClass())
            ->pluck('attachable_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @throws DriveException
     */
    protected function assertNotTrashed(File $file): void
    {
        if ($file->trashed()) {
            throw DriveException::trashed();
        }
    }

    protected function cleanFilename(string $filename): string
    {
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', basename(str_replace('\\', '/', $filename))));

        return mb_substr($filename !== '' ? $filename : 'fichier', 0, 255);
    }

    protected function folderService(): DriveFolders
    {
        return app(DriveFolders::class);
    }
}
