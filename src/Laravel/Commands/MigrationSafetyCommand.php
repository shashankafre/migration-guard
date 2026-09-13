<?php

namespace MigrationGuard\Laravel\Laravel\Commands;

use Illuminate\Console\Command;
use MigrationGuard\Laravel\Analysis\MigrationAnalysis;
use MigrationGuard\Laravel\Analysis\MigrationAnalyzer;
use MigrationGuard\Laravel\Contracts\TenantManager;
use MigrationGuard\Laravel\Contracts\TenantResolver;
use MigrationGuard\Laravel\Reports\ConsoleReporter;
use MigrationGuard\Laravel\Risk\RiskLevel;

final class MigrationSafetyCommand extends Command
{
    protected $signature = 'migration:safety
        {--central : Analyze central migrations only}
        {--tenants : Analyze tenant migrations only}
        {--tenant= : Analyze one tenant by ID}
        {--ci : Return a non-zero code when the configured threshold is reached}
        {--format=console : Output format: console or json}
        {--path=* : Migration path to analyze instead of the configured paths}
        {--from= : Analyze migrations at or after this migration name}
        {--limit= : Maximum tenants to analyze}
        {--fail-fast : Stop tenant analysis at the configured blocking threshold}';

    protected $description = 'Analyze pending migrations for deployment safety risks';

    public function handle(MigrationAnalyzer $analyzer, ConsoleReporter $reporter): int
    {
        if (!config('migration-guard.enabled')) {
            $this->components->info('Migration safety analysis is disabled.');
            return self::SUCCESS;
        }

        if ($this->option('central') && $this->option('tenants')) {
            $this->components->error('Choose either --central or --tenants, not both.');
            return 4;
        }

        $analyses = [];
        $tenantRequested = $this->option('tenants') || $this->option('tenant') !== null;
        if (!$tenantRequested && config('migration-guard.migrations.central.enabled')) {
            $analyses['central'] = $analyzer->central($this->paths(), $this->option('from'));
        }

        if (!$this->option('central') && config('migration-guard.migrations.tenant.enabled')) {
            $analyses['tenants'] = $this->analyzeTenants($analyzer);
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode($this->json($analyses), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } elseif ($this->option('format') !== 'console') {
            $this->components->error('The format must be console or json.');
            return 4;
        } else {
            foreach ($analyses as $scope => $analysis) {
                $reporter->report($this->output, strtoupper($scope), $analysis);
            }
        }

        return $this->exitCode($analyses);
    }

    private function analyzeTenants(MigrationAnalyzer $analyzer): MigrationAnalysis
    {
        if (!app()->bound(TenantResolver::class)) {
            $this->components->error('Tenant analysis requires a configured TenantResolver.');
            return new MigrationAnalysis([], 0, ['TenantResolver is not configured.']);
        }

        $resolver = app(TenantResolver::class);
        $manager = app()->bound(TenantManager::class) ? app(TenantManager::class) : null;
        if (!$resolver instanceof TenantResolver) {
            $this->components->error('Tenant analysis requires a configured TenantResolver.');
            return new MigrationAnalysis([], 0, ['TenantResolver is not configured.']);
        }

        $allRisks = [];
        $errors = [];
        $pending = 0;
        $analyzed = 0;
        $skipped = 0;
        $limit = $this->option('limit') === null ? null : (int) $this->option('limit');
        $tenants = $this->option('tenant') ? array_filter([$resolver->find($this->option('tenant'))]) : $resolver->tenants();

        foreach ($tenants as $index => $tenant) {
            if ($limit !== null && $index >= $limit) {
                $skipped++;
                break;
            }
            try {
                $manager?->activate($tenant);
                $analysis = $analyzer->tenant($tenant, $this->paths(), $this->option('from'));
                $analyzed++;
                array_push($allRisks, ...$analysis->risks);
                array_push($errors, ...$analysis->errors);
                $pending += $analysis->pendingMigrations;
                if ($this->option('fail-fast') && $analysis->highestRisk()->value >= RiskLevel::fromName(config('migration-guard.risk.tenant.fail_on'))->value) {
                    $skipped++;
                    break;
                }
            } catch (\Throwable $exception) {
                $errors[] = "{$tenant->id}: {$exception->getMessage()}";
            } finally {
                if ($manager !== null && config('migration-guard.tenancy.disconnect_after_analysis')) {
                    $manager->disconnect($tenant);
                }
            }
        }

        return new MigrationAnalysis($allRisks, $pending, $errors, $analyzed, $skipped);
    }

    /** @return array<int, string>|null */
    private function paths(): ?array
    {
        $paths = $this->option('path');

        return $paths === [] ? null : array_map(
            static fn (string $path) => preg_match('/^[A-Za-z]:[\\\\\/]|^\//', $path) === 1 ? $path : base_path($path),
            $paths,
        );
    }

    /** @param array<string, MigrationAnalysis> $analyses */
    private function exitCode(array $analyses): int
    {
        foreach ($analyses as $scope => $analysis) {
            if ($analysis->errors !== []) {
                return 5;
            }
            $configScope = $scope === 'tenants' ? 'tenant' : $scope;
            $threshold = RiskLevel::fromName(config("migration-guard.risk.{$configScope}.fail_on", 'critical'));
            if ($analysis->highestRisk()->value >= $threshold->value) {
                return $this->option('ci') ? 1 : self::SUCCESS;
            }
        }

        return self::SUCCESS;
    }

    /** @param array<string, MigrationAnalysis> $analyses
     *  @return array<string, mixed>
     */
    private function json(array $analyses): array
    {
        $result = ['status' => $this->exitCode($analyses) === 0 ? 'passed' : 'failed'];
        foreach ($analyses as $scope => $analysis) {
            $result[$scope] = [
                'risk' => strtolower($analysis->highestRisk()->name),
                'pending_migrations' => $analysis->pendingMigrations,
                'findings' => array_map(static fn ($risk) => $risk->toArray(), $analysis->risks),
                'errors' => $analysis->errors,
                'tenants_analyzed' => $analysis->tenantsAnalyzed,
                'tenants_skipped' => $analysis->tenantsSkipped,
            ];
        }

        return $result;
    }
}
