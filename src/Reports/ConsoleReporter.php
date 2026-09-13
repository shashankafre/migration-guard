<?php

namespace MigrationGuard\Laravel\Reports;

use Illuminate\Console\OutputStyle;
use MigrationGuard\Laravel\Analysis\MigrationAnalysis;

final class ConsoleReporter
{
    public function report(OutputStyle $output, string $heading, MigrationAnalysis $analysis): void
    {
        $output->section($heading);
        $output->writeln("Pending migrations: {$analysis->pendingMigrations}");
        if ($analysis->tenantsAnalyzed > 0 || $analysis->tenantsSkipped > 0) {
            $output->writeln("Tenants analyzed: {$analysis->tenantsAnalyzed}; skipped: {$analysis->tenantsSkipped}");
        }
        $output->writeln("Risk: <fg=yellow>{$analysis->highestRisk()->label()}</>");

        foreach ($analysis->risks as $risk) {
            $output->newLine();
            $status = $risk->status === 'active' ? '' : " ({$risk->status})";
            $tenant = $risk->tenant === null ? '' : " tenant={$risk->tenant}";
            $output->writeln("<fg=red>{$risk->level->label()}</> {$risk->migration} [{$risk->rule}]{$tenant}{$status}");
            $output->writeln($risk->reason);
            if ($risk->file !== null) {
                $output->writeln("File: {$risk->file}");
            }
            $output->writeln("Recommendation: {$risk->recommendation}");
        }

        foreach ($analysis->errors as $error) {
            $output->warning("Manual review required: {$error}");
        }
    }
}
