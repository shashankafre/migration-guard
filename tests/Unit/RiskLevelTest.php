<?php

namespace MigrationGuard\Laravel\Tests\Unit;

use MigrationGuard\Laravel\Risk\RiskLevel;
use MigrationGuard\Laravel\Tests\TestCase;

final class RiskLevelTest extends TestCase
{
    public function test_it_parses_configured_risk_levels(): void
    {
        self::assertSame(RiskLevel::Critical, RiskLevel::fromName('critical'));
        self::assertSame('HIGH', RiskLevel::High->label());
    }
}
