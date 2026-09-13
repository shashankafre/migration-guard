<?php

namespace MigrationGuard\Laravel\Operations;

final readonly class MigrationOperation
{
    /** @param array<int, string> $columns */
    public function __construct(
        public string $type,
        public ?string $table = null,
        public ?string $column = null,
        public array $columns = [],
        public bool $nullable = true,
        public ?string $columnType = null,
        public array $attributes = [],
    ) {
    }
}
