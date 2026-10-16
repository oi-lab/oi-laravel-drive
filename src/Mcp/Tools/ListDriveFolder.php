<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Liste le contenu d’un dossier du drive (sous-dossiers et fichiers), ou de la racine sans folder_id. Un fichier rangé dans aucun dossier est à la racine ; un même fichier peut être rangé dans plusieurs dossiers (folder_ids). Donne aussi le chemin (breadcrumbs).')]
#[IsReadOnly]
#[IsIdempotent]
class ListDriveFolder extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate(['folder_id' => ['nullable', 'uuid'], 'drive_id' => ['nullable', 'uuid']]);

        $folder = $this->folder($request, $request->get('folder_id'));
        $drive = $folder ? $this->drives()->driveOf($folder) : $this->drive($request);

        return $this->json($this->drives()->contents($drive, $folder));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->string()->description('Dossier à lister. Sans lui : la racine du drive.'),
            ...$this->driveSchema($schema),
        ];
    }
}
