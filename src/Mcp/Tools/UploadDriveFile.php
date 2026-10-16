<?php

namespace OiLab\OiLaravelDrive\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Services\DriveFiles;

#[Description('Ajoute tout de suite un petit fichier au drive (1 Mo au plus) : un texte que vous rédigez (content_text : note, markdown, csv…) ou un fichier déjà en main encodé en base 64 (content_base64). Pour un fichier plus gros, préférez request-drive-upload. Le fichier est rangé dans folder_ids (à la racine sans eux).')]
#[IsDestructive(false)]
class UploadDriveFile extends DriveTool
{
    protected function respond(Request $request): Response
    {
        $maxBytes = (int) config('oi-laravel-drive.uploads.inline_max_bytes', 1_048_576);

        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'content_text' => ['nullable', 'string', 'required_without:content_base64', 'max:'.$maxBytes],
            'content_base64' => ['nullable', 'string', 'required_without:content_text', 'max:'.((int) ceil($maxBytes * 4 / 3) + 4)],
            'folder_ids' => ['nullable', 'array'],
            'folder_ids.*' => ['uuid'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            ...$this->commonRules(),
        ]);

        $content = $request->filled('content_text')
            ? (string) $request->get('content_text')
            : base64_decode((string) $request->get('content_base64'), true);

        if ($content === false || $content === '') {
            throw $content === false ? DriveException::invalidBase64() : DriveException::emptyFile();
        }

        if (strlen($content) > $maxBytes) {
            throw DriveException::fileTooLarge($maxBytes);
        }

        $folders = $this->folders($request, $request->get('folder_ids'));
        $drive = $folders !== [] ? $this->drives()->driveOf($folders[0]) : $this->drive($request, 'update');

        $file = app(DriveFiles::class)->storeContent(
            $drive,
            $content,
            (string) $request->get('filename'),
            $folders,
            $this->owner($request->get('owner_id')),
            $this->date($request->get('created_at')),
            $request->get('title'),
            $request->get('description'),
        );

        return $this->json($this->presenter()->file($file));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->required()->description('Nom du fichier, avec son extension (notes.md).'),
            'content_text' => $schema->string()->description('Contenu texte du fichier (UTF-8).'),
            'content_base64' => $schema->string()->description('Contenu binaire encodé en base 64 (1 Mo décodé au plus).'),
            'folder_ids' => $schema->array()->items($schema->string())->description('Dossiers où ranger le fichier. Sans eux : la racine.'),
            'title' => $schema->string()->description('Titre du fichier.'),
            'description' => $schema->string()->description('Description du fichier.'),
            'owner_id' => $this->ownerSchema($schema),
            'created_at' => $this->createdAtSchema($schema),
            ...$this->driveSchema($schema),
        ];
    }
}
