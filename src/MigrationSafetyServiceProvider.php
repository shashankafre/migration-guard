<?php

namespace MigrationSafe\Laravel;

use Illuminate\Support\ServiceProvider;
use MigrationSafe\Laravel\Analysis\MigrationAnalyzer;
use MigrationSafe\Laravel\Contracts\TenantResolver;
use MigrationSafe\Laravel\Laravel\Commands\MigrationSafetyCommand;

final class MigrationSafetyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/migration-safety.php', 'migration-safety');

        $this->app->singleton(MigrationAnalyzer::class);
        $this->app->bind(TenantResolver::class, static fn () => null);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/migration-safety.php' => config_path('migration-safety.php'),
        ], 'migration-safety-config');

        if ($this->app->runningInConsole()) {
            $this->commands([MigrationSafetyCommand::class]);
        }
    }
}
