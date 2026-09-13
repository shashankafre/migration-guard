<?php

namespace MigrationSafe\Laravel\Parsing;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use MigrationSafe\Laravel\Operations\MigrationOperation;

final class BlueprintCollector
{
    /** @var array<int, MigrationOperation> */
    private array $operations = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $table, \Closure $callback): void
    {
        $this->operations[] = new MigrationOperation('create_table', $table);
        $callback(new CapturedBlueprint($this->connection, $table, $this));
    }

    public function table(string $table, \Closure $callback): void
    {
        $callback(new CapturedBlueprint($this->connection, $table, $this));
    }

    public function add(MigrationOperation $operation): int
    {
        $this->operations[] = $operation;

        return array_key_last($this->operations);
    }

    public function replace(int $index, MigrationOperation $operation): void
    {
        $this->operations[$index] = $operation;
    }

    /** @return array<int, MigrationOperation> */
    public function operations(): array
    {
        return $this->operations;
    }
}

final class CapturedBlueprint extends Blueprint
{
    public function __construct(Connection $connection, private readonly string $capturedTable, private readonly BlueprintCollector $collector)
    {
        if ($connection->getSchemaGrammar() === null) {
            $connection->useDefaultSchemaGrammar();
        }
        parent::__construct($connection, $capturedTable);
    }

    public function addColumn($type, $name, array $parameters = [])
    {
        $operation = new MigrationOperation('add_column', $this->capturedTable, (string) $name, columnType: (string) $type, nullable: false);
        $index = $this->collector->add($operation);

        return new CapturedColumn($operation, $index, $this->collector);
    }

    public function drop()
    {
        $this->collector->add(new MigrationOperation('drop_table', $this->capturedTable));
    }

    public function dropColumn($columns)
    {
        foreach ((array) $columns as $column) {
            $this->collector->add(new MigrationOperation('drop_column', $this->capturedTable, (string) $column));
        }
    }

    public function renameColumn($from, $to)
    {
        $this->collector->add(new MigrationOperation('rename_column', $this->capturedTable, (string) $from, attributes: ['to' => $to]));
    }

    public function index($columns, $name = null, $algorithm = null)
    {
        $this->collector->add(new MigrationOperation('add_index', $this->capturedTable, columns: (array) $columns));
    }

    public function unique($columns, $name = null, $algorithm = null)
    {
        $this->collector->add(new MigrationOperation('add_unique_index', $this->capturedTable, columns: (array) $columns));
    }

    public function foreign($columns, $name = null)
    {
        return new CapturedForeignKey($this->capturedTable, (string) $columns, $this->collector);
    }

    public function __call($method, $arguments)
    {
        $this->collector->add(new MigrationOperation('unknown', $this->capturedTable, attributes: ['method' => $method]));

        return $this;
    }
}

final class CapturedColumn
{
    public function __construct(private MigrationOperation $operation, private readonly int $index, private readonly BlueprintCollector $collector)
    {
    }

    public function __call(string $method, array $arguments): self
    {
        if ($method === 'nullable') {
            $this->operation = new MigrationOperation(
                $this->operation->type,
                $this->operation->table,
                $this->operation->column,
                $this->operation->columns,
                true,
                $this->operation->columnType,
                $this->operation->attributes,
            );
            $this->collector->replace($this->index, $this->operation);
        }

        if ($method === 'unique') {
            $this->collector->add(new MigrationOperation('add_unique_index', $this->operation->table, columns: [$this->operation->column]));
        }

        if ($method === 'index') {
            $this->collector->add(new MigrationOperation('add_index', $this->operation->table, columns: [$this->operation->column]));
        }

        if ($method === 'change') {
            $this->collector->add(new MigrationOperation('change_column', $this->operation->table, $this->operation->column, columnType: $this->operation->columnType));
        }

        return $this;
    }
}

final class CapturedForeignKey
{
    private ?string $referencedColumn = null;

    public function __construct(private readonly string $table, private readonly string $column, private readonly BlueprintCollector $collector)
    {
    }

    public function references(string $column): self
    {
        $this->referencedColumn = $column;

        return $this;
    }

    public function on(string $table): self
    {
        $this->collector->add(new MigrationOperation('add_foreign_key', $this->table, $this->column, attributes: [
            'referenced_table' => $table,
            'referenced_column' => $this->referencedColumn ?? 'id',
        ]));

        return $this;
    }

    public function __call(string $method, array $arguments): self
    {
        return $this;
    }
}
