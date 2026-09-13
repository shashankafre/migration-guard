<?php

namespace MigrationSafe\Laravel\Analysis;

use MigrationSafe\Laravel\Scope\MigrationScope;
use MigrationSafe\Laravel\Tenancy\TenantContext;

final readonly class AnalysisContext
{
    public function __construct(
        public MigrationScope $scope,
        public string $connection,
        public ?TenantContext $tenant = null,
    ) {
    }
}
