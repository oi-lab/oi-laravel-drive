<?php

namespace OiLab\OiLaravelDrive\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    /**
     * A member of the team, working in it.
     */
    public function joinAndSwitch(Team $team): static
    {
        $this->teams()->syncWithoutDetaching([$team->id]);
        $this->forceFill(['current_team_id' => $team->id])->save();

        return $this->refresh();
    }

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
