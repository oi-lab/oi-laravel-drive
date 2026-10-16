<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\Services\DriveLinks;
use OiLab\OiLaravelDrive\Services\DriveShares;

#[Description('Ouvre ou ferme le lien public d’un dossier : quiconque a l’adresse lit le dossier, ses sous-dossiers et leurs fichiers, sans compte. enabled=false ferme le lien. Facultatifs : password (mot de passe), remove_password, expires_at (date de fin), remove_expiry, regenerate (nouvelle adresse, l’ancienne cesse de marcher). Demandez confirmation avant d’ouvrir un lien sans mot de passe.')]
#[IsDestructive]
class UpdateDriveFolderLink extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate([
            'folder_id' => ['required', 'uuid'],
            'enabled' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'max:255'],
            'remove_password' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'remove_expiry' => ['nullable', 'boolean'],
            'regenerate' => ['nullable', 'boolean'],
        ]);

        $links = app(DriveLinks::class);
        $folder = $this->folder($request, (string) $request->get('folder_id'), 'update');

        if (! $request->boolean('enabled')) {
            $links->revoke($folder);

            return $this->json(app(DriveShares::class)->sharing($folder));
        }

        $link = $links->open($folder, $this->user($request));

        if ($request->boolean('remove_password')) {
            $links->setPassword($link, null);
        } elseif ($request->filled('password')) {
            $links->setPassword($link, (string) $request->get('password'));
        }

        if ($request->boolean('remove_expiry')) {
            $links->setExpiry($link, null);
        } elseif ($request->filled('expires_at')) {
            $links->setExpiry($link, $this->date($request->get('expires_at')));
        }

        if ($request->boolean('regenerate')) {
            $links->regenerate($link);
        }

        return $this->json(app(DriveShares::class)->sharing($folder));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->string()->required()->description('Dossier concerné.'),
            'enabled' => $schema->boolean()->required()->description('Vrai pour ouvrir (ou garder) le lien, faux pour le fermer.'),
            'password' => $schema->string()->description('Nouveau mot de passe du lien.'),
            'remove_password' => $schema->boolean()->description('Vrai pour retirer le mot de passe.'),
            'expires_at' => $schema->string()->description('Date et heure de fin du lien (ISO 8601).'),
            'remove_expiry' => $schema->boolean()->description('Vrai pour que le lien n’expire plus.'),
            'regenerate' => $schema->boolean()->description('Vrai pour donner au lien une nouvelle adresse.'),
        ];
    }
}
