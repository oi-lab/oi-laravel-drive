<?php

namespace OiLab\OiLaravelDrive\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OiLab\OiLaravelDrive\Data\DriveData;
use OiLab\OiLaravelDrive\Http\Requests\DriveRequest;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use OiLab\OiLaravelDrive\Services\DrivePresenter;

class DriveController extends Controller
{
    /**
     * The drives of the user's current entity.
     */
    public function index(Request $request, DrivePresenter $presenter): JsonResponse
    {
        $driveable = $this->drives->currentDriveable($this->user($request));
        $gate = Gate::forUser($this->user($request));

        $drives = $this->drives->drivesOf($driveable)
            ->filter(fn (Drive $drive): bool => $gate->allows('view', $drive))
            ->map(fn (Drive $drive): DriveData => $presenter->drive($drive))
            ->values();

        return new JsonResponse([
            'multiple' => OiLaravelDrive::allowsMultipleDrives(),
            'drives' => $drives,
        ]);
    }

    public function store(DriveRequest $request, DrivePresenter $presenter): JsonResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('create', OiLaravelDrive::driveModel());

        $drive = $this->drives->createDrive(
            $this->drives->currentDriveable($user),
            $request->validated('name'),
            $this->owner($request->validated('owner_id')) ?? $user,
            $request->validated('props'),
        );

        return $presenter->drive($drive)->toResponse($request)->setStatusCode(201);
    }

    public function show(Request $request, string $drive, DrivePresenter $presenter): DriveData
    {
        return $presenter->drive($this->drive($request, 'view', $drive));
    }

    public function update(DriveRequest $request, string $drive, DrivePresenter $presenter): DriveData
    {
        $model = $this->drive($request, 'update', $drive);

        return $presenter->drive($this->drives->updateDrive($model, $request->validated('name'), $request->validated('props')));
    }

    public function destroy(Request $request, string $drive): Response
    {
        $this->drives->deleteDrive($this->drive($request, 'delete', $drive));

        return response()->noContent();
    }
}
