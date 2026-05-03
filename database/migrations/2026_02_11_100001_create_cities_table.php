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
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained('governorates')->onDelete('cascade');
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->index('governorate_id');
        });

        if (Schema::hasTable('malls') && ! Schema::hasColumn('malls', 'city_id')) {
            Schema::table('malls', function (Blueprint $table) {
                $table->unsignedBigInteger('city_id')->nullable()->after('city');
                $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
            });
        }

        if (Schema::hasTable('merchants') && ! Schema::hasColumn('merchants', 'city_id')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->unsignedBigInteger('city_id')->nullable()->after('city');
                $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('malls') && Schema::hasColumn('malls', 'city_id')) {
            Schema::table('malls', function (Blueprint $table) {
                $table->dropForeign(['city_id']);
                $table->dropColumn('city_id');
            });
        }

        if (Schema::hasTable('merchants') && Schema::hasColumn('merchants', 'city_id')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->dropForeign(['city_id']);
                $table->dropColumn('city_id');
            });
        }

        Schema::dropIfExists('cities');
    }
};
