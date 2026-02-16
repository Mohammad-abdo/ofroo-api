<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ensures guard_name has a default so inserts never fail when it's omitted.
     */
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'guard_name')) {
            return;
        }

        DB::statement("ALTER TABLE roles MODIFY COLUMN guard_name VARCHAR(255) NOT NULL DEFAULT 'web'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'guard_name')) {
            return;
        }

        DB::statement("ALTER TABLE roles MODIFY COLUMN guard_name VARCHAR(255) NOT NULL");
    }
};
