<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\Services\DriveUploads;

#[Description('Moyen recommandé pour ajouter un fichier au drive : renvoie un lien à usage unique, valable quelques minutes. Si vous pouvez envoyer le fichier vous-même, faites un PUT de son contenu brut sur upload_url ; sinon donnez page_url à l’utilisateur, qui y glissera le fichier. Le fichier est rangé dans folder_ids (à la racine sans eux) ; suivez le dépôt avec get-drive-upload-status.')]
#[IsDestructive(false)]
class RequestDriveUpload extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'folder_ids' => ['nullable', 'array'],
            'folder_ids.*' => ['uuid'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            ...$this->commonRules(),
        ]);

        $folders = $this->folders($request, $request->get('folder_ids'));
        $drive = $folders !== [] ? $this->drives()->driveOf($folders[0]) : $this->drive($request, 'update');
        $uploads = app(DriveUploads::class);

        $upload = $uploads->open(
            $drive,
            $this->user($request),
            (string) $request->get('filename'),
            $folders,
            $request->get('title'),
            $request->get('description'),
            $this->owner($request->get('owner_id')),
        );

        return $this->json([...$uploads->data($upload)->toArray(), 'upload_method' => 'PUT']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->required()->description('Nom du fichier (compte-rendu-reunion.pdf).'),
            'folder_ids' => $schema->array()->items($schema->string())->description('Dossiers où ranger le fichier. Sans eux : la racine.'),
            'title' => $schema->string()->description('Titre du fichier.'),
            'description' => $schema->string()->description('Description du fichier.'),
            'owner_id' => $this->ownerSchema($schema),
            ...$this->driveSchema($schema),
        ];
    }
}
