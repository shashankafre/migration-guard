<?php

namespace MigrationSafe\Laravel\Analysis;

use MigrationSafe\Laravel\Discovery\MigrationDiscovery;
use MigrationSafe\Laravel\Parsing\MigrationParser;
use MigrationSafe\Laravel\Rules\SafetyRuleEngine;
use MigrationSafe\Laravel\Scope\MigrationScope;
use MigrationSafe\Laravel\Tenancy\TenantContext;

final class MigrationAnalyzer
{
    public function __construct(
        private readonly MigrationDiscovery $discovery,
        private readonly MigrationParser $parser,
        private readonly SafetyRuleEngine $rules,
    ) {
    }

    public function central(): MigrationAnalysis
    {
        $config = config('migration-safety.migrations.central');

        return $this->analyze(
            new AnalysisContext(MigrationScope::Central, $config['connection'] ?: config('database.default')),
            $config['paths'],
        );
    }

    public function tenant(TenantContext $tenant): MigrationAnalysis
    {
        return $this->analyze(
            new AnalysisContext(MigrationScope::Tenant, $tenant->connection, $tenant),
            config('migration-safety.migrations.tenant.paths'),
        );
    }

    /** @param array<int, string> $paths */
    private function analyze(AnalysisContext $context, array $paths): MigrationAnalysis
    {
        $risks = [];
        $errors = [];
        $pending = $this->discovery->pending($paths, $context->connection);

        foreach ($pending as $name => $file) {
            $parsed = $this->parser->parse($file);
            if ($parsed['error'] !== null) {
                $errors[] = "{$name}: {$parsed['error']}";
            }

            foreach ($parsed['operations'] as $operation) {
                array_push($risks, ...$this->rules->analyze($operation, $context, $name));
            }
        }

        return new MigrationAnalysis($risks, count($pending), $errors);
    }
}
