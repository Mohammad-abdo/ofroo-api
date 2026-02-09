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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('User ID');
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->onDelete('set null')->comment('Merchant ID');
            $table->decimal('total_amount', 12, 2)->comment('Total order amount');
            $table->enum('payment_method', ['cash', 'card', 'none'])->default('cash')->comment('Payment method');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending')->comment('Payment status');
            $table->text('notes')->nullable()->comment('Order notes');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('merchant_id');
            $table->index('payment_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
