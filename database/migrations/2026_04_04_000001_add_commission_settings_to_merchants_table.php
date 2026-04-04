<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (! Schema::hasColumn('merchants', 'commission_mode')) {
                $table->string('commission_mode', 32)->default('platform')->after('tax_number');
            }
            if (! Schema::hasColumn('merchants', 'commission_custom_percent')) {
                $table->decimal('commission_custom_percent', 5, 2)->nullable()->after('commission_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (Schema::hasColumn('merchants', 'commission_custom_percent')) {
                $table->dropColumn('commission_custom_percent');
            }
            if (Schema::hasColumn('merchants', 'commission_mode')) {
                $table->dropColumn('commission_mode');
            }
        });
    }
};
