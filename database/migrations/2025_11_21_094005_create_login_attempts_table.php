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
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('User ID (null if email/phone not found)');
            $table->string('email')->nullable()->comment('Attempted email');
            $table->string('phone')->nullable()->comment('Attempted phone');
            $table->string('ip_address', 45)->comment('IP address');
            $table->text('user_agent')->nullable()->comment('User agent');
            $table->boolean('success')->default(false)->comment('Login success');
            $table->timestamp('attempted_at')->useCurrent()->comment('Attempt timestamp');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('email');
            $table->index('phone');
            $table->index('ip_address');
            $table->index('attempted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
