<?php

namespace MigrationGuard\Laravel\Database;

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

    public function estimatedSizeMb(string $table, ?string $connection = null): ?float
    {
        try {
            $database = $this->connections->connection($connection);
            $result = $database->selectOne(
                'SELECT (DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 AS size_mb FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$database->getDatabaseName(), $table],
            );

            return $result === null ? null : (float) $result->size_mb;
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
            if (!$this->canValidate($database, $table, $connection)) {
                return null;
            }
            $this->setQueryTimeout($database);
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
            if (!$this->canValidate($database, $table, $connection)) {
                return null;
            }
            $this->setQueryTimeout($database);
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

    private function canValidate($database, string $table, ?string $connection): bool
    {
        $maximum = config('migration-guard.analysis.max_validation_rows');

        return $maximum === null || ($this->estimatedRows($table, $connection) ?? PHP_INT_MAX) <= $maximum;
    }

    private function setQueryTimeout($database): void
    {
        $seconds = (int) config('migration-guard.analysis.query_timeout_seconds', 0);
        if ($seconds > 0 && $database->getDriverName() === 'mysql') {
            $database->unprepared('SET SESSION MAX_EXECUTION_TIME = '.($seconds * 1000));
        }
    }
}
