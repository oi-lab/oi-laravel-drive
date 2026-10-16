<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use OiLab\OiLaravelAttachments\Models\File;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DrivePresenter;
use Spatie\LaravelData\Contracts\BaseData;

/**
 * Base of the drive tools: the caller is the user of the token; every folder
 * and file is authorized through its drive (DrivePolicy). Identifiers are
 * UUIDs; `drive_id` is optional, the default drive of the caller's current
 * entity being used without it.
 */
abstract class DriveTool extends Tool
{
    /**
     * `list-drive-folder`, or `list_drive_folder` when `mcp.naming` is `snake`.
     */
    public function name(): string
    {
        $name = Str::kebab(class_basename($this));

        return config('oi-laravel-drive.mcp.naming') === 'snake' ? str_replace('-', '_', $name) : $name;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            return $this->respond($request);
        } catch (DriveException $exception) {
            return Response::error($exception->getMessage());
        } catch (ModelNotFoundException) {
            return Response::error('Élément introuvable dans le drive (vérifiez l’identifiant).');
        } catch (AuthorizationException) {
            return Response::error('Accès refusé à ce drive.');
        }
    }

    abstract protected function respond(Request $request): Response|ResponseFactory;

    protected function drives(): DriveManager
    {
        return app(DriveManager::class);
    }

    protected function presenter(): DrivePresenter
    {
        return app(DrivePresenter::class);
    }

    protected function user(Request $request): Authenticatable
    {
        return $request->user() ?? throw new AuthorizationException;
    }

    protected function drive(Request $request, string $ability = 'view'): Drive
    {
        $drive = $this->drives()->resolveDrive($this->user($request), $request->get('drive_id'));
        $this->authorize($request, $ability, $drive);

        return $drive;
    }

    protected function folder(Request $request, ?string $uuid, string $ability = 'view', bool $withTrashed = false): ?Folder
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $folder = $this->drives()->findFolder($uuid, $withTrashed);
        $this->authorize($request, $ability, $this->drives()->driveOf($folder));

        return $folder;
    }

    protected function file(Request $request, string $uuid, string $ability = 'view', bool $withTrashed = false): File
    {
        $file = $this->drives()->findFile($uuid, $withTrashed);
        $this->authorize($request, $ability, $this->drives()->driveOf($file));

        return $file;
    }

    /**
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

    protected function date(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    protected function authorize(Request $request, string $ability, Drive $drive): void
    {
        Gate::forUser($this->user($request))->authorize($ability, $drive);
    }

    /**
     * @param  BaseData|array<string, mixed>  $data
     */
    protected function json(BaseData|array $data): Response
    {
        return Response::json($data instanceof BaseData ? $data->toArray() : $data);
    }

    /**
     * @return array<string, Type>
     */
    protected function driveSchema(JsonSchema $schema): array
    {
        return [
            'drive_id' => $schema->string()->description(OiLaravelDrive::allowsMultipleDrives()
                ? 'Drive visé (identifiant donné par list-drives). Par défaut : le premier drive de l’entité courante.'
                : 'Facultatif : l’entité courante n’a qu’un drive.'),
        ];
    }

    protected function ownerSchema(JsonSchema $schema): Type
    {
        return $schema->integer()->description('Identifiant de l’utilisateur propriétaire, membre de l’entité du drive.');
    }

    protected function createdAtSchema(JsonSchema $schema): Type
    {
        return $schema->string()->description('Date de création à enregistrer (ISO 8601), par exemple pour un import. Par défaut : maintenant.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function commonRules(): array
    {
        return [
            'drive_id' => ['nullable', 'uuid'],
            'owner_id' => ['nullable', 'integer'],
            'created_at' => ['nullable', 'date'],
        ];
    }
}
