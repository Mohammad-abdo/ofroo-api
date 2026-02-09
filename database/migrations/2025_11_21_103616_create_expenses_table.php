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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->string('expense_type', 50)->comment('Type: advertising, subscription, fees, other');
            $table->string('expense_type_ar', 50)->nullable()->comment('نوع المصروف بالعربية');
            $table->string('expense_type_en', 50)->nullable()->comment('Expense type in English');
            $table->string('category', 100)->nullable()->comment('Expense category');
            $table->string('category_ar', 100)->nullable()->comment('فئة المصروف بالعربية');
            $table->string('category_en', 100)->nullable()->comment('Expense category in English');
            $table->decimal('amount', 15, 2)->comment('Expense amount');
            $table->string('description', 500)->nullable()->comment('Expense description');
            $table->string('description_ar', 500)->nullable()->comment('وصف المصروف بالعربية');
            $table->string('description_en', 500)->nullable()->comment('Expense description in English');
            $table->date('expense_date')->comment('Expense date');
            $table->string('receipt_url', 500)->nullable()->comment('Receipt/document URL');
            $table->json('metadata')->nullable()->comment('Additional expense data');
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('expense_type');
            $table->index('expense_date');
            $table->index(['merchant_id', 'expense_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
