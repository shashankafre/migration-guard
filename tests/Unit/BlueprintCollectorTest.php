<?php

namespace MigrationSafe\Laravel\Tests\Unit;

use MigrationSafe\Laravel\Parsing\BlueprintCollector;
use MigrationSafe\Laravel\Tests\TestCase;

final class BlueprintCollectorTest extends TestCase
{
    public function test_it_collects_common_schema_operations(): void
    {
        $collector = new BlueprintCollector($this->app['db']->connection());
        $collector->table('orders', function ($table): void {
            $table->string('reference')->nullable()->unique();
            $table->dropColumn('legacy_reference');
        });

        $operations = $collector->operations();

        self::assertSame('add_column', $operations[0]->type);
        self::assertTrue($operations[0]->nullable);
        self::assertSame('add_unique_index', $operations[1]->type);
        self::assertSame('drop_column', $operations[2]->type);
    }
}
