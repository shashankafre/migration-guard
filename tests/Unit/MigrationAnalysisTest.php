<?php

namespace MigrationGuard\Laravel\Tests\Unit;

use MigrationGuard\Laravel\Analysis\MigrationAnalysis;
use MigrationGuard\Laravel\Risk\RiskLevel;
use MigrationGuard\Laravel\Risk\RiskResult;
use MigrationGuard\Laravel\Scope\MigrationScope;
use MigrationGuard\Laravel\Tests\TestCase;

final class MigrationAnalysisTest extends TestCase
{
    public function test_approved_findings_do_not_increase_the_blocking_risk(): void
    {
        $risk = new RiskResult(RiskLevel::Critical, 'drop_table', 'Drops data.', 'Back up data.', MigrationScope::Central, 'drop_users');

        self::assertSame(RiskLevel::Low, new MigrationAnalysis([$risk->withStatus('approved', 'Archived.')], 1)->highestRisk());
    }
}
