<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\Services\DriveFiles;

#[Description('Modifie un fichier du drive : nom, titre, description, propriétaire (owner_id), et ses dossiers — add_folder_ids le range en plus dans des dossiers, remove_folder_ids l’en retire, folder_ids fixe exactement ses dossiers ([] : la racine). Les arguments absents ne changent rien.')]
#[IsDestructive(false)]
class UpdateDriveFile extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate([
            'file_id' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'folder_ids' => ['nullable', 'array'],
            'folder_ids.*' => ['uuid'],
            'add_folder_ids' => ['nullable', 'array'],
            'add_folder_ids.*' => ['uuid'],
            'remove_folder_ids' => ['nullable', 'array'],
            'remove_folder_ids.*' => ['uuid'],
            ...$this->commonRules(),
        ]);

        $files = app(DriveFiles::class);
        $file = $this->file($request, (string) $request->get('file_id'), 'update');

        $files->update($file, collect(['name', 'title', 'description'])
            ->filter(fn (string $key): bool => $request->exists($key))
            ->mapWithKeys(fn (string $key): array => [$key => $request->get($key)])
            ->filter(fn ($value, string $key): bool => $key !== 'name' || filled($value))
            ->all());

        if (is_array($request->get('folder_ids'))) {
            $files->sync($file, $this->folders($request, $request->get('folder_ids')));
        }

        if ($request->filled('add_folder_ids')) {
            $files->attach($file, $this->folders($request, $request->get('add_folder_ids')));
        }

        if ($request->filled('remove_folder_ids')) {
            $files->detach($file, $this->folders($request, $request->get('remove_folder_ids')));
        }

        if ($request->filled('owner_id')) {
            $this->drives()->setOwner($file, $this->owner($request->get('owner_id')));
        }

        if ($request->filled('created_at')) {
            $this->drives()->setCreatedAt($file, $this->date($request->get('created_at')));
        }

        return $this->json($this->presenter()->file($file->refresh()));
    }

    public function schema(JsonSchema $schema): array
    {
        $folders = fn (string $description) => $schema->array()->items($schema->string())->description($description);

        return [
            'file_id' => $schema->string()->required()->description('Fichier à modifier.'),
            'name' => $schema->string()->description('Nouveau nom, avec son extension.'),
            'title' => $schema->string()->description('Titre.'),
            'description' => $schema->string()->description('Description.'),
            'folder_ids' => $folders('Exactement les dossiers du fichier ([] : la racine).'),
            'add_folder_ids' => $folders('Dossiers où ranger le fichier en plus.'),
            'remove_folder_ids' => $folders('Dossiers d’où retirer le fichier.'),
            'owner_id' => $this->ownerSchema($schema),
            'created_at' => $this->createdAtSchema($schema),
        ];
    }
}
