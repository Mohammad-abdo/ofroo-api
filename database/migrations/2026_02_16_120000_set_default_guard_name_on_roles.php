<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ensures guard_name has a default so inserts never fail when it's omitted.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'guard_name')) {
            return;
        }

        // MySQL / MariaDB only: the MODIFY COLUMN syntax is unsupported by
        // SQLite (used by the test suite). Skip silently on other drivers —
        // application code always sets guard_name explicitly anyway.
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE roles MODIFY COLUMN guard_name VARCHAR(255) NOT NULL DEFAULT 'web'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'guard_name')) {
            return;
        }

        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE roles MODIFY COLUMN guard_name VARCHAR(255) NOT NULL');
    }
};
