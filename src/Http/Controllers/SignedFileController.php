<?php

namespace OiLab\OiLaravelDrive\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use OiLab\OiLaravelDrive\Services\DriveManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download of a file through a temporary signed link (DriveFiles::downloadUrl),
 * for disks without temporary URLs of their own: the signature is the whole
 * credential.
 */
class SignedFileController
{
    public function download(string $file, DriveManager $drives): StreamedResponse
    {
        $model = $drives->findFile($file);

        return Storage::disk($model->storage)->download($model->filename_disk, $model->filename_download, [
            'Content-Type' => $model->mimetype,
        ]);
    }
}
