<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use OiLab\OiLaravelDrive\Models\Drive;

#[Description('Liste les drives (espaces de fichiers) de l’entité courante, avec leur identifiant.')]
#[IsReadOnly]
#[IsIdempotent]
class ListDrives extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $user = $this->user($request);
        $gate = Gate::forUser($user);

        return $this->json([
            'drives' => $this->drives()->drivesOf($this->drives()->currentDriveable($user))
                ->filter(fn (Drive $drive): bool => $gate->allows('view', $drive))
                ->map(fn (Drive $drive): array => $this->presenter()->drive($drive)->toArray())
                ->values()
                ->all(),
        ]);
    }
}
