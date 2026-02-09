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
        Schema::create('merchant_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique()->comment('Unique invoice number');
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->date('invoice_date')->comment('Invoice date');
            $table->date('period_start')->comment('Billing period start');
            $table->date('period_end')->comment('Billing period end');
            $table->decimal('total_sales', 12, 2)->default(0)->comment('Total sales amount');
            $table->decimal('commission_rate', 5, 2)->default(0)->comment('Commission rate percentage');
            $table->decimal('commission_amount', 12, 2)->default(0)->comment('Commission amount');
            $table->integer('total_activations')->default(0)->comment('Total activations count');
            $table->decimal('net_amount', 12, 2)->default(0)->comment('Net amount after commission');
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue'])->default('draft')->comment('Invoice status');
            $table->string('pdf_path', 500)->nullable()->comment('PDF invoice path');
            $table->date('due_date')->nullable()->comment('Payment due date');
            $table->date('paid_at')->nullable()->comment('Payment date');
            $table->text('notes')->nullable()->comment('Additional notes');
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('invoice_date');
            $table->index('status');
            $table->index('period_start');
            $table->index('period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_invoices');
    }
};
