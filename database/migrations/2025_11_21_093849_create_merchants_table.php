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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Owner user ID');
            $table->string('company_name', 255)->comment('Company name');
            $table->string('company_name_ar', 255)->nullable()->comment('اسم الشركة بالعربية');
            $table->string('company_name_en', 255)->nullable()->comment('Company name in English');
            $table->text('description')->nullable()->comment('Merchant description');
            $table->text('description_ar')->nullable()->comment('وصف التاجر بالعربية');
            $table->text('description_en')->nullable()->comment('Merchant description in English');
            $table->string('address', 500)->nullable()->comment('Address');
            $table->string('address_ar', 500)->nullable()->comment('العنوان بالعربية');
            $table->string('address_en', 500)->nullable()->comment('Address in English');
            $table->string('phone', 50)->nullable()->comment('Phone number');
            $table->string('whatsapp_link', 255)->nullable()->comment('WhatsApp link');
            $table->boolean('approved')->default(false)->comment('Admin approval status');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
