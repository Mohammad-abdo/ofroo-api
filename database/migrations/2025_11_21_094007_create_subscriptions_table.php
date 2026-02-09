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
        // Drop table if exists to avoid conflicts
        Schema::dropIfExists('subscriptions');
        
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subscribable'); // Creates subscribable_id and subscribable_type (for merchant or user) with index
            $table->string('package_name', 100)->comment('Package name');
            $table->string('package_name_ar', 100)->nullable()->comment('اسم الباقة بالعربية');
            $table->string('package_name_en', 100)->nullable()->comment('Package name in English');
            $table->dateTime('starts_at')->comment('Subscription start date');
            $table->dateTime('ends_at')->comment('Subscription end date');
            $table->decimal('price', 10, 2)->comment('Subscription price');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active')->comment('Subscription status');
            $table->timestamps();
            
            // morphs() already adds index on (subscribable_type, subscribable_id), so we don't need to add it again
            $table->index('status');
            $table->index('ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
