<?php

namespace OiLab\OiLaravelDrive\Tests;

use Illuminate\Foundation\Application;
use Laravel\Mcp\Server\McpServiceProvider;
use OiLab\OiLaravelAttachments\OiLaravelAttachmentsServiceProvider;
use OiLab\OiLaravelDrive\OiLaravelDriveServiceProvider;
use OiLab\OiLaravelDrive\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            OiLaravelAttachmentsServiceProvider::class,
            McpServiceProvider::class,
            OiLaravelDriveServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('oi-laravel-attachments.user_model', User::class);
        $app['config']->set('oi-laravel-drive.user_model', User::class);
        $app['config']->set('oi-laravel-drive.driveable.current', 'currentTeam');
        $app['config']->set('oi-laravel-drive.driveable.members', 'members');
        $app['config']->set('oi-laravel-drive.files.disk', 'drive');
        $app['config']->set('oi-laravel-drive.api.middleware', ['api', 'auth']);
        $app['config']->set('filesystems.disks.drive', ['driver' => 'local', 'root' => storage_path('framework/testing/drive')]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }
}
