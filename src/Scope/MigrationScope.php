<?php

namespace MigrationSafe\Laravel\Scope;

enum MigrationScope: string
{
    case Central = 'central';
    case Tenant = 'tenant';
}
