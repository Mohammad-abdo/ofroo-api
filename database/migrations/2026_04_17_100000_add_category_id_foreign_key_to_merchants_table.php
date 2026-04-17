<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant ↔ Category: many merchants belong to one category.
 * The foreign key lives on merchants (category_id → categories.id), not on categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merchants') || ! Schema::hasTable('categories')) {
            return;
        }

        if (! Schema::hasColumn('merchants', 'category_id')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('categories')
                    ->nullOnDelete()
                    ->comment('Business category (many merchants per category)');
            });

            return;
        }

        Schema::table('merchants', function (Blueprint $table) {
            try {
                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->nullOnDelete();
            } catch (\Throwable $e) {
                // Constraint may already exist (e.g. manual schema).
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('merchants') || ! Schema::hasColumn('merchants', 'category_id')) {
            return;
        }

        Schema::table('merchants', function (Blueprint $table) {
            try {
                $table->dropForeign(['category_id']);
            } catch (\Throwable $e) {
                //
            }
            if (Schema::hasColumn('merchants', 'category_id')) {
                $table->dropColumn('category_id');
            }
        });
    }
};
