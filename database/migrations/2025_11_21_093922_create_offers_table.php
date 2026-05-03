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
            $table->foreignId('mall_id')->nullable()->constrained('malls')->nullOnDelete()->comment('Optional mall scope');
            $table->foreignId('location_id')->nullable()->constrained('store_locations')->onDelete('set null')->comment('Store location ID');
            $table->string('title_ar', 255)->comment('عنوان العرض بالعربية');
            $table->string('title_en', 255)->nullable()->comment('Offer title in English');
            $table->text('description_ar')->nullable()->comment('وصف العرض بالعربية');
            $table->text('description_en')->nullable()->comment('Offer description in English');
            $table->text('terms_conditions_ar')->nullable()->comment('شروط وأحكام العرض بالعربية');
            $table->text('terms_conditions_en')->nullable()->comment('Terms and conditions in English');
            $table->decimal('price', 10, 2)->comment('Offer price');
            $table->decimal('original_price', 10, 2)->nullable()->comment('Original price before discount');
            $table->integer('discount_percent')->default(0)->comment('Discount percentage');
            $table->json('images')->nullable()->comment('Images array JSON');
            $table->integer('total_coupons')->default(0)->comment('Total available coupons');
            $table->integer('coupons_remaining')->default(0)->comment('Remaining coupons');
            $table->unsignedInteger('reserved_quantity')->default(0)->comment('Internal: quantity reserved by pending orders (not yet activated)');
            $table->unsignedInteger('used_quantity')->default(0)->comment('Internal: quantity activated via QR scan');
            $table->dateTime('start_at')->nullable()->comment('Offer start date');
            $table->dateTime('end_at')->nullable()->comment('Offer end date');
            $table->enum('status', ['draft', 'pending', 'active', 'expired'])->default('draft')->comment('Offer status');
            $table->timestamps();
            $table->softDeletes();

            $table->index('merchant_id');
            $table->index('category_id');
            $table->index('mall_id');
            $table->index('location_id');
            $table->index('status');
            $table->index('start_at');
            $table->index('end_at');
            $table->index(['merchant_id', 'status'], 'offers_merchant_status_index');
            $table->index(['category_id', 'status'], 'offers_category_status_index');
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
