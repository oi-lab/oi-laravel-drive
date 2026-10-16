<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use OiLab\OiLaravelDrive\Services\DriveShares;

#[Description('Dit à qui un dossier du drive est ouvert : les entités avec qui il est partagé en lecture seule (shares) et son lien public (link : adresse, mot de passe ou non, expiration, nombre de consultations), null quand il n’en a pas.')]
#[IsReadOnly]
#[IsIdempotent]
class GetDriveFolderSharing extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate(['folder_id' => ['required', 'uuid']]);

        $folder = $this->folder($request, (string) $request->get('folder_id'));

        return $this->json(app(DriveShares::class)->sharing($folder));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->string()->required()->description('Dossier dont on veut le partage.'),
        ];
    }
}
