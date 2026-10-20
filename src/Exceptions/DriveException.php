<?php

namespace OiLab\OiLaravelDrive\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A drive rule refused the operation. `rule` is a stable machine code
 * (`folder_name_taken`, `file_too_large`…), the message is for people.
 */
class DriveException extends RuntimeException
{
    public function __construct(string $message, public readonly string $rule, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function noDriveable(): self
    {
        return new self('Aucune entité courante : impossible de trouver le drive.', 'no_driveable', 403);
    }

    public static function singleDrive(): self
    {
        return new self('Cette application ne permet qu’un seul drive par entité.', 'single_drive');
    }

    public static function folderNameTaken(string $name): self
    {
        return new self("Un dossier « {$name} » existe déjà à cet endroit.", 'folder_name_taken');
    }

    public static function otherDrive(): self
    {
        return new self('Ce dossier appartient à un autre drive.', 'other_drive');
    }

    public static function cyclicMove(): self
    {
        return new self('Un dossier ne peut pas être déplacé dans lui-même ni dans l’un de ses sous-dossiers.', 'cyclic_move');
    }

    public static function trashed(): self
    {
        return new self('Cet élément est dans la corbeille : restaurez-le d’abord.', 'trashed');
    }

    public static function notTrashed(): self
    {
        return new self('Cet élément n’est pas dans la corbeille.', 'not_trashed');
    }

    public static function locked(): self
    {
        return new self('Cet élément est géré par l’application : il ne peut être ni renommé, ni déplacé, ni supprimé.', 'locked', 403);
    }

    public static function lockedContent(): self
    {
        return new self('Ce dossier contient des éléments gérés par l’application : il ne peut pas être supprimé.', 'locked_content', 403);
    }

    public static function sealed(string $name): self
    {
        return new self("Le contenu du dossier « {$name} » est géré par l’application : on ne peut pas y ajouter ni en retirer d’éléments.", 'sealed', 403);
    }

    public static function notMember(): self
    {
        return new self('Ce propriétaire n’est pas membre de l’entité du drive.', 'owner_not_member');
    }

    public static function emptyFile(): self
    {
        return new self('Le fichier est vide.', 'file_empty');
    }

    public static function fileTooLarge(int $maxBytes): self
    {
        $megabytes = max(1, intdiv($maxBytes, 1_048_576));

        return new self("Le fichier dépasse la taille autorisée ({$megabytes} Mo).", 'file_too_large');
    }

    public static function mimeRefused(string $mime): self
    {
        return new self("Ce type de fichier n’est pas accepté (type lu dans le fichier : {$mime}).", 'mime_refused');
    }

    public static function invalidBase64(): self
    {
        return new self('Le contenu n’est pas un base 64 valide.', 'invalid_base64');
    }

    public static function uploadClosed(): self
    {
        return new self('Ce lien de dépôt a déjà servi ou a expiré : demandez-en un nouveau.', 'upload_closed', 409);
    }

    public static function uploadsDisabled(): self
    {
        return new self('Les dépôts par lien sont désactivés.', 'uploads_disabled', 404);
    }

    public static function shareWithOwner(): self
    {
        return new self('Ce dossier appartient déjà à cette entité : inutile de le partager avec elle.', 'share_with_owner');
    }

    public static function shareTargetRefused(): self
    {
        return new self('Ce dossier ne peut pas être partagé avec cette entité.', 'share_target_refused');
    }

    public static function passwordTooShort(int $minLength): self
    {
        return new self("Le mot de passe doit compter au moins {$minLength} caractères.", 'password_too_short');
    }

    public static function archiveTooLarge(int $maxBytes): self
    {
        $megabytes = (int) round($maxBytes / 1024 / 1024);

        return new self("Ce dossier dépasse {$megabytes} Mo : téléchargez ses fichiers un par un.", 'archive_too_large', 413);
    }

    public static function archivesUnavailable(): self
    {
        return new self('Le téléchargement en ZIP n’est pas disponible sur ce serveur (extension zip absente).', 'archives_unavailable', 501);
    }

    /**
     * JSON `{error, message}` for API calls; the default rendering otherwise.
     */
    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return null;
        }

        return new JsonResponse(['error' => $this->rule, 'message' => $this->getMessage()], $this->status ?: Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
