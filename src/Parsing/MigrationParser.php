<?php

namespace MigrationGuard\Laravel\Parsing;

use MigrationGuard\Laravel\Operations\MigrationOperation;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

final class MigrationParser
{
    /** @return array{operations: array<int, MigrationOperation>, error: ?string} */
    public function parse(string $file): array
    {
        try {
            $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse((string) file_get_contents($file));
            $visitor = new StaticMigrationVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast ?? []);

            return ['operations' => $visitor->operations(), 'error' => null];
        } catch (\Throwable $exception) {
            return ['operations' => [new MigrationOperation('unsupported_operation', attributes: ['api' => 'parser', 'detail' => $exception->getMessage()])], 'error' => $exception->getMessage()];
        }
    }
}

final class StaticMigrationVisitor extends NodeVisitorAbstract
{
    /** @var array<int, MigrationOperation> */
    private array $operations = [];

    private ?string $currentTable = null;

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name) {
            $class = strtolower($node->class->toString());
            $method = $node->name instanceof Node\Identifier ? strtolower($node->name->toString()) : 'dynamic';
            if (in_array($class, ['schema', 'illuminate\\support\\facades\\schema'], true)) {
                $this->schemaCall($method, $node);
            }
            if (in_array($class, ['db', 'illuminate\\support\\facades\\db'], true)) {
                $this->databaseCall($method, $node);
            }
            if (!in_array($class, ['schema', 'illuminate\\support\\facades\\schema', 'db', 'illuminate\\support\\facades\\db'], true)) {
                $this->operations[] = new MigrationOperation('application_side_effect', attributes: ['api' => "{$node->class}::{$method}", 'line' => $node->getStartLine()]);
            }
        }

        if ($node instanceof Node\Expr\MethodCall && $this->isBlueprintCall($node)) {
            $this->blueprintCall($node);
        }

        if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier && in_array($node->name->toString(), ['update', 'delete', 'insert', 'updateOrInsert'], true)) {
            $this->operations[] = new MigrationOperation('data_write', attributes: ['api' => "QueryBuilder::{$node->name}", 'line' => $node->getStartLine()]);
        }

        return null;
    }

    /** @return array<int, MigrationOperation> */
    public function operations(): array
    {
        usort($this->operations, static function (MigrationOperation $left, MigrationOperation $right): int {
            $leftLine = $left->attributes['line'] ?? 0;
            $rightLine = $right->attributes['line'] ?? 0;
            if ($leftLine !== $rightLine) {
                return $leftLine <=> $rightLine;
            }

            $priority = ['create_table' => 0, 'add_column' => 1, 'add_index' => 2, 'add_unique_index' => 2, 'add_foreign_key' => 2];

            return ($priority[$left->type] ?? 3) <=> ($priority[$right->type] ?? 3);
        });

        return $this->operations;
    }

    private function schemaCall(string $method, Node\Expr\StaticCall $node): void
    {
        $table = $this->stringArgument($node, 0);
        if (in_array($method, ['create', 'table'], true)) {
            $this->currentTable = $table;
        }
        $line = $node->getStartLine();
        match ($method) {
            'create' => $this->operations[] = new MigrationOperation('create_table', $table, attributes: ['line' => $line]),
            'table' => null,
            'drop', 'dropifexists' => $this->operations[] = new MigrationOperation('drop_table', $table, attributes: ['line' => $line]),
            'rename' => $this->operations[] = new MigrationOperation('rename_table', $table, attributes: ['to' => $this->stringArgument($node, 1), 'line' => $line]),
            'hastable', 'hascolumn', 'connection' => null,
            default => $this->operations[] = new MigrationOperation('unsupported_operation', $table, attributes: ['api' => "Schema::{$method}", 'line' => $line]),
        };
    }

    private function databaseCall(string $method, Node\Expr\StaticCall $node): void
    {
        $line = $node->getStartLine();
        if (in_array($method, ['statement', 'unprepared'], true)) {
            $this->operations[] = new MigrationOperation('raw_sql', attributes: ['sql' => $this->stringArgument($node, 0) ?? '', 'line' => $line]);
            return;
        }
        if ($method === 'table') {
            $this->operations[] = new MigrationOperation('data_query', $this->stringArgument($node, 0), attributes: ['line' => $line]);
            return;
        }
        if ($method !== 'connection') {
            $this->operations[] = new MigrationOperation('unsupported_operation', attributes: ['api' => "DB::{$method}", 'line' => $line]);
        }
    }

    private function blueprintCall(Node\Expr\MethodCall $node): void
    {
        $method = $node->name instanceof Node\Identifier ? $node->name->toString() : 'dynamic';
        $column = $this->stringArgument($node, 0);
        $line = $node->getStartLine();
        $columns = $this->stringArrayArgument($node, 0);
        $operation = match ($method) {
            'dropColumn' => new MigrationOperation('drop_column', $this->currentTable, $column, $columns, attributes: ['line' => $line]),
            'renameColumn' => new MigrationOperation('rename_column', $this->currentTable, $column, attributes: ['to' => $this->stringArgument($node, 1), 'line' => $line]),
            'index' => new MigrationOperation('add_index', $this->currentTable, columns: $columns ?: array_filter([$column]), attributes: ['line' => $line]),
            'unique' => new MigrationOperation('add_unique_index', $this->currentTable, columns: $columns ?: array_filter([$column]), attributes: ['line' => $line]),
            'foreign' => new MigrationOperation('add_foreign_key', $this->currentTable, $column, attributes: ['line' => $line]),
            'primary', 'dropIndex', 'dropUnique', 'dropForeign', 'morphs', 'nullableMorphs' => new MigrationOperation('schema_definition', $this->currentTable, attributes: ['api' => "Blueprint::{$method}", 'line' => $line]),
            'nullable', 'default', 'unsigned', 'after', 'comment', 'change', 'constrained', 'cascadeOnDelete', 'nullOnDelete', 'onDelete', 'references', 'on', 'virtualAs', 'storedAs', 'charset' => new MigrationOperation('schema_definition', $this->currentTable, attributes: ['api' => "Blueprint::{$method}", 'line' => $line]),
            default => $this->columnOrUnsupported($method, $column, $line),
        };
        $this->operations[] = $operation;
    }

    private function columnOrUnsupported(string $method, ?string $column, int $line): MigrationOperation
    {
        $columns = ['string', 'text', 'longText', 'integer', 'bigInteger', 'unsignedBigInteger', 'foreignId', 'boolean', 'json', 'jsonb', 'uuid', 'timestamp', 'dateTime', 'decimal', 'float'];
        if (in_array($method, $columns, true)) {
            return new MigrationOperation('add_column', $this->currentTable, $column, columnType: $method, nullable: false, attributes: ['line' => $line]);
        }

        return new MigrationOperation('unsupported_operation', $this->currentTable, attributes: ['api' => "Blueprint::{$method}", 'line' => $line]);
    }

    private function isBlueprintCall(Node\Expr\MethodCall $node): bool
    {
        $target = $node->var;
        while ($target instanceof Node\Expr\MethodCall) {
            $target = $target->var;
        }

        return $target instanceof Node\Expr\Variable && $target->name === 'table';
    }

    private function stringArgument(Node\Expr $node, int $position): ?string
    {
        $argument = $node->args[$position]->value ?? null;

        return $argument instanceof Node\Scalar\String_ ? $argument->value : null;
    }

    /** @return array<int, string> */
    private function stringArrayArgument(Node\Expr $node, int $position): array
    {
        $argument = $node->args[$position]->value ?? null;
        if (!$argument instanceof Node\Expr\Array_) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (Node\Expr\ArrayItem $item) => $item->value instanceof Node\Scalar\String_ ? $item->value->value : null, $argument->items)));
    }
}
