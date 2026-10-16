<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;

#[Description('Rend un dossier (folder_id, avec ce qui a été supprimé avec lui) ou un fichier (file_id) de la corbeille. Un dossier dont le parent n’existe plus revient à la racine.')]
#[IsDestructive(false)]
class RestoreDriveItem extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate([
            'folder_id' => ['nullable', 'uuid', 'required_without:file_id'],
            'file_id' => ['nullable', 'uuid', 'required_without:folder_id'],
        ]);

        if ($request->filled('folder_id')) {
            $folder = $this->folder($request, (string) $request->get('folder_id'), 'update', withTrashed: true);

            return $this->json($this->presenter()->folder(app(DriveFolders::class)->restore($folder)));
        }

        $file = $this->file($request, (string) $request->get('file_id'), 'update', withTrashed: true);

        return $this->json($this->presenter()->file(app(DriveFiles::class)->restore($file)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->string()->description('Dossier à restaurer.'),
            'file_id' => $schema->string()->description('Fichier à restaurer.'),
        ];
    }
}
