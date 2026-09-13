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
        'raw_sql' => true,
    ],

    'tenancy' => [
        'resolver' => null,
        'disconnect_after_analysis' => true,
    ],

    'analysis' => [
        'query_timeout_seconds' => 5,
        'duplicate_sample_limit' => 10,
        'orphan_sample_limit' => 10,
    ],
];
