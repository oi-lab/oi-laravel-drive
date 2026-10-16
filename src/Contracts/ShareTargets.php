<?php

namespace OiLab\OiLaravelDrive\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OiLab\OiLaravelAttachments\Models\Folder;

/**
 * The entities a user may share a folder with — to keep the tenants of an
 * application apart, a host lists only the entities the user deals with.
 * Bound from `oi-laravel-drive.sharing.targets`.
 */
interface ShareTargets
{
    /**
     * @param  class-string<Model>  $model  The class a folder of this drive is shared with
     * @return Builder<Model>
     */
    public function query(Authenticatable $user, Folder $folder, string $model): Builder;
}
