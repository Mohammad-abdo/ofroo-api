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
        Schema::dropIfExists('ads'); // Drop if exists to avoid conflicts
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255)->comment('Ad title');
            $table->string('title_ar', 255)->nullable()->comment('عنوان الإعلان بالعربية');
            $table->string('title_en', 255)->nullable()->comment('Ad title in English');
            $table->text('description')->nullable()->comment('Ad description');
            $table->text('description_ar')->nullable()->comment('وصف الإعلان بالعربية');
            $table->text('description_en')->nullable()->comment('Ad description in English');
            $table->string('image_url', 500)->comment('Ad image URL');
            $table->json('images')->nullable()->comment('Additional images array JSON');
            $table->string('link_url', 500)->nullable()->comment('Click link URL');
            $table->string('position', 50)->comment('Position: home_top, home_middle, category_top, offer_detail, etc');
            $table->enum('ad_type', ['banner', 'popup', 'sidebar', 'inline'])->default('banner')->comment('Ad type');
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->onDelete('cascade')->comment('Merchant ID (if sponsored)');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null')->comment('Category ID (if category-specific)');
            $table->boolean('is_active')->default(true)->comment('Active status');
            $table->integer('order_index')->default(0)->comment('Display order');
            $table->dateTime('start_date')->nullable()->comment('Start date');
            $table->dateTime('end_date')->nullable()->comment('End date');
            $table->integer('clicks_count')->default(0)->comment('Clicks count');
            $table->integer('views_count')->default(0)->comment('Views count');
            $table->decimal('cost_per_click', 10, 2)->nullable()->comment('Cost per click');
            $table->decimal('total_budget', 10, 2)->nullable()->comment('Total budget');
            $table->timestamps();
            
            $table->index('position');
            $table->index('ad_type');
            $table->index('is_active');
            $table->index('merchant_id');
            $table->index('category_id');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};

