<?php

namespace MigrationSafe\Laravel\Discovery;

use Illuminate\Database\ConnectionResolverInterface;

final class MigrationDiscovery
{
    public function __construct(private readonly ConnectionResolverInterface $connections)
    {
    }

    /** @param array<int, string> $paths
     *  @return array<string, string> migration name => file path
     */
    public function pending(array $paths, ?string $connection = null): array
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

        return array_diff_key($files, array_flip($ran));
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
                $files[$name] = $file->getPathname();
            }
        }

        ksort($files);

        return $files;
    }
}
