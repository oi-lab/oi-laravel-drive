<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use OiLab\OiLaravelDrive\Services\DriveFolders;

#[Description('Supprime un dossier et ses sous-dossiers (dans la corbeille quand elle est active : restore-drive-item les rend). Sans with_files, ses fichiers restent dans le drive (à la racine s’ils ne sont rangés nulle part ailleurs) ; avec with_files, les fichiers rangés uniquement dans ces dossiers sont supprimés aussi. Un fichier rangé aussi dans un autre dossier n’est jamais supprimé, seulement retiré. Demandez confirmation à l’utilisateur avant d’appeler cet outil.')]
#[IsDestructive]
class DeleteDriveFolder extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate(['folder_id' => ['required', 'uuid'], 'with_files' => ['nullable', 'boolean']]);

        $folder = $this->folder($request, (string) $request->get('folder_id'), 'update');
        app(DriveFolders::class)->delete($folder, $request->boolean('with_files'));

        return $this->json([
            'deleted' => $folder->uuid,
            'in_trash' => OiLaravelDrive::usesTrash(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->string()->required()->description('Dossier à supprimer.'),
            'with_files' => $schema->boolean()->description('Supprimer aussi les fichiers rangés uniquement dans ce dossier et ses sous-dossiers (faux par défaut).'),
        ];
    }
}
