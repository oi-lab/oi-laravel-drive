<?php

namespace OiLab\OiLaravelDrive\Sharing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use OiLab\OiLaravelAttachments\Models\Folder;
use OiLab\OiLaravelDrive\Contracts\ShareTargets;

/**
 * Every entity of the shareable class: fit for an application with a single
 * tenant. Bind your own ShareTargets when entities must stay apart.
 */
class AnyShareTarget implements ShareTargets
{
    public function query(Authenticatable $user, Folder $folder, string $model): Builder
    {
        return $model::query();
    }
}
