<?php

namespace MigrationSafe\Laravel\Database;

use Illuminate\Database\ConnectionResolverInterface;

final class DatabaseInspector
{
    public function __construct(private readonly ConnectionResolverInterface $connections)
    {
    }

    public function estimatedRows(string $table, ?string $connection = null): ?int
    {
        try {
            $database = $this->connections->connection($connection);
            $name = $database->getDatabaseName();
            $result = $database->selectOne(
                'SELECT TABLE_ROWS AS rows FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$name, $table],
            );

            return $result === null ? null : (int) $result->rows;
        } catch (\Throwable) {
            return null;
        }
    }

    public function duplicateGroups(string $table, array $columns, ?string $connection = null): ?int
    {
        if ($columns === []) {
            return null;
        }

        try {
            $database = $this->connections->connection($connection);
            $grammar = $database->getQueryGrammar();
            $wrapped = array_map($grammar->wrap(...), $columns);
            $groups = implode(', ', $wrapped);
            $query = sprintf('SELECT COUNT(*) AS aggregate FROM (SELECT %s FROM %s GROUP BY %s HAVING COUNT(*) > 1) AS duplicates', $groups, $grammar->wrapTable($table), $groups);

            return (int) $database->selectOne($query)->aggregate;
        } catch (\Throwable) {
            return null;
        }
    }

    public function orphanedRows(string $table, string $column, string $referencedTable, string $referencedColumn, ?string $connection = null): ?int
    {
        try {
            $database = $this->connections->connection($connection);
            $grammar = $database->getQueryGrammar();
            $query = sprintf(
                'SELECT COUNT(*) AS aggregate FROM %s AS source LEFT JOIN %s AS target ON source.%s = target.%s WHERE source.%s IS NOT NULL AND target.%s IS NULL',
                $grammar->wrapTable($table), $grammar->wrapTable($referencedTable), $grammar->wrap($column), $grammar->wrap($referencedColumn), $grammar->wrap($column), $grammar->wrap($referencedColumn),
            );

            return (int) $database->selectOne($query)->aggregate;
        } catch (\Throwable) {
            return null;
        }
    }
}
