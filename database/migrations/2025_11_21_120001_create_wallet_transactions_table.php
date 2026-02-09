<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id')->comment('Wallet ID (merchant_wallet or admin_wallet)');
            $table->enum('wallet_type', ['merchant', 'admin'])->comment('Wallet type');
            $table->enum('transaction_type', ['credit', 'debit', 'payout', 'fee', 'refund', 'adjustment', 'commission'])->comment('Transaction type');
            $table->string('related_type', 100)->nullable()->comment('Related model: Order, Commission, Withdrawal, Penalty, etc');
            $table->unsignedBigInteger('related_id')->nullable()->comment('Related model ID');
            $table->decimal('amount', 14, 2)->comment('Transaction amount');
            $table->decimal('balance_before', 14, 2)->comment('Balance before transaction');
            $table->decimal('balance_after', 14, 2)->comment('Balance after transaction');
            $table->text('note')->nullable()->comment('Transaction note');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null')->comment('User who created transaction');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();

            $table->index('wallet_id');
            $table->index('wallet_type');
            $table->index('transaction_type');
            $table->index(['related_type', 'related_id']);
            $table->index('created_at');
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};

