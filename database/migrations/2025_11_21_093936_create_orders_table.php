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
            $table->enum('status', ['pending', 'activated', 'expired', 'cancelled'])
                ->default('pending')
                ->comment('Reservation/activation lifecycle (internal, separate from payment_status)');
            $table->timestamp('reservation_expires_at')->nullable()->comment('When the reservation auto-expires if not activated');
            $table->timestamp('activated_at')->nullable()->comment('Timestamp of first successful QR activation');
            $table->timestamp('wallet_processed_at')->nullable()->comment('Idempotency: first WalletService::processOrderPayment run');
            $table->text('notes')->nullable()->comment('Order notes');
            $table->timestamps();

            $table->index('user_id');
            $table->index('merchant_id');
            $table->index('payment_status');
            $table->index('created_at');
            $table->index(['status', 'reservation_expires_at'], 'orders_status_reservation_expires_at_index');
            $table->index(['user_id', 'created_at'], 'orders_user_id_created_at_index');
            $table->index(['merchant_id', 'payment_status', 'created_at'], 'orders_merchant_payment_status_index');
            $table->index(['payment_status', 'created_at'], 'orders_payment_status_created_at_index');
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
