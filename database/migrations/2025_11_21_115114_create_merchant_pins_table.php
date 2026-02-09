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
        Schema::create('merchant_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->string('pin_hash', 255)->comment('Hashed PIN');
            $table->boolean('biometric_enabled')->default(false)->comment('Biometric authentication enabled');
            $table->integer('failed_attempts')->default(0)->comment('Failed login attempts');
            $table->timestamp('locked_until')->nullable()->comment('Account locked until');
            $table->timestamp('last_login_at')->nullable()->comment('Last login timestamp');
            $table->timestamps();
            
            $table->index('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_pins');
    }
};
