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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade')->comment('Order ID');
            $table->foreignId('offer_id')->constrained('offers')->onDelete('cascade')->comment('Offer ID');
            $table->integer('quantity')->comment('Quantity');
            $table->decimal('unit_price', 10, 2)->comment('Unit price');
            $table->decimal('total_price', 10, 2)->comment('Total price');
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('offer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
