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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150)->comment('اسم الفئة بالعربية');
            $table->string('name_en', 150)->nullable()->comment('Category name in English');
            $table->integer('order_index')->default(0)->comment('Display order');
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade')->comment('Parent category ID');
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
