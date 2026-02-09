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
        Schema::dropIfExists('malls'); // Drop if exists to avoid conflicts
        Schema::create('malls', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Mall name');
            $table->string('name_ar', 255)->nullable()->comment('اسم المول بالعربية');
            $table->string('name_en', 255)->nullable()->comment('Mall name in English');
            $table->text('description')->nullable()->comment('Mall description');
            $table->text('description_ar')->nullable()->comment('وصف المول بالعربية');
            $table->text('description_en')->nullable()->comment('Mall description in English');
            $table->string('address', 500)->comment('Mall address');
            $table->string('address_ar', 500)->nullable()->comment('عنوان المول بالعربية');
            $table->string('address_en', 500)->nullable()->comment('Mall address in English');
            $table->string('city', 100)->default('القاهرة')->comment('City');
            $table->string('country', 100)->default('مصر')->comment('Country');
            $table->decimal('latitude', 10, 7)->nullable()->comment('Latitude');
            $table->decimal('longitude', 10, 7)->nullable()->comment('Longitude');
            $table->string('phone', 20)->nullable()->comment('Phone number');
            $table->string('email', 255)->nullable()->comment('Email');
            $table->string('website', 500)->nullable()->comment('Website URL');
            $table->string('image_url', 500)->nullable()->comment('Mall image URL');
            $table->json('images')->nullable()->comment('Additional images array JSON');
            $table->json('opening_hours')->nullable()->comment('Opening hours JSON');
            $table->boolean('is_active')->default(true)->comment('Active status');
            $table->integer('order_index')->default(0)->comment('Display order');
            $table->timestamps();
            
            $table->index('city');
            $table->index('is_active');
            $table->index('order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('malls');
    }
};

