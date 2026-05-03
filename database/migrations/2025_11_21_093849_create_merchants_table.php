<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Includes columns previously added via add_* migrations (whatsapp, location, mall, tax, logo, commissions, application).
     * category_id is added in create_categories (merchants must exist first; categories table is created later).
     * city_id is added in create_cities (after cities exist).
     */
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Owner user ID');
            $table->string('company_name', 255)->comment('Company name');
            $table->string('company_name_ar', 255)->nullable()->comment('اسم الشركة بالعربية');
            $table->string('company_name_en', 255)->nullable()->comment('Company name in English');
            $table->string('commercial_registration', 255)->nullable()->comment('Commercial registration');
            $table->string('tax_number', 255)->nullable()->comment('Tax number');
            $table->text('description')->nullable()->comment('Merchant description');
            $table->text('description_ar')->nullable()->comment('وصف التاجر بالعربية');
            $table->text('description_en')->nullable()->comment('Merchant description in English');
            $table->string('address', 500)->nullable()->comment('Address');
            $table->string('address_ar', 500)->nullable()->comment('العنوان بالعربية');
            $table->string('address_en', 500)->nullable()->comment('Address in English');
            $table->string('phone', 50)->nullable()->comment('Phone number');
            $table->string('whatsapp_number', 50)->nullable()->comment('WhatsApp number');
            $table->string('whatsapp_link', 500)->nullable()->comment('WhatsApp direct link');
            $table->boolean('whatsapp_enabled')->default(true)->comment('WhatsApp contact enabled');
            $table->boolean('approved')->default(false)->comment('Admin approval status');
            $table->boolean('accepted_terms')->default(false)->comment('Accepted terms and conditions');
            $table->boolean('is_blocked')->default(false)->comment('Is blocked');
            $table->integer('branches_number')->default(0)->comment('Branch number');
            $table->string('country', 100)->nullable()->comment('Country');
            $table->string('city', 100)->nullable()->comment('City');
            $table->foreignId('mall_id')->nullable()->constrained('malls')->onDelete('set null')->comment('Mall association');
            $table->string('logo_url', 500)->nullable()->comment('Merchant logo URL');
            $table->string('commission_mode', 32)->default('platform');
            $table->decimal('commission_custom_percent', 5, 2)->nullable();
            $table->text('rejection_reason')->nullable()->comment('Admin rejection reason shown to the merchant');
            $table->timestamps();

            $table->index('user_id', 'merchants_user_id_index');
            $table->index('approved', 'merchants_approved_index');
            $table->index('mall_id', 'merchants_mall_id_index');
            $table->index(['approved', 'created_at'], 'merchants_approved_created_at_index');
            $table->index(['is_blocked', 'created_at'], 'merchants_status_created_at_index');
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
