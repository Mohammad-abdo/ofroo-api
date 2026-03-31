<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
                $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade');
                $table->decimal('commission_rate', 5, 4);
                $table->decimal('commission_amount', 14, 2);
                $table->string('status', 50)->default('completed');
                $table->string('note', 500)->nullable();
                $table->timestamps();

                $table->index(['merchant_id', 'created_at']);
                $table->index(['order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
