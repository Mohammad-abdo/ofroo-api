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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->decimal('amount', 15, 2)->comment('Withdrawal amount');
            $table->string('withdrawal_method', 50)->comment('Method: bank_transfer, paypal, etc');
            $table->string('account_details', 500)->nullable()->comment('Account details (encrypted)');
            $table->string('status', 50)->default('pending')->comment('Status: pending, approved, rejected, completed');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->comment('Admin who approved');
            $table->timestamp('approved_at')->nullable()->comment('Approval timestamp');
            $table->timestamp('completed_at')->nullable()->comment('Completion timestamp');
            $table->text('rejection_reason')->nullable()->comment('Rejection reason');
            $table->text('admin_notes')->nullable()->comment('Admin notes');
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
