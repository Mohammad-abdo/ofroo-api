<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores Privacy Policy sections managed from the admin dashboard
 * and exposed to the mobile app through GET /api/mobile/app/policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_policies')) {
            Schema::create('app_policies', function (Blueprint $table) {
                $table->id();
                $table->string('type', 32)->default('privacy')->index();
                $table->string('title_ar')->nullable();
                $table->string('title_en')->nullable();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->unsignedInteger('order_index')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('order_index');
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_policies');
    }
};
