<?php

namespace MigrationGuard\Laravel\Discovery;

use Illuminate\Database\ConnectionResolverInterface;

final class MigrationDiscovery
{
    public function __construct(private readonly ConnectionResolverInterface $connections)
    {
    }

    /** @param array<int, string> $paths
     *  @return array<string, string> migration name => file path
     */
    public function pending(array $paths, ?string $connection = null, ?string $from = null): array
    {
        $files = $this->files($paths);
        $database = $this->connections->connection($connection);
        $table = config('database.migrations', 'migrations');

        try {
            $ran = $database->table($table)->pluck('migration')->all();
        } catch (\Throwable) {
            // A database without a migration table has not applied any known migration.
            $ran = [];
        }

        $pending = array_diff_key($files, array_flip($ran));

        return $from === null ? $pending : array_filter($pending, static fn (string $file, string $migration) => $migration >= $from, ARRAY_FILTER_USE_BOTH);
    }

    /** @param array<int, string> $paths
     *  @return array<string, string>
     */
    private function files(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                if (isset($files[$name]) && $files[$name] !== $file->getPathname()) {
                    throw new \LogicException("Duplicate migration name [{$name}] found in [{$files[$name]}] and [{$file->getPathname()}].");
                }
                $files[$name] = $file->getPathname();
            }
        }

        ksort($files);

        return $files;
    }
}
