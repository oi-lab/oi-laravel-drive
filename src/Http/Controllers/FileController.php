<?php

namespace OiLab\OiLaravelDrive\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use OiLab\OiLaravelDrive\Data\DriveFileContentData;
use OiLab\OiLaravelDrive\Data\DriveFileData;
use OiLab\OiLaravelDrive\Http\Requests\StoreFileRequest;
use OiLab\OiLaravelDrive\Http\Requests\UpdateFileRequest;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DrivePresenter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FileController extends Controller
{
    public function __construct(
        DriveManager $drives,
        protected DriveFiles $files,
        protected DrivePresenter $presenter,
    ) {
        parent::__construct($drives);
    }

    /**
     * Uploads a file (`file`), filed in `folder_ids` (none: the root).
     */
    public function store(StoreFileRequest $request): SymfonyResponse
    {
        $folders = $this->folders($request, $request->validated('folder_ids'));
        $drive = $folders !== [] ? $this->drives->driveOf($folders[0]) : $this->drive($request, 'update');

        $file = $this->files->store(
            $drive,
            $request->file('file'),
            $folders,
            $this->owner($request->validated('owner_id')),
            $request->validated('created_at') ? Carbon::parse($request->validated('created_at')) : null,
            $request->validated('title'),
            $request->validated('description'),
            $request->validated('name'),
            $request->validated('props'),
        );

        return $this->presenter->file($file)->toResponse($request)->setStatusCode(201);
    }

    public function show(Request $request, string $file): DriveFileData
    {
        return $this->presenter->file($this->file($request, $file, withTrashed: true));
    }

    /**
     * The file with a temporary download link and, for a textual file, its text.
     */
    public function content(Request $request, string $file): DriveFileContentData
    {
        return $this->files->content($this->file($request, $file), withText: $request->boolean('text', true));
    }

    public function download(Request $request, string $file): RedirectResponse
    {
        return redirect()->away($this->files->downloadUrl($this->file($request, $file))['url']);
    }

    /**
     * Renames, describes, files (`folder_ids`: exactly these folders) or gives
     * a new owner to a file.
     */
    public function update(UpdateFileRequest $request, string $file): DriveFileData
    {
        $model = $this->file($request, $file, 'update');

        $this->files->update($model, array_intersect_key($request->validated(), array_flip(['name', 'title', 'description', 'props'])));

        if ($request->exists('folder_ids')) {
            $this->files->sync($model, $this->folders($request, $request->validated('folder_ids')));
        }

        if ($request->exists('owner_id')) {
            $this->drives->setOwner($model, $this->owner($request->validated('owner_id')));
        }

        if ($request->validated('created_at')) {
            $this->drives->setCreatedAt($model, Carbon::parse($request->validated('created_at')));
        }

        return $this->presenter->file($model->refresh());
    }

    /**
     * `force`: skip the trash.
     */
    public function destroy(Request $request, string $file): Response
    {
        $model = $this->file($request, $file, 'update', withTrashed: $request->boolean('force'));
        $this->files->delete($model, $request->boolean('force'));

        return response()->noContent();
    }

    public function restore(Request $request, string $file): JsonResponse
    {
        return new JsonResponse($this->presenter->file($this->files->restore($this->file($request, $file, 'update', withTrashed: true))));
    }
}
