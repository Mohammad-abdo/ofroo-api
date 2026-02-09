<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 10)->default('EG')->comment('Country code (ISO 3166-1 alpha-2)');
            $table->string('tax_name', 100)->default('VAT')->comment('Tax name');
            $table->string('tax_name_ar', 100)->nullable()->comment('اسم الضريبة بالعربية');
            $table->string('tax_name_en', 100)->nullable()->comment('Tax name in English');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Tax rate percentage');
            $table->boolean('is_active')->default(true)->comment('Active status');
            $table->json('exempt_categories')->nullable()->comment('Exempt categories IDs');
            $table->timestamps();
            
            $table->index('country_code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};
