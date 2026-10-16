<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\Services\DriveFolders;

#[Description('Crée un dossier dans le drive, à la racine ou dans parent_id. Le propriétaire est l’utilisateur connecté, sauf owner_id.')]
#[IsDestructive(false)]
class CreateDriveFolder extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
            ...$this->commonRules(),
        ]);

        $parent = $this->folder($request, $request->get('parent_id'), 'update');
        $drive = $parent ? $this->drives()->driveOf($parent) : $this->drive($request, 'update');

        $folder = app(DriveFolders::class)->create(
            $drive,
            (string) $request->get('name'),
            $parent,
            $this->owner($request->get('owner_id')),
            $this->date($request->get('created_at')),
        );

        return $this->json($this->presenter()->folder($folder));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Nom du dossier.'),
            'parent_id' => $schema->string()->description('Dossier parent. Sans lui : la racine.'),
            'owner_id' => $this->ownerSchema($schema),
            'created_at' => $this->createdAtSchema($schema),
            ...$this->driveSchema($schema),
        ];
    }
}
