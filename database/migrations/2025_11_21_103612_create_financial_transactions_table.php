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
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null')->comment('Related order ID');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null')->comment('Related payment ID');
            $table->string('transaction_type', 50)->comment('Type: order_revenue, commission, withdrawal, refund, expense, subscription');
            $table->enum('transaction_flow', ['incoming', 'outgoing'])->comment('Money flow direction');
            $table->decimal('amount', 15, 2)->comment('Transaction amount');
            $table->decimal('balance_before', 15, 2)->comment('Balance before transaction');
            $table->decimal('balance_after', 15, 2)->comment('Balance after transaction');
            $table->string('description', 500)->nullable()->comment('Transaction description');
            $table->string('description_ar', 500)->nullable()->comment('وصف المعاملة بالعربية');
            $table->string('description_en', 500)->nullable()->comment('Transaction description in English');
            $table->string('reference_number', 100)->nullable()->comment('Reference number');
            $table->json('metadata')->nullable()->comment('Additional transaction data');
            $table->string('status', 50)->default('completed')->comment('Transaction status');
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('order_id');
            $table->index('transaction_type');
            $table->index('transaction_flow');
            $table->index('created_at');
            $table->index(['merchant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
