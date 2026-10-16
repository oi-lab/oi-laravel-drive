<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Cherche dans le drive les dossiers par leur nom et les fichiers par leur nom, titre ou description (50 de chaque au plus).')]
#[IsReadOnly]
#[IsIdempotent]
class SearchDrive extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate(['query' => ['required', 'string', 'min:2', 'max:200'], 'drive_id' => ['nullable', 'uuid']]);

        return $this->json($this->drives()->search($this->drive($request), (string) $request->get('query')));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Texte cherché (2 caractères au moins).'),
            ...$this->driveSchema($schema),
        ];
    }
}
