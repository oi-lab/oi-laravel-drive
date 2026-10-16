<?php

namespace OiLab\OiLaravelDrive\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Models\DriveUpload;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use OiLab\OiLaravelDrive\Services\DriveUploads;

/**
 * The file of an upload opened by an assistant, sent to a one-time signed
 * link: a PUT of the raw body, or the drag-and-drop page.
 */
class UploadController
{
    public function __construct(protected DriveUploads $uploads) {}

    public function store(Request $request, string $upload): JsonResponse
    {
        try {
            $model = $this->uploads->receive($this->find($upload), (string) $request->getContent());
        } catch (DriveException $exception) {
            return new JsonResponse(['error' => $exception->rule, 'message' => $exception->getMessage()], $exception->status);
        }

        return new JsonResponse($this->uploads->data($model)->toArray(), 201);
    }

    public function page(string $upload): View
    {
        $model = $this->find($upload);

        return view('oi-laravel-drive::upload', [
            'upload' => $model,
            'uploadUrl' => $model->isOpen() ? $this->uploads->uploadUrl($model) : null,
            'maxMegabytes' => max(1, intdiv((int) config('oi-laravel-drive.files.max_bytes'), 1_048_576)),
        ]);
    }

    protected function find(string $id): DriveUpload
    {
        abort_unless($this->uploads->enabled(), 404);

        return OiLaravelDrive::uploadModel()::query()->findOrFail($id);
    }
}
