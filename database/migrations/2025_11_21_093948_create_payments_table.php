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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade')->comment('Order ID');
            $table->string('transaction_id', 255)->nullable()->comment('Transaction ID from gateway');
            $table->decimal('amount', 12, 2)->comment('Payment amount');
            $table->string('gateway', 100)->nullable()->comment('Payment gateway name');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending')->comment('Payment status');
            $table->json('response')->nullable()->comment('Gateway response JSON');
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('transaction_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
