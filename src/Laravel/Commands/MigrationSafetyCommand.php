<?php

namespace MigrationSafe\Laravel\Laravel\Commands;

use Illuminate\Console\Command;
use MigrationSafe\Laravel\Analysis\MigrationAnalysis;
use MigrationSafe\Laravel\Analysis\MigrationAnalyzer;
use MigrationSafe\Laravel\Contracts\TenantManager;
use MigrationSafe\Laravel\Contracts\TenantResolver;
use MigrationSafe\Laravel\Reports\ConsoleReporter;
use MigrationSafe\Laravel\Risk\RiskLevel;

final class MigrationSafetyCommand extends Command
{
    protected $signature = 'migration:safety
        {--central : Analyze central migrations only}
        {--tenants : Analyze tenant migrations only}
        {--tenant= : Analyze one tenant by ID}
        {--ci : Return a non-zero code when the configured threshold is reached}
        {--format=console : Output format: console or json}
        {--limit= : Maximum tenants to analyze}
        {--fail-fast : Stop tenant analysis at the configured blocking threshold}';

    protected $description = 'Analyze pending migrations for deployment safety risks';

    public function handle(MigrationAnalyzer $analyzer, ConsoleReporter $reporter): int
    {
        if (!config('migration-safety.enabled')) {
            $this->components->info('Migration safety analysis is disabled.');
            return self::SUCCESS;
        }

        if ($this->option('central') && $this->option('tenants')) {
            $this->components->error('Choose either --central or --tenants, not both.');
            return 4;
        }

        $analyses = [];
        $tenantRequested = $this->option('tenants') || $this->option('tenant') !== null;
        if (!$tenantRequested && config('migration-safety.migrations.central.enabled')) {
            $analyses['central'] = $analyzer->central();
        }

        if (!$this->option('central') && config('migration-safety.migrations.tenant.enabled')) {
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
        $resolver = app(TenantResolver::class);
        $manager = app()->bound(TenantManager::class) ? app(TenantManager::class) : null;
        if (!$resolver instanceof TenantResolver) {
            $this->components->error('Tenant analysis requires a configured TenantResolver.');
            return new MigrationAnalysis([], 0, ['TenantResolver is not configured.']);
        }

        $allRisks = [];
        $errors = [];
        $pending = 0;
        $limit = $this->option('limit') === null ? null : (int) $this->option('limit');
        $tenants = $this->option('tenant') ? array_filter([$resolver->find($this->option('tenant'))]) : $resolver->tenants();

        foreach ($tenants as $index => $tenant) {
            if ($limit !== null && $index >= $limit) {
                break;
            }
            try {
                $manager?->activate($tenant);
                $analysis = $analyzer->tenant($tenant);
                array_push($allRisks, ...$analysis->risks);
                array_push($errors, ...$analysis->errors);
                $pending += $analysis->pendingMigrations;
                if ($this->option('fail-fast') && $analysis->highestRisk()->value >= RiskLevel::fromName(config('migration-safety.risk.tenant.fail_on'))->value) {
                    break;
                }
            } finally {
                if ($manager !== null && config('migration-safety.tenancy.disconnect_after_analysis')) {
                    $manager->disconnect($tenant);
                }
            }
        }

        return new MigrationAnalysis($allRisks, $pending, $errors);
    }

    /** @param array<string, MigrationAnalysis> $analyses */
    private function exitCode(array $analyses): int
    {
        foreach ($analyses as $scope => $analysis) {
            if ($analysis->errors !== []) {
                return 5;
            }
            $configScope = $scope === 'tenants' ? 'tenant' : $scope;
            $threshold = RiskLevel::fromName(config("migration-safety.risk.{$configScope}.fail_on", 'critical'));
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
            ];
        }

        return $result;
    }
}
