# Migration Guard

Migration Guard analyzes pending Laravel migrations before deployment. It inspects MySQL or MariaDB metadata and reports schema and data risks without applying migrations.

Repository and Composer package: [`shashankafre/migration-guard`](https://github.com/shashankafre/migration-guard)

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- MySQL 8+ or MariaDB 10.6+

## Install

```bash
composer require shashankafre/migration-guard --dev
php artisan vendor:publish --tag=migration-guard-config
php artisan migration:safety
```

Use CI mode to fail when configured risk thresholds are met:

```bash
php artisan migration:safety --ci
php artisan migration:safety --format=json
```

### GitHub Actions

Run Migration Guard after dependencies are installed and before deploying migrations:

```yaml
- name: Check pending migration safety
  run: php artisan migration:safety --ci
```

Use `--format=json` when a later workflow step needs machine-readable results.

## Scopes

Central migrations use `migrations.central.paths`. Enable tenant migration analysis through `migrations.tenant.enabled`, provide a `TenantResolver`, and optionally a `TenantManager` to activate and disconnect each tenant connection.

```php
use MigrationGuard\Laravel\Contracts\TenantResolver;
use MigrationGuard\Laravel\Tenancy\TenantContext;

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
php artisan migration:safety --path=database/migrations/module --from=2026_09_13_000000
```

## Safety Model

The package only uses read queries for metadata and validation. It swaps Laravel's schema and database facades while collecting supported migration operations; it never invokes schema changes itself. PHP migration code is not a sandbox, so unsupported operations and raw SQL are reported for manual review rather than treated as safe.

Current rules detect table/column removal, non-null additions to populated tables, large-table indexes, duplicate values before unique indexes, orphaned values before foreign keys, column changes, renames, raw SQL, and unsupported operations.

## Tenancy and Exceptions

Set `tenancy.resolver` to a `TenantResolver` class or a container binding in `migration-guard.php`. Set `tenancy.manager` to a `TenantManager` when a tenant needs connection activation or explicit cleanup. Neither contract requires a tenancy-package dependency.

Approvals remain visible in console and JSON output but do not block CI. Each requires a migration, rule, and reason:

```php
'approvals' => [[
    'migration' => '2026_09_13_drop_legacy_column',
    'rule' => 'drop_column',
    'reason' => 'The data was archived on 2026-09-01.',
]],
'ignores' => [[
    'migration' => '2026_09_13_drop_legacy_column',
    'rule' => 'drop_column',
    'reason' => 'Temporary deployment exception.',
    'expires_at' => '2026-10-01',
]],
```

Ignored findings also do not block CI. Expired ignores automatically become active findings.

## Development

```bash
composer install
composer test
```
