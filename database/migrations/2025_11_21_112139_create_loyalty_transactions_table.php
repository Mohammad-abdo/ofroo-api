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
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('User ID');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null')->comment('Related order');
            $table->string('transaction_type', 50)->comment('Type: earned, redeemed, expired, bonus');
            $table->integer('points')->comment('Points amount (positive for earned, negative for redeemed)');
            $table->text('description')->nullable()->comment('Transaction description');
            $table->date('expires_at')->nullable()->comment('Expiration date for points');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('order_id');
            $table->index('transaction_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
