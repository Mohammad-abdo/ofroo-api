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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null')->comment('Category ID');
            $table->foreignId('location_id')->nullable()->constrained('store_locations')->onDelete('set null')->comment('Store location ID');
            $table->string('title_ar', 255)->comment('عنوان العرض بالعربية');
            $table->string('title_en', 255)->nullable()->comment('Offer title in English');
            $table->text('description_ar')->nullable()->comment('وصف العرض بالعربية');
            $table->text('description_en')->nullable()->comment('Offer description in English');
            $table->decimal('price', 10, 2)->comment('Offer price');
            $table->decimal('original_price', 10, 2)->nullable()->comment('Original price before discount');
            $table->integer('discount_percent')->default(0)->comment('Discount percentage');
            $table->json('images')->nullable()->comment('Images array JSON');
            $table->integer('total_coupons')->default(0)->comment('Total available coupons');
            $table->integer('coupons_remaining')->default(0)->comment('Remaining coupons');
            $table->dateTime('start_at')->nullable()->comment('Offer start date');
            $table->dateTime('end_at')->nullable()->comment('Offer end date');
            $table->enum('status', ['draft', 'pending', 'active', 'expired'])->default('draft')->comment('Offer status');
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('category_id');
            $table->index('location_id');
            $table->index('status');
            $table->index('start_at');
            $table->index('end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('offers');
        Schema::enableForeignKeyConstraints();
    }
};
