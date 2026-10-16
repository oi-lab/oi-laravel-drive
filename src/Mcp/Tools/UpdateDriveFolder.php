<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\Services\DriveFolders;

#[Description('Renomme un dossier (name), le déplace (parent_id, ou move_to_root pour la racine) ou change son propriétaire (owner_id). Les arguments absents ne changent rien.')]
#[IsDestructive(false)]
class UpdateDriveFolder extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate([
            'folder_id' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
            'move_to_root' => ['nullable', 'boolean'],
            ...$this->commonRules(),
        ]);

        $folders = app(DriveFolders::class);
        $folder = $this->folder($request, (string) $request->get('folder_id'), 'update');

        if ($request->filled('name')) {
            $folders->update($folder, (string) $request->get('name'));
        }

        if ($request->boolean('move_to_root')) {
            $folders->move($folder, null);
        } elseif ($request->filled('parent_id')) {
            $folders->move($folder, $this->folder($request, (string) $request->get('parent_id'), 'update'));
        }

        if ($request->filled('owner_id')) {
            $this->drives()->setOwner($folder, $this->owner($request->get('owner_id')));
        }

        if ($request->filled('created_at')) {
            $this->drives()->setCreatedAt($folder, $this->date($request->get('created_at')));
        }

        return $this->json($this->presenter()->folder($folder->refresh()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->string()->required()->description('Dossier à modifier.'),
            'name' => $schema->string()->description('Nouveau nom.'),
            'parent_id' => $schema->string()->description('Nouveau dossier parent.'),
            'move_to_root' => $schema->boolean()->description('Vrai pour déplacer le dossier à la racine.'),
            'owner_id' => $this->ownerSchema($schema),
            'created_at' => $this->createdAtSchema($schema),
        ];
    }
}
