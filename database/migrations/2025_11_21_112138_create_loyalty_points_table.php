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
        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade')->comment('User ID');
            $table->integer('total_points')->default(0)->comment('Total loyalty points');
            $table->string('tier', 50)->default('bronze')->comment('Tier: bronze, silver, gold, platinum');
            $table->integer('points_used')->default(0)->comment('Points used so far');
            $table->integer('points_expired')->default(0)->comment('Points expired');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_points');
    }
};
