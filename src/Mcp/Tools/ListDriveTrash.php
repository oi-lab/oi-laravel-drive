<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Liste la corbeille du drive : les dossiers et fichiers supprimés (deleted_at), que restore-drive-item peut rendre. Ce qui a été supprimé avec un dossier revient avec lui.')]
#[IsReadOnly]
#[IsIdempotent]
class ListDriveTrash extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate(['drive_id' => ['nullable', 'uuid']]);

        return $this->json($this->drives()->trash($this->drive($request)));
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->driveSchema($schema);
    }
}
