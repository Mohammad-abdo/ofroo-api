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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('Gateway name: knet, visa, mastercard, apple_pay, google_pay');
            $table->string('display_name', 255)->comment('Display name');
            $table->string('display_name_ar', 255)->nullable()->comment('اسم العرض بالعربية');
            $table->string('display_name_en', 255)->nullable()->comment('Display name in English');
            $table->boolean('is_active')->default(false)->comment('Active status');
            $table->json('credentials')->nullable()->comment('Encrypted gateway credentials');
            $table->json('settings')->nullable()->comment('Gateway settings');
            $table->decimal('fee_percentage', 5, 2)->default(0)->comment('Fee percentage');
            $table->decimal('fee_fixed', 10, 2)->default(0)->comment('Fixed fee amount');
            $table->integer('order_index')->default(0)->comment('Display order');
            $table->timestamps();
            
            $table->index('name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
