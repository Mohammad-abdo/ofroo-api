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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('User ID');
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null')->comment('Order ID');
            $table->tinyInteger('rating')->comment('Rating 1-5');
            $table->text('notes')->nullable()->comment('Review notes');
            $table->text('notes_ar')->nullable()->comment('ملاحظات التقييم بالعربية');
            $table->text('notes_en')->nullable()->comment('Review notes in English');
            $table->boolean('visible_to_public')->default(false)->comment('Visible to public');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('merchant_id');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
