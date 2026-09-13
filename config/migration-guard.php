<?php

return [
    'enabled' => env('MIGRATION_SAFETY_ENABLED', true),

    'migrations' => [
        'central' => [
            'enabled' => true,
            'paths' => [database_path('migrations')],
            'connection' => null,
        ],
        'tenant' => [
            'enabled' => false,
            'paths' => [database_path('migrations/tenant')],
        ],
    ],

    'risk' => [
        'central' => ['fail_on' => 'high'],
        'tenant' => ['fail_on' => 'critical'],
    ],

    'thresholds' => [
        'large_table_rows' => 1_000_000,
        'very_large_table_rows' => 10_000_000,
        'large_table_size_mb' => 1024,
    ],

    'rules' => [
        'drop_table' => true,
        'drop_column' => true,
        'add_not_null_column' => true,
        'large_table_index' => true,
        'unique_constraint' => true,
        'foreign_key' => true,
        'column_change' => true,
        'rename_column' => true,
        'raw_sql' => true,
    ],

    'tenancy' => [
        // Bind a TenantResolver class name or service-container binding.
        'resolver' => null,
        // Optionally bind a TenantManager class name or service-container binding.
        'manager' => null,
        'disconnect_after_analysis' => true,
    ],

    'analysis' => [
        'query_timeout_seconds' => 5,
        'duplicate_sample_limit' => 10,
        'orphan_sample_limit' => 10,
        'max_validation_rows' => null,
    ],

    // Each entry requires migration, rule, and reason. An approved finding is reported but does not block CI.
    'approvals' => [],

    // Entries may set migration and/or rule, plus an optional ISO-8601 expires_at date.
    'ignores' => [],
];
