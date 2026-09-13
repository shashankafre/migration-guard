<?php

namespace MigrationGuard\Laravel\Analysis;

use MigrationGuard\Laravel\Risk\RiskLevel;
use MigrationGuard\Laravel\Risk\RiskResult;

final readonly class MigrationAnalysis
{
    /** @param array<int, RiskResult> $risks */
    public function __construct(
        public array $risks,
        public int $pendingMigrations,
        public array $errors = [],
        public int $tenantsAnalyzed = 0,
        public int $tenantsSkipped = 0,
    ) {
    }

    public function highestRisk(): RiskLevel
    {
        $active = array_filter($this->risks, static fn (RiskResult $risk) => $risk->status === 'active');

        return empty($active)
            ? RiskLevel::Low
            : RiskLevel::from(max(array_map(static fn (RiskResult $risk) => $risk->level->value, $active)));
    }
}
