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
        Schema::create('merchant_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Staff user ID');
            $table->string('role', 50)->default('staff')->comment('Role: manager, staff, cashier, scanner');
            $table->string('role_ar', 50)->nullable()->comment('الدور بالعربية');
            $table->string('role_en', 50)->nullable()->comment('Role in English');
            $table->json('permissions')->nullable()->comment('Staff permissions JSON');
            $table->boolean('can_create_offers')->default(false)->comment('Can create offers');
            $table->boolean('can_edit_offers')->default(false)->comment('Can edit offers');
            $table->boolean('can_activate_coupons')->default(true)->comment('Can activate coupons');
            $table->boolean('can_view_reports')->default(false)->comment('Can view reports');
            $table->boolean('can_manage_staff')->default(false)->comment('Can manage staff');
            $table->boolean('is_active')->default(true)->comment('Active status');
            $table->timestamps();
            
            $table->unique(['merchant_id', 'user_id']);
            $table->index('merchant_id');
            $table->index('user_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_staff');
    }
};
