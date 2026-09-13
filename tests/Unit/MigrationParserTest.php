<?php

namespace MigrationGuard\Laravel\Tests\Unit;

use MigrationGuard\Laravel\Parsing\MigrationParser;
use MigrationGuard\Laravel\Tests\TestCase;

final class MigrationParserTest extends TestCase
{
    public function test_it_intercepts_schema_operations_without_running_them(): void
    {
        $result = $this->app->make(MigrationParser::class)->parse(__DIR__.'/../Fixtures/add_reference_migration.php');

        self::assertNull($result['error']);
        self::assertSame(['add_column', 'add_unique_index', 'drop_column'], array_map(
            static fn ($operation) => $operation->type,
            $result['operations'],
        ));
    }

    public function test_it_reports_the_exact_unsupported_api_without_executing_a_named_migration(): void
    {
        $result = $this->app->make(MigrationParser::class)->parse(__DIR__.'/../Fixtures/static_unsupported_migration.php');

        self::assertNull($result['error']);
        self::assertSame('unsupported_operation', $result['operations'][0]->type);
        self::assertSame('Schema::mystery', $result['operations'][0]->attributes['api']);
        self::assertSame(11, $result['operations'][0]->attributes['line']);
    }
}
