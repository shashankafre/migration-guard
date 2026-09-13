<?php

namespace MigrationGuard\Laravel;

use Illuminate\Support\ServiceProvider;
use MigrationGuard\Laravel\Analysis\MigrationAnalyzer;
use MigrationGuard\Laravel\Contracts\TenantManager;
use MigrationGuard\Laravel\Contracts\TenantResolver;
use MigrationGuard\Laravel\Laravel\Commands\MigrationSafetyCommand;

final class MigrationSafetyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/migration-guard.php', 'migration-guard');

        $this->app->singleton(MigrationAnalyzer::class);
        foreach ([TenantResolver::class => 'resolver', TenantManager::class => 'manager'] as $contract => $key) {
            $binding = config("migration-guard.tenancy.{$key}");
            if ($binding !== null) {
                $this->app->bind($contract, fn ($app) => is_string($binding) ? $app->make($binding) : $binding($app));
            }
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/migration-guard.php' => config_path('migration-guard.php'),
        ], 'migration-guard-config');

        if ($this->app->runningInConsole()) {
            $this->commands([MigrationSafetyCommand::class]);
        }
    }
}
