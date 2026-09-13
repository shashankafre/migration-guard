<?php

namespace MigrationGuard\Laravel\Risk;

use MigrationGuard\Laravel\Scope\MigrationScope;

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
        public string $status = 'active',
        public ?string $exceptionReason = null,
        public ?string $file = null,
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
            'status' => $this->status,
            'exception_reason' => $this->exceptionReason,
            'file' => $this->file,
        ];
    }

    public function withStatus(string $status, string $reason): self
    {
        return new self($this->level, $this->rule, $this->reason, $this->recommendation, $this->scope, $this->migration, $this->table, $this->column, $this->tenant, $status, $reason, $this->file);
    }

    public function withFile(string $file): self
    {
        return new self($this->level, $this->rule, $this->reason, $this->recommendation, $this->scope, $this->migration, $this->table, $this->column, $this->tenant, $this->status, $this->exceptionReason, $file);
    }
}
