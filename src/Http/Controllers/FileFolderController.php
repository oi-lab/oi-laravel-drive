<?php

namespace OiLab\OiLaravelDrive\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OiLab\OiLaravelDrive\Data\DriveFileData;
use OiLab\OiLaravelDrive\Http\Requests\AttachFoldersRequest;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DrivePresenter;

/**
 * Files a file in more folders, or takes it out of one.
 */
class FileFolderController extends Controller
{
    public function __construct(
        DriveManager $drives,
        protected DriveFiles $files,
        protected DrivePresenter $presenter,
    ) {
        parent::__construct($drives);
    }

    public function store(AttachFoldersRequest $request, string $file): JsonResponse
    {
        $model = $this->file($request, $file, 'update');

        return new JsonResponse($this->presenter->file($this->files->attach($model, $this->folders($request, $request->validated('folder_ids')))));
    }

    public function destroy(Request $request, string $file, string $folder): DriveFileData
    {
        $model = $this->file($request, $file, 'update');

        return $this->presenter->file($this->files->detach($model, [$this->folder($request, $folder, 'update', withTrashed: true)]));
    }
}
