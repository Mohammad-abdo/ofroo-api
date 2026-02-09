<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->text('terms_conditions_ar')->nullable()->after('description_en')->comment('شروط وأحكام العرض بالعربية');
            $table->text('terms_conditions_en')->nullable()->after('terms_conditions_ar')->comment('Terms and conditions in English');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['terms_conditions_ar', 'terms_conditions_en']);
        });
    }
};


