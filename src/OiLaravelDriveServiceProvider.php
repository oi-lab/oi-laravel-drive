<?php

namespace OiLab\OiLaravelDrive;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OiLab\OiLaravelDrive\Console\Commands\InstallAiSkillCommand;
use OiLab\OiLaravelDrive\Console\Commands\PruneUploadsCommand;
use OiLab\OiLaravelDrive\Console\Commands\PurgeTrashCommand;
use OiLab\OiLaravelDrive\Contracts\DriveableResolver;
use OiLab\OiLaravelDrive\Contracts\ShareTargets;
use OiLab\OiLaravelDrive\Resolvers\ConfigurableDriveableResolver;
use OiLab\OiLaravelDrive\Services\DriveArchives;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveLinks;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DrivePresenter;
use OiLab\OiLaravelDrive\Services\DriveRelations;
use OiLab\OiLaravelDrive\Services\DriveShares;
use OiLab\OiLaravelDrive\Services\DriveUploads;
use OiLab\OiLaravelDrive\Sharing\AnyShareTarget;

class OiLaravelDriveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oi-laravel-drive.php', 'oi-laravel-drive');

        $this->app->bind(DriveableResolver::class, fn ($app) => $app->make(
            config('oi-laravel-drive.driveable.resolver', ConfigurableDriveableResolver::class)
        ));

        $this->app->bind(ShareTargets::class, fn ($app) => $app->make(
            config('oi-laravel-drive.sharing.targets', AnyShareTarget::class)
        ));

        foreach ([DrivePresenter::class, DriveManager::class, DriveFiles::class, DriveFolders::class, DriveUploads::class, DriveShares::class, DriveLinks::class, DriveArchives::class] as $service) {
            $this->app->scoped($service);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'oi-laravel-drive');

        DriveRelations::register();

        Gate::policy(OiLaravelDrive::driveModel(), config('oi-laravel-drive.policy'));

        $this->registerRoutes();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (config('oi-laravel-drive.trash.purge_after_days') !== null) {
                $schedule->command('drive:purge-trash')->daily();
            }

            if (config('oi-laravel-drive.uploads.enabled')) {
                $schedule->command('drive:prune-uploads')->hourly();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallAiSkillCommand::class,
                PurgeTrashCommand::class,
                PruneUploadsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/oi-laravel-drive.php' => config_path('oi-laravel-drive.php'),
            ], 'oi-laravel-drive-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'oi-laravel-drive-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/oi-laravel-drive'),
            ], 'oi-laravel-drive-views');

            $this->publishes([
                __DIR__.'/../resources/stubs/ai-skill.md' => base_path('.claude/skills/oilab-laravel-drive/SKILL.md'),
            ], 'oi-laravel-drive-skill');
        }
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        if (config('oi-laravel-drive.api.enabled')) {
            Route::prefix(config('oi-laravel-drive.api.prefix', 'api/drive'))
                ->name(config('oi-laravel-drive.api.name', 'drive.api.'))
                ->middleware(config('oi-laravel-drive.api.middleware', ['api', 'auth:sanctum']))
                ->group(__DIR__.'/../routes/api.php');
        }

        Route::prefix(config('oi-laravel-drive.signed_routes.prefix', 'drive'))
            ->name(config('oi-laravel-drive.signed_routes.name', 'drive.'))
            ->middleware([...config('oi-laravel-drive.signed_routes.middleware', []), 'signed'])
            ->group(__DIR__.'/../routes/signed.php');
    }
}
