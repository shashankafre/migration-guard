<?php

namespace MigrationSafe\Laravel\Reports;

use Illuminate\Console\OutputStyle;
use MigrationSafe\Laravel\Analysis\MigrationAnalysis;

final class ConsoleReporter
{
    public function report(OutputStyle $output, string $heading, MigrationAnalysis $analysis): void
    {
        $output->section($heading);
        $output->writeln("Pending migrations: {$analysis->pendingMigrations}");
        $output->writeln("Risk: <fg=yellow>{$analysis->highestRisk()->label()}</>");

        foreach ($analysis->risks as $risk) {
            $output->newLine();
            $output->writeln("<fg=red>{$risk->level->label()}</> {$risk->migration} [{$risk->rule}]");
            $output->writeln($risk->reason);
            $output->writeln("Recommendation: {$risk->recommendation}");
        }

        foreach ($analysis->errors as $error) {
            $output->warning("Manual review required: {$error}");
        }
    }
}
