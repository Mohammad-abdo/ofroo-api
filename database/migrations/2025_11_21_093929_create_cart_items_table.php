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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->onDelete('cascade')->comment('Cart ID');
            $table->foreignId('offer_id')->constrained('offers')->onDelete('cascade')->comment('Offer ID');
            $table->integer('quantity')->default(1)->comment('Quantity');
            $table->decimal('price_at_add', 10, 2)->comment('Price when added to cart');
            $table->timestamps();
            
            $table->index('cart_id');
            $table->index('offer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
