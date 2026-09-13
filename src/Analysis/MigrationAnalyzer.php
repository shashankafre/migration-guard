<?php

namespace MigrationGuard\Laravel\Analysis;

use MigrationGuard\Laravel\Discovery\MigrationDiscovery;
use MigrationGuard\Laravel\Exceptions\FindingExceptionMatcher;
use MigrationGuard\Laravel\Parsing\MigrationParser;
use MigrationGuard\Laravel\Rules\SafetyRuleEngine;
use MigrationGuard\Laravel\Scope\MigrationScope;
use MigrationGuard\Laravel\Tenancy\TenantContext;

final class MigrationAnalyzer
{
    public function __construct(
        private readonly MigrationDiscovery $discovery,
        private readonly MigrationParser $parser,
        private readonly SafetyRuleEngine $rules,
        private readonly FindingExceptionMatcher $exceptions,
    ) {
    }

    /** @param array<int, string>|null $paths */
    public function central(?array $paths = null, ?string $from = null): MigrationAnalysis
    {
        $config = config('migration-guard.migrations.central');

        return $this->analyze(
            new AnalysisContext(MigrationScope::Central, $config['connection'] ?: config('database.default')),
            $paths ?? $config['paths'],
            $from,
        );
    }

    /** @param array<int, string>|null $paths */
    public function tenant(TenantContext $tenant, ?array $paths = null, ?string $from = null): MigrationAnalysis
    {
        return $this->analyze(
            new AnalysisContext(MigrationScope::Tenant, $tenant->connection, $tenant),
            $paths ?? config('migration-guard.migrations.tenant.paths'),
            $from,
        );
    }

    /** @param array<int, string> $paths */
    private function analyze(AnalysisContext $context, array $paths, ?string $from): MigrationAnalysis
    {
        $risks = [];
        $errors = [];
        $pending = $this->discovery->pending($paths, $context->connection, $from);

        foreach ($pending as $name => $file) {
            $parsed = $this->parser->parse($file);
            if ($parsed['error'] !== null) {
                $errors[] = "{$name}: {$parsed['error']}";
            }

            foreach ($parsed['operations'] as $operation) {
                foreach ($this->rules->analyze($operation, $context, $name) as $risk) {
                    $risks[] = $this->exceptions->apply($risk->withFile($file));
                }
            }
        }

        return new MigrationAnalysis($risks, count($pending), $errors);
    }
}
