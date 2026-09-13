<?php

namespace MigrationGuard\Laravel\Tests\Unit;

use MigrationGuard\Laravel\Parsing\BlueprintCollector;
use MigrationGuard\Laravel\Tests\TestCase;

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

    public function test_it_preserves_default_and_column_modifiers(): void
    {
        $collector = new BlueprintCollector($this->app['db']->connection());
        $collector->table('orders', function ($table): void {
            $table->unsignedBigInteger('account_id')->default(1)->comment('Owner');
        });

        $operation = $collector->operations()[0];

        self::assertSame(1, $operation->attributes['default']);
        self::assertTrue($operation->attributes['unsigned']);
        self::assertSame('Owner', $operation->attributes['comment']);
    }
}
