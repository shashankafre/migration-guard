<?php

namespace MigrationSafe\Laravel\Tests\Unit;

use MigrationSafe\Laravel\Risk\RiskLevel;
use MigrationSafe\Laravel\Tests\TestCase;

final class RiskLevelTest extends TestCase
{
    public function test_it_parses_configured_risk_levels(): void
    {
        self::assertSame(RiskLevel::Critical, RiskLevel::fromName('critical'));
        self::assertSame('HIGH', RiskLevel::High->label());
    }
}
