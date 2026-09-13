<?php

namespace MigrationSafe\Laravel\Tenancy;

final readonly class TenantContext
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public string $connection,
        public ?string $database = null,
        public array $metadata = [],
    ) {
    }
}
