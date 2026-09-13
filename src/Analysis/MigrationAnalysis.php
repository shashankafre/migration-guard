<?php

namespace MigrationSafe\Laravel\Analysis;

use MigrationSafe\Laravel\Risk\RiskLevel;
use MigrationSafe\Laravel\Risk\RiskResult;

final readonly class MigrationAnalysis
{
    /** @param array<int, RiskResult> $risks */
    public function __construct(
        public array $risks,
        public int $pendingMigrations,
        public array $errors = [],
    ) {
    }

    public function highestRisk(): RiskLevel
    {
        return empty($this->risks)
            ? RiskLevel::Low
            : RiskLevel::from(max(array_map(static fn (RiskResult $risk) => $risk->level->value, $this->risks)));
    }
}
