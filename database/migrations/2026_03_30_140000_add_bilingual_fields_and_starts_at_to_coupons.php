<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'title_ar')) {
                $table->string('title_ar')->nullable();
            }
            if (! Schema::hasColumn('coupons', 'title_en')) {
                $table->string('title_en')->nullable();
            }
            if (! Schema::hasColumn('coupons', 'description_ar')) {
                $table->text('description_ar')->nullable();
            }
            if (! Schema::hasColumn('coupons', 'description_en')) {
                $table->text('description_en')->nullable();
            }
            if (! Schema::hasColumn('coupons', 'starts_at')) {
                $table->dateTime('starts_at')->nullable();
            }
        });
        // usage_limit: keep non-null; use 0 = unlimited in app logic (no doctrine/dbal column change).
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            foreach (['title_ar', 'title_en', 'description_ar', 'description_en', 'starts_at'] as $col) {
                if (Schema::hasColumn('coupons', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
