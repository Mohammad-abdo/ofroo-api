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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('Role name: admin/merchant/user');
            $table->string('name_ar', 50)->nullable()->comment('اسم الدور بالعربية');
            $table->string('name_en', 50)->nullable()->comment('Role name in English');
            $table->string('description', 255)->nullable()->comment('Role description');
            $table->string('description_ar', 255)->nullable()->comment('وصف الدور بالعربية');
            $table->string('description_en', 255)->nullable()->comment('Role description in English');
            $table->json('permissions')->nullable()->comment('JSON permissions array');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
