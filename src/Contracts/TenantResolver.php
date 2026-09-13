<?php

namespace MigrationGuard\Laravel\Contracts;

use MigrationGuard\Laravel\Tenancy\TenantContext;

interface TenantResolver
{
    /** @return iterable<TenantContext> */
    public function tenants(): iterable;

    public function find(string $id): ?TenantContext;
}
