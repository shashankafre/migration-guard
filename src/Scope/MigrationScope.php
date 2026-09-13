<?php

namespace MigrationGuard\Laravel\Scope;

enum MigrationScope: string
{
    case Central = 'central';
    case Tenant = 'tenant';
}
