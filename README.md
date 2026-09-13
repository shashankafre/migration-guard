# MigrationSafe for Laravel

MigrationSafe analyzes pending Laravel migrations before deployment. It inspects MySQL or MariaDB metadata and reports schema and data risks without applying migrations.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- MySQL 8+ or MariaDB 10.6+

## Install

```bash
composer require migrationsafe/laravel --dev
php artisan vendor:publish --tag=migration-safety-config
php artisan migration:safety
```

Use CI mode to fail when configured risk thresholds are met:

```bash
php artisan migration:safety --ci
php artisan migration:safety --format=json
```

## Scopes

Central migrations use `migrations.central.paths`. Enable tenant migration analysis through `migrations.tenant.enabled`, provide a `TenantResolver`, and optionally a `TenantManager` to activate and disconnect each tenant connection.

```php
use MigrationSafe\Laravel\Contracts\TenantResolver;
use MigrationSafe\Laravel\Tenancy\TenantContext;

$this->app->bind(TenantResolver::class, function (): TenantResolver {
    return new class implements TenantResolver {
        public function tenants(): iterable { /* yield TenantContext instances */ }
        public function find(string $id): ?TenantContext { /* find one tenant */ }
    };
});
```

Commands:

```text
php artisan migration:safety
php artisan migration:safety --central
php artisan migration:safety --tenants
php artisan migration:safety --tenant=tenant_123
php artisan migration:safety --tenants --limit=500 --fail-fast --ci
```

## Safety Model

The package only uses read queries for metadata and validation. It swaps Laravel's schema and database facades while collecting supported migration operations; it never invokes schema changes itself. PHP migration code is not a sandbox, so unsupported operations and raw SQL are reported for manual review rather than treated as safe.

Current rules detect table/column removal, non-null additions to populated tables, large-table indexes, duplicate values before unique indexes, orphaned values before foreign keys, column changes, renames, raw SQL, and unsupported operations.

## Development

```bash
composer install
composer test
```
