<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OiLab\OiLaravelDrive\Models\Drive;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Tests\Fixtures\Team;
use OiLab\OiLaravelDrive\Tests\Fixtures\User;
use OiLab\OiLaravelDrive\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => Storage::fake('drive'))
    ->in(__DIR__);

/**
 * A team, a member working in it, and the team's drive.
 *
 * @return array{team: Team, user: User, drive: Drive}
 */
function driveContext(): array
{
    $team = Team::query()->create(['name' => 'Équipe']);
    $user = User::factory()->create()->joinAndSwitch($team);
    test()->actingAs($user);

    return ['team' => $team, 'user' => $user, 'drive' => app(DriveManager::class)->driveFor($team)];
}
