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
}
