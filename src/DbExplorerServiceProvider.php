<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer;

use Hatchyu\DbExplorer\Console\Commands\InstallCommand;
use Hatchyu\DbExplorer\Console\Commands\UpdateCommand;
use Illuminate\Support\ServiceProvider;

final class DbExplorerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/db-explorer.php', 'db-explorer'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                UpdateCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/db-explorer'),
        ], 'db-explorer-views');

        $this->publishes([
            __DIR__ . '/../config/db-explorer.php' => config_path('db-explorer.php'),
        ], 'db-explorer-config');

        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/db-explorer'),
        ], 'db-explorer-assets');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'db-explorer-migrations');

        if (! $this->shouldRegister()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'db-explorer');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    private function shouldRegister(): bool
    {
        if (! config('db-explorer.enabled')) {
            return false;
        }

        return app()->environment(
            config('db-explorer.allowed_environments', [])
        );
    }
}
