<?php

namespace MigrationGuard\Laravel\Tests\Unit;

use MigrationGuard\Laravel\Exceptions\FindingExceptionMatcher;
use MigrationGuard\Laravel\Risk\RiskLevel;
use MigrationGuard\Laravel\Risk\RiskResult;
use MigrationGuard\Laravel\Scope\MigrationScope;
use MigrationGuard\Laravel\Tests\TestCase;

final class FindingExceptionMatcherTest extends TestCase
{
    public function test_it_marks_a_matching_approval_without_affecting_the_finding(): void
    {
        config()->set('migration-guard.approvals', [[
            'migration' => '2026_01_01_drop_users',
            'rule' => 'drop_table',
            'reason' => 'Data was archived.',
        ]]);

        $result = $this->app->make(FindingExceptionMatcher::class)->apply($this->risk());

        self::assertSame('approved', $result->status);
        self::assertSame('Data was archived.', $result->exceptionReason);
    }

    public function test_an_expired_ignore_does_not_apply(): void
    {
        config()->set('migration-guard.ignores', [[
            'rule' => 'drop_table',
            'expires_at' => '2020-01-01',
        ]]);

        self::assertSame('active', $this->app->make(FindingExceptionMatcher::class)->apply($this->risk())->status);
    }

    private function risk(): RiskResult
    {
        return new RiskResult(RiskLevel::Critical, 'drop_table', 'Drops data.', 'Back up data.', MigrationScope::Central, '2026_01_01_drop_users', 'users');
    }
}
