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
        if (Schema::hasTable('store_locations')) {
            return; // Table already exists, skip creation
        }
        
        Schema::create('store_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->string('name_ar', 255)->nullable()->comment('اسم الفرع بالعربية');
            $table->string('name_en', 255)->nullable()->comment('Branch name in English');
            $table->string('phone', 50)->nullable()->comment('Branch phone number');
            $table->boolean('is_active')->default(true)->comment('Branch active status');
            $table->decimal('lat', 10, 7)->comment('Latitude');
            $table->decimal('lng', 10, 7)->comment('Longitude');
            $table->string('address', 500)->nullable()->comment('Address');
            $table->string('address_ar', 500)->nullable()->comment('العنوان بالعربية');
            $table->string('address_en', 500)->nullable()->comment('Address in English');
            $table->string('google_place_id', 255)->nullable()->comment('Google Place ID');
            $table->json('opening_hours')->nullable()->comment('Opening hours JSON');
            $table->timestamps();
            
            $table->index(['merchant_id']);
            $table->index(['lat', 'lng'], 'store_locations_geo_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('store_locations');
        Schema::enableForeignKeyConstraints();
    }
};
