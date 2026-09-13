<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

final class StaticUnsupportedMigration extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::mystery('audit_logs');
        }
    }
}
