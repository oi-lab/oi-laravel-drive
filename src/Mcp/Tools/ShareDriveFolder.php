<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\Services\DriveShares;

#[Description('Partage un dossier du drive en lecture seule avec d’autres entités (add), ou cesse de le partager (remove). Une entité se désigne par son identifiant ou son nom exact. Ses membres lisent le dossier, ses sous-dossiers et leurs fichiers, sans rien pouvoir modifier. Donne le partage qui en résulte.')]
#[IsDestructive(false)]
class ShareDriveFolder extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate([
            'folder_id' => ['required', 'uuid'],
            'add' => ['nullable', 'array'],
            'add.*' => ['required'],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['required'],
        ]);

        $shares = app(DriveShares::class);
        $folder = $this->folder($request, (string) $request->get('folder_id'), 'update');

        foreach ((array) $request->get('remove', []) as $target) {
            $shares->unshare($folder, $shares->findShared($folder, $target));
        }

        foreach ((array) $request->get('add', []) as $target) {
            $shares->share($folder, $shares->findShareable($this->user($request), $folder, $target), $this->user($request));
        }

        return $this->json($shares->sharing($folder));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->string()->required()->description('Dossier à partager.'),
            'add' => $schema->array()->items($schema->string())->description('Entités avec qui partager le dossier (identifiant ou nom exact).'),
            'remove' => $schema->array()->items($schema->string())->description('Entités avec qui ne plus le partager (identifiant ou nom exact).'),
        ];
    }
}
