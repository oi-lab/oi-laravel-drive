<?php

namespace OiLab\OiLaravelDrive\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use OiLab\OiLaravelDrive\Services\DriveManager;

/**
 * Shared lookups of the drive API: every folder and file is authorized
 * through its drive (DrivePolicy, or the host's policy).
 */
abstract class Controller
{
    public function __construct(protected DriveManager $drives) {}

    protected function user(Request $request): Authenticatable
    {
        return $request->user() ?? abort(401);
    }

    /**
     * The drive given by `drive` (UUID), or the default drive of the user's
     * entity.
     */
    protected function drive(Request $request, string $ability = 'view', ?string $uuid = null): Drive
    {
        $drive = $this->drives->resolveDrive($this->user($request), $uuid ?? $request->input('drive') ?? $request->input('drive_id'));
        $this->authorize($request, $ability, $drive);

        return $drive;
    }

    protected function folder(Request $request, ?string $uuid, string $ability = 'view', bool $withTrashed = false): ?Folder
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $folder = $this->drives->findFolder($uuid, $withTrashed);
        $this->authorize($request, $ability, $this->drives->driveOf($folder));

        return $folder;
    }

    protected function file(Request $request, string $uuid, string $ability = 'view', bool $withTrashed = false): File
    {
        $file = $this->drives->findFile($uuid, $withTrashed);
        $this->authorize($request, $ability, $this->drives->driveOf($file));

        return $file;
    }

    /**
     * Folders given by UUID, each authorized.
     *
     * @param  array<int, string>|null  $uuids
     * @return list<Folder>
     */
    protected function folders(Request $request, ?array $uuids): array
    {
        return collect($uuids ?? [])
            ->unique()
            ->map(fn (string $uuid): Folder => $this->folder($request, $uuid, 'update'))
            ->values()
            ->all();
    }

    protected function owner(mixed $id): ?Authenticatable
    {
        if ($id === null || $id === '') {
            return null;
        }

        return OiLaravelDrive::userModel()::query()->findOrFail($id);
    }

    protected function authorize(Request $request, string $ability, Drive $drive): void
    {
        Gate::forUser($this->user($request))->authorize($ability, $drive);
    }
}
