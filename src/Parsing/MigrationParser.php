<?php

namespace MigrationGuard\Laravel\Parsing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MigrationGuard\Laravel\Operations\MigrationOperation;

final class MigrationParser
{
    /** @return array{operations: array<int, MigrationOperation>, error: ?string} */
    public function parse(string $file): array
    {
        $schema = Schema::getFacadeRoot();
        $database = DB::getFacadeRoot();
        $collector = new BlueprintCollector($database->connection());

        try {
            Schema::swap(new CapturedSchema($collector));
            DB::swap(new CapturedDatabase($collector));
            $migration = require $file;

            if (!is_object($migration) || !method_exists($migration, 'up')) {
                return ['operations' => [new MigrationOperation('unknown')], 'error' => 'Migration must return an object with an up method.'];
            }

            $migration->up();

            return ['operations' => $collector->operations(), 'error' => null];
        } catch (\Throwable $exception) {
            return ['operations' => [new MigrationOperation('unknown')], 'error' => $exception->getMessage()];
        } finally {
            Schema::swap($schema);
            DB::swap($database);
        }
    }
}

final class CapturedSchema
{
    public function __construct(private readonly BlueprintCollector $collector)
    {
    }

    public function create(string $table, \Closure $callback): void { $this->collector->create($table, $callback); }
    public function table(string $table, \Closure $callback): void { $this->collector->table($table, $callback); }
    public function drop(string $table): void { $this->collector->add(new MigrationOperation('drop_table', $table)); }
    public function dropIfExists(string $table): void { $this->collector->add(new MigrationOperation('drop_table', $table)); }
    public function rename(string $from, string $to): void { $this->collector->add(new MigrationOperation('rename_table', $from, attributes: ['to' => $to])); }
}

final class CapturedDatabase
{
    public function __construct(private readonly BlueprintCollector $collector)
    {
    }

    public function statement(string $sql): bool { $this->raw($sql); return true; }
    public function unprepared(string $sql): bool { $this->raw($sql); return true; }
    public function connection(?string $name = null): self { return $this; }
    public function __call(string $method, array $arguments): self { $this->collector->add(new MigrationOperation('unknown', attributes: ['database_method' => $method])); return $this; }

    private function raw(string $sql): void
    {
        $this->collector->add(new MigrationOperation('raw_sql', attributes: ['sql' => $sql]));
    }
}
