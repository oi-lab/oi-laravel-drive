<?php

namespace OiLab\OiLaravelDrive\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use OiLab\OiLaravelDrive\Data\DriveFolderData;
use OiLab\OiLaravelDrive\Http\Requests\StoreFolderRequest;
use OiLab\OiLaravelDrive\Http\Requests\UpdateFolderRequest;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DrivePresenter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FolderController extends Controller
{
    public function __construct(
        DriveManager $drives,
        protected DriveFolders $folders,
        protected DrivePresenter $presenter,
    ) {
        parent::__construct($drives);
    }

    public function store(StoreFolderRequest $request): SymfonyResponse
    {
        $parent = $this->folder($request, $request->validated('parent_id'), 'update');
        $drive = $parent ? $this->drives->driveOf($parent) : $this->drive($request, 'update');

        $folder = $this->folders->create(
            $drive,
            $request->validated('name'),
            $parent,
            $this->owner($request->validated('owner_id')),
            $request->validated('created_at') ? Carbon::parse($request->validated('created_at')) : null,
            $request->validated('props'),
        );

        return $this->presenter->folder($folder)->toResponse($request)->setStatusCode(201);
    }

    public function show(Request $request, string $folder): DriveFolderData
    {
        return $this->presenter->folder($this->folder($request, $folder, withTrashed: true));
    }

    public function update(UpdateFolderRequest $request, string $folder): DriveFolderData
    {
        $model = $this->folder($request, $folder, 'update');

        if ($request->has('name') || $request->has('props')) {
            $this->folders->update($model, $request->validated('name'), $request->validated('props'));
        }

        if ($request->exists('parent_id')) {
            $this->folders->move($model, $this->folder($request, $request->validated('parent_id'), 'update'));
        }

        if ($request->exists('owner_id')) {
            $this->drives->setOwner($model, $this->owner($request->validated('owner_id')));
        }

        if ($request->validated('created_at')) {
            $this->drives->setCreatedAt($model, Carbon::parse($request->validated('created_at')));
        }

        return $this->presenter->folder($model->refresh());
    }

    /**
     * `with_files`: also delete the files filed only in this folder tree.
     * `force`: skip the trash.
     */
    public function destroy(Request $request, string $folder): Response
    {
        $model = $this->folder($request, $folder, 'update', withTrashed: $request->boolean('force'));
        $this->folders->delete($model, $request->boolean('with_files'), $request->boolean('force'));

        return response()->noContent();
    }

    public function restore(Request $request, string $folder): JsonResponse
    {
        return new JsonResponse($this->presenter->folder($this->folders->restore($this->folder($request, $folder, 'update', withTrashed: true))));
    }
}
