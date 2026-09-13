<?php

namespace MigrationGuard\Laravel\Contracts;

use MigrationGuard\Laravel\Tenancy\TenantContext;

interface TenantManager
{
    public function activate(TenantContext $tenant): void;

    public function disconnect(TenantContext $tenant): void;
}
