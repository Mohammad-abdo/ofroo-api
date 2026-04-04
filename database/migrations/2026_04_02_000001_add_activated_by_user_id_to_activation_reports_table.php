<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activation_reports', 'activated_by_user_id')) {
            Schema::table('activation_reports', function (Blueprint $table) {
                $table->foreignId('activated_by_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('Merchant user or staff who performed the activation');
            });
        }

        $indexExists = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            ['activation_reports', 'ar_merch_activator_created_idx']
        );

        if (! $indexExists) {
            Schema::table('activation_reports', function (Blueprint $table) {
                $table->index(
                    ['merchant_id', 'activated_by_user_id', 'created_at'],
                    'ar_merch_activator_created_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('activation_reports', function (Blueprint $table) {
            $table->dropIndex('ar_merch_activator_created_idx');
        });

        if (Schema::hasColumn('activation_reports', 'activated_by_user_id')) {
            Schema::table('activation_reports', function (Blueprint $table) {
                $table->dropForeign(['activated_by_user_id']);
            });
        }
    }
};
