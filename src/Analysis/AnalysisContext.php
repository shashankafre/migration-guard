<?php

namespace MigrationGuard\Laravel\Analysis;

use MigrationGuard\Laravel\Scope\MigrationScope;
use MigrationGuard\Laravel\Tenancy\TenantContext;

final readonly class AnalysisContext
{
    public function __construct(
        public MigrationScope $scope,
        public string $connection,
        public ?TenantContext $tenant = null,
    ) {
    }
}
