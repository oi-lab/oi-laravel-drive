<?php

namespace OiLab\OiLaravelDrive\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Packs a folder, its subfolders and their files into a ZIP archive, the
 * tree kept as directories. A file filed in several folders of the tree is
 * packed in each. Trashed items are left out.
 */
class DriveArchives
{
    public function __construct(
        protected DriveManager $drives,
        protected DriveFolders $folders,
    ) {}

    /**
     * A download response of the archive, deleted once sent.
     *
     * @throws DriveException
     */
    public function download(Folder $folder): BinaryFileResponse
    {
        return response()
            ->download($this->build($folder), $this->filename($folder), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * Builds the archive in a temporary file and gives its path.
     *
     * @throws DriveException when the zip extension is missing or the folder is too large
     */
    public function build(Folder $folder): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw DriveException::archivesUnavailable();
        }

        $paths = $this->paths($folder);
        $files = $this->files(array_keys($paths));

        $maxBytes = (int) config('oi-laravel-drive.links.archive_max_bytes', 500 * 1024 * 1024);
        $total = $files->sum(fn (File $file): int => (int) $file->filesize * $file->driveFolders->whereIn('id', array_keys($paths))->count());

        if ($maxBytes > 0 && $total > $maxBytes) {
            throw DriveException::archiveTooLarge($maxBytes);
        }

        $workspace = storage_path('framework/cache/drive-archives/'.Str::uuid());
        Filesystem::ensureDirectoryExists($workspace);
        $archivePath = $workspace.'.zip';

        $zip = new ZipArchive;
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        try {
            foreach ($paths as $path) {
                if ($path !== '') {
                    $zip->addEmptyDir(rtrim($path, '/'));
                }
            }

            $taken = [];

            foreach ($files as $index => $file) {
                $copy = $workspace.'/'.$index;
                $source = Storage::disk($file->storage)->readStream($file->filename_disk);

                if ($source === null || $source === false) {
                    continue;
                }

                $target = fopen($copy, 'wb');
                stream_copy_to_stream($source, $target);
                fclose($target);
                fclose($source);

                foreach ($file->driveFolders as $parent) {
                    if (! array_key_exists($parent->getKey(), $paths)) {
                        continue;
                    }

                    $zip->addFile($copy, $this->uniquePath($paths[$parent->getKey()], $file->filename_download, $taken));
                }
            }
        } finally {
            $zip->close();
            Filesystem::deleteDirectory($workspace);
        }

        return $archivePath;
    }

    /**
     * The name of the archive: the folder's name, `.zip`.
     */
    public function filename(Folder $folder): string
    {
        $name = trim(str_replace(['/', '\\'], '-', $folder->name));

        return ($name !== '' ? $name : 'dossier').'.zip';
    }

    /**
     * The path of every folder of the tree inside the archive, keyed by id;
     * the root folder is the root of the archive.
     *
     * @return array<int, string>
     */
    protected function paths(Folder $root): array
    {
        $ids = $this->folders->subtreeIds($root);
        $folders = OiLaravelDrive::folderModel()::query()->whereKey($ids)->get(['id', 'name', 'parent_id'])->keyBy('id');

        $paths = [$root->getKey() => ''];

        foreach ($ids as $id) {
            if ($id === $root->getKey() || ! $folders->has($id)) {
                continue;
            }

            $folder = $folders->get($id);
            $parent = $paths[$folder->parent_id] ?? null;

            if ($parent !== null) {
                $paths[$id] = $parent.$this->clean($folder->name).'/';
            }
        }

        return $paths;
    }

    /**
     * @param  list<int>  $folderIds
     * @return Collection<int, File>
     */
    protected function files(array $folderIds): Collection
    {
        return OiLaravelDrive::fileModel()::query()
            ->has('driveEntry')
            ->whereHas('driveFolders', fn (Builder $query) => $query->whereKey($folderIds))
            ->with('driveFolders')
            ->orderBy('filename_download')
            ->get()
            ->values();
    }

    /**
     * @param  array<string, true>  $taken
     */
    protected function uniquePath(string $directory, string $filename, array &$taken): string
    {
        $filename = $this->clean($filename);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = $directory.$filename;
        $number = 2;

        while (isset($taken[mb_strtolower($path)])) {
            $path = $directory.$name.' ('.$number++.')'.($extension !== '' ? '.'.$extension : '');
        }

        $taken[mb_strtolower($path)] = true;

        return $path;
    }

    protected function clean(string $name): string
    {
        $name = trim(str_replace(['/', '\\', "\0"], '-', $name));

        return in_array($name, ['', '.', '..'], true) ? '_' : $name;
    }
}
