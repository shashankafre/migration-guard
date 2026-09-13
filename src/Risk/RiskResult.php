<?php

namespace MigrationSafe\Laravel\Risk;

use MigrationSafe\Laravel\Scope\MigrationScope;

final readonly class RiskResult
{
    public function __construct(
        public RiskLevel $level,
        public string $rule,
        public string $reason,
        public string $recommendation,
        public MigrationScope $scope,
        public string $migration,
        public ?string $table = null,
        public ?string $column = null,
        public ?string $tenant = null,
    ) {
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'level' => strtolower($this->level->name),
            'rule' => $this->rule,
            'reason' => $this->reason,
            'recommendation' => $this->recommendation,
            'scope' => $this->scope->value,
            'migration' => $this->migration,
            'table' => $this->table,
            'column' => $this->column,
            'tenant' => $this->tenant,
        ];
    }
}
