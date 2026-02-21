<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (!Schema::hasColumn('merchants', 'commercial_registration')) {
                $table->string('commercial_registration', 255)->nullable()->after('company_name_en');
            }
            if (!Schema::hasColumn('merchants', 'tax_number')) {
                $table->string('tax_number', 255)->nullable()->after('commercial_registration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['commercial_registration', 'tax_number']);
        });
    }
};
