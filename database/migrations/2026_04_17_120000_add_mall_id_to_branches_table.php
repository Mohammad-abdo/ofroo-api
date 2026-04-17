<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branches (renamed from store_locations) are used with mall_id for profile/offers UI.
     */
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }
        if (Schema::hasColumn('branches', 'mall_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('mall_id')
                ->nullable()
                ->after('merchant_id')
                ->constrained('malls')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('branches') || ! Schema::hasColumn('branches', 'mall_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['mall_id']);
            $table->dropColumn('mall_id');
        });
    }
};
