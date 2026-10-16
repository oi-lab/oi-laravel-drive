<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use OiLab\OiLaravelDrive\Services\DriveUploads;

#[Description('État d’un dépôt ouvert par request-drive-upload : awaiting (en attente du fichier), completed (le fichier est dans le drive, voir file), expired ou failed (voir error).')]
#[IsReadOnly]
class GetDriveUploadStatus extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate(['upload_id' => ['required', 'uuid']]);

        $upload = OiLaravelDrive::uploadModel()::query()->findOrFail($request->get('upload_id'));
        $this->authorize($request, 'view', $upload->drive);

        return $this->json(app(DriveUploads::class)->data($upload));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'upload_id' => $schema->string()->required()->description('Identifiant renvoyé par request-drive-upload.'),
        ];
    }
}
