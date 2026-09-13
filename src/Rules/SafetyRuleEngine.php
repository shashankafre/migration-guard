<?php

namespace MigrationGuard\Laravel\Rules;

use MigrationGuard\Laravel\Analysis\AnalysisContext;
use MigrationGuard\Laravel\Database\DatabaseInspector;
use MigrationGuard\Laravel\Operations\MigrationOperation;
use MigrationGuard\Laravel\Risk\RiskLevel;
use MigrationGuard\Laravel\Risk\RiskResult;

final class SafetyRuleEngine
{
    public function __construct(private readonly DatabaseInspector $inspector)
    {
    }

    /** @return array<int, RiskResult> */
    public function analyze(MigrationOperation $operation, AnalysisContext $context, string $migration): array
    {
        $tableRows = $operation->table === null ? null : $this->inspector->estimatedRows($operation->table, $context->connection);
        $enabled = config('migration-guard.rules');
        $risk = fn (RiskLevel $level, string $rule, string $reason, string $recommendation) => new RiskResult($level, $rule, $reason, $recommendation, $context->scope, $migration, $operation->table, $operation->column, $context->tenant?->id);

        return match ($operation->type) {
            'create_table' => [$risk(RiskLevel::Low, 'create_table', 'Creates a new table.', 'Verify the table definition and deployment capacity.')],
            'drop_table' => $enabled['drop_table'] ? [$risk(RiskLevel::Critical, 'drop_table', 'Drops a database table and all of its data.', 'Back up the data and use an acknowledged migration if intentional.')] : [],
            'drop_column' => $enabled['drop_column'] ? [$risk(RiskLevel::Critical, 'drop_column', 'Drops a column that may contain production data.', 'Back up or migrate the data before removing the column.')] : [],
            'add_column' => $this->columnRisk($operation, $tableRows, $enabled, $risk),
            'add_index' => $this->existingTable($operation) ? $this->indexRisk($operation, $tableRows, $risk, 'large_table_index', 'Adds an index to a large table.', $context->connection) : [$risk(RiskLevel::Low, 'add_index', 'Adds an index to a newly created table.', 'Verify the index definition.')],
            'add_unique_index' => $this->uniqueRisk($operation, $context, $migration, $risk),
            'add_foreign_key' => $this->foreignKeyRisk($operation, $context, $migration, $risk),
            'change_column' => $enabled['column_change'] ? [$risk(RiskLevel::High, 'column_change', 'Changes an existing column definition.', 'Validate conversion compatibility and use an online migration strategy where needed.')] : [],
            'rename_column' => $enabled['rename_column'] ? [$risk(RiskLevel::Medium, 'rename_column', 'Renames an existing column.', 'Deploy compatible application code before removing references to the old name.')] : [],
            'rename_table' => [$risk(RiskLevel::High, 'rename_table', 'Renames an existing table.', 'Deploy compatible application code before removing references to the old table name.')],
            'drop_index', 'drop_unique_index', 'drop_foreign_key' => [$risk(RiskLevel::Medium, $operation->type, 'Removes an existing database constraint or index.', 'Verify query performance and referential integrity before deployment.')],
            'raw_sql' => $enabled['raw_sql'] ? [$risk($this->rawSqlLevel((string) ($operation->attributes['sql'] ?? '')), 'raw_sql', 'Raw SQL cannot be fully analyzed safely.', 'Review the SQL manually before deployment.')] : [],
            'data_write' => [$risk(RiskLevel::High, 'data_write', 'Migration performs a data write; affected rows cannot be bounded statically.', 'Use a scoped query, batch processing, and a reversible deployment plan.')],
            'data_query' => [$risk(RiskLevel::Medium, 'data_query', 'Migration uses the query builder; chained writes are reported separately when detected.', 'Review query scope and execution cost.')],
            'schema_definition' => [$risk(RiskLevel::Low, 'schema_definition', 'Migration defines an additional schema attribute.', 'Verify the generated schema and deployment compatibility.')],
            'application_side_effect' => [$risk(RiskLevel::High, 'application_side_effect', 'Migration invokes application code outside the schema builder.', 'Move application behavior out of the migration or review its side effects manually.')],
            default => [$risk(RiskLevel::High, 'unsupported_operation', $this->unsupportedReason($operation), 'Review this migration manually before deployment.')],
        };
    }

    private function columnRisk(MigrationOperation $operation, ?int $rows, array $enabled, \Closure $risk): array
    {
        $safeDefault = array_key_exists('default', $operation->attributes) && $operation->attributes['default'] !== null;
        if ($enabled['add_not_null_column'] && $this->existingTable($operation) && !$operation->nullable && !$safeDefault && ($rows ?? 0) > 0) {
            return [$risk(RiskLevel::High, 'add_not_null_column', "Adds a non-nullable column without a default to a populated table ({$rows} estimated rows).", 'Add it as nullable or with a safe default, backfill, then enforce NOT NULL.')];
        }

        return [$risk(RiskLevel::Low, 'add_column', 'Adds a column without a detected populated-table constraint.', 'Verify application compatibility before deployment.')];
    }

    private function indexRisk(MigrationOperation $operation, ?int $rows, \Closure $risk, string $rule, string $reason, ?string $connection = null): array
    {
        if (!config('migration-guard.rules.large_table_index') || ($rows ?? 0) < config('migration-guard.thresholds.large_table_rows')) {
            return [$risk(RiskLevel::Low, 'add_index', 'Adds an index.', 'Verify the operation against expected table size.')];
        }

        $size = $this->inspector->estimatedSizeMb($operation->table, $connection);
        $veryLarge = ($rows ?? 0) >= config('migration-guard.thresholds.very_large_table_rows')
            || ($size ?? 0) >= config('migration-guard.thresholds.large_table_size_mb');
        $level = $veryLarge ? RiskLevel::Critical : RiskLevel::High;

        return [$risk($level, $rule, "{$reason} {$rows} estimated rows".($size === null ? '.' : ", {$size} MB."), 'Use an online index strategy and test its locking behavior.')];
    }

    private function uniqueRisk(MigrationOperation $operation, AnalysisContext $context, string $migration, \Closure $risk): array
    {
        if (!config('migration-guard.rules.unique_constraint')) {
            return [];
        }
        if ($operation->table === null) {
            return [$risk(RiskLevel::High, 'unique_constraint', 'Could not determine the table for this unique constraint.', 'Review the migration manually; the analyzer will not assume the constraint is safe.')];
        }
        if (!$this->existingTable($operation)) {
            return [$risk(RiskLevel::Low, 'unique_constraint', 'Adds a unique constraint to a newly created table.', 'Verify the constraint definition.')];
        }
        $duplicates = $this->inspector->duplicateGroups($operation->table, $operation->columns, $context->connection);
        if ($duplicates === null) {
            return [$risk(RiskLevel::High, 'unique_constraint', 'Could not safely validate existing values for the unique constraint.', 'Review the data manually before deployment.')];
        }
        if ($duplicates > 0) {
            return [$risk(RiskLevel::Critical, 'unique_constraint', "{$duplicates} duplicate value groups would prevent this unique constraint.", 'Clean duplicate records before applying the unique constraint.')];
        }

        return $this->indexRisk($operation, $this->inspector->estimatedRows($operation->table, $context->connection), $risk, 'large_table_unique_index', 'Adds a unique index to a large table.', $context->connection);
    }

    private function foreignKeyRisk(MigrationOperation $operation, AnalysisContext $context, string $migration, \Closure $risk): array
    {
        if (!config('migration-guard.rules.foreign_key')) {
            return [];
        }
        if ($operation->table === null || $operation->column === null || !$this->existingTable($operation) || !isset($operation->attributes['referenced_table'], $operation->attributes['referenced_column'])) {
            return [$risk(RiskLevel::Low, 'foreign_key', 'Adds a foreign key whose referenced data cannot require pre-existing validation.', 'Verify the referenced table and cascade behavior.')];
        }
        $orphans = $this->inspector->orphanedRows($operation->table, $operation->column, $operation->attributes['referenced_table'], $operation->attributes['referenced_column'], $context->connection);
        if ($orphans === null) {
            return [$risk(RiskLevel::High, 'foreign_key', 'Could not safely validate orphaned records.', 'Review referential integrity manually before deployment.')];
        }
        if ($orphans > 0) {
            return [$risk(RiskLevel::Critical, 'foreign_key', "{$orphans} orphaned rows would prevent this foreign key.", 'Clean orphaned records before applying the foreign key.')];
        }

        return [$risk(RiskLevel::Low, 'foreign_key', 'No orphaned records were detected.', 'Verify deployment locking behavior.')];
    }

    private function rawSqlLevel(string $sql): RiskLevel
    {
        return preg_match('/\b(drop|truncate)\b/i', $sql) === 1 ? RiskLevel::Critical : RiskLevel::High;
    }

    private function existingTable(MigrationOperation $operation): bool
    {
        return !($operation->attributes['created_in_migration'] ?? false);
    }

    private function unsupportedReason(MigrationOperation $operation): string
    {
        $api = $operation->attributes['api'] ?? 'an unknown operation';
        $line = isset($operation->attributes['line']) ? " on line {$operation->attributes['line']}" : '';

        return "Unsupported API {$api}{$line}.";
    }
}
