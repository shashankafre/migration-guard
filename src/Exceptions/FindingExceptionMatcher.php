<?php

namespace MigrationGuard\Laravel\Exceptions;

use Carbon\CarbonImmutable;
use MigrationGuard\Laravel\Risk\RiskResult;

final class FindingExceptionMatcher
{
    public function apply(RiskResult $risk): RiskResult
    {
        foreach (config('migration-guard.ignores', []) as $ignore) {
            if ($this->matches($ignore, $risk) && $this->isActive($ignore)) {
                return $risk->withStatus('ignored', $ignore['reason'] ?? 'Ignored by configuration.');
            }
        }

        foreach (config('migration-guard.approvals', []) as $approval) {
            if ($this->matches($approval, $risk) && !empty($approval['reason'])) {
                return $risk->withStatus('approved', $approval['reason']);
            }
        }

        return $risk;
    }

    /** @param array<string, mixed> $exception */
    private function matches(array $exception, RiskResult $risk): bool
    {
        return (!isset($exception['migration']) || $exception['migration'] === $risk->migration)
            && (!isset($exception['rule']) || $exception['rule'] === $risk->rule);
    }

    /** @param array<string, mixed> $ignore */
    private function isActive(array $ignore): bool
    {
        return !isset($ignore['expires_at']) || CarbonImmutable::parse($ignore['expires_at'])->endOfDay()->isFuture();
    }
}
