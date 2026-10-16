<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use OiLab\OiLaravelDrive\Services\DriveFiles;

#[Description('Supprime un fichier du drive, de tous ses dossiers à la fois (dans la corbeille quand elle est active : restore-drive-item le rend). Pour seulement le retirer d’un dossier, utilisez update-drive-file avec remove_folder_ids. Demandez confirmation à l’utilisateur avant d’appeler cet outil.')]
#[IsDestructive]
class DeleteDriveFile extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate(['file_id' => ['required', 'uuid']]);

        $file = $this->file($request, (string) $request->get('file_id'), 'update');
        app(DriveFiles::class)->delete($file);

        return $this->json(['deleted' => $file->uuid, 'in_trash' => OiLaravelDrive::usesTrash()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file_id' => $schema->string()->required()->description('Fichier à supprimer.'),
        ];
    }
}
