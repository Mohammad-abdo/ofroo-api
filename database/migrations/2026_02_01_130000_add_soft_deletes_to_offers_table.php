<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Offer model uses SoftDeletes; ensure offers table has deleted_at.
     */
    public function up(): void
    {
        if (Schema::hasTable('offers') && !Schema::hasColumn('offers', 'deleted_at')) {
            Schema::table('offers', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('offers', 'deleted_at')) {
            Schema::table('offers', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
