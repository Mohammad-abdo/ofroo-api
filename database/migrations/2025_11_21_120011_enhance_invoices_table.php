<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('merchant_invoices', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('merchant_id')->constrained('users')->onDelete('set null')->comment('Customer ID');
            }
            if (!Schema::hasColumn('merchant_invoices', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('net_amount')->comment('Tax/VAT amount');
            }
            if (!Schema::hasColumn('merchant_invoices', 'invoice_type')) {
                $table->enum('invoice_type', ['order', 'monthly', 'one_off'])->default('order')->after('merchant_id')->comment('Invoice type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchant_invoices', function (Blueprint $table) {
            // Drop foreign key first if it exists
            if (Schema::hasColumn('merchant_invoices', 'customer_id')) {
                $table->dropForeign(['customer_id']);
            }
        });
        
        Schema::table('merchant_invoices', function (Blueprint $table) {
            // Now drop columns
            if (Schema::hasColumn('merchant_invoices', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('merchant_invoices', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('merchant_invoices', 'invoice_type')) {
                $table->dropColumn('invoice_type');
            }
        });
    }
};


