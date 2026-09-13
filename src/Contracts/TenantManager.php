<?php

namespace MigrationSafe\Laravel\Contracts;

use MigrationSafe\Laravel\Tenancy\TenantContext;

interface TenantManager
{
    public function activate(TenantContext $tenant): void;

    public function disconnect(TenantContext $tenant): void;
}
