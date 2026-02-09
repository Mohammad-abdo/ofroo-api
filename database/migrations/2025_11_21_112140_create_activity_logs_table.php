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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('User who performed action');
            $table->string('action', 100)->comment('Action: login, logout, create, update, delete, etc');
            $table->string('model_type', 100)->nullable()->comment('Model class name');
            $table->unsignedBigInteger('model_id')->nullable()->comment('Model ID');
            $table->text('description')->comment('Action description');
            $table->string('ip_address', 45)->nullable()->comment('IP address');
            $table->string('user_agent', 500)->nullable()->comment('User agent');
            $table->json('old_values')->nullable()->comment('Old values before change');
            $table->json('new_values')->nullable()->comment('New values after change');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('action');
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
