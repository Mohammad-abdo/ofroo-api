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
        Schema::create('two_factor_auths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade')->comment('User ID');
            $table->boolean('is_enabled')->default(false)->comment('2FA enabled status');
            $table->string('secret_key', 255)->nullable()->comment('TOTP secret key');
            $table->json('recovery_codes')->nullable()->comment('Recovery codes');
            $table->timestamp('verified_at')->nullable()->comment('Verification timestamp');
            $table->timestamps();
            
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_auths');
    }
};
