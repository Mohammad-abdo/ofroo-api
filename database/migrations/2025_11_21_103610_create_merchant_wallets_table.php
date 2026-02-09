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
        Schema::create('merchant_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->decimal('balance', 15, 2)->default(0)->comment('Current wallet balance');
            $table->decimal('pending_balance', 15, 2)->default(0)->comment('Pending withdrawal balance');
            $table->decimal('total_earned', 15, 2)->default(0)->comment('Total lifetime earnings');
            $table->decimal('total_withdrawn', 15, 2)->default(0)->comment('Total lifetime withdrawals');
            $table->decimal('total_commission_paid', 15, 2)->default(0)->comment('Total commission paid to platform');
            $table->timestamps();
            
            $table->index('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_wallets');
    }
};
