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
        // Set default country to Egypt for existing users
        \DB::table('users')
            ->whereNull('country')
            ->orWhere('country', '')
            ->update(['country' => 'مصر']);

        // Set default country to Egypt for existing merchants
        \DB::table('merchants')
            ->whereNull('country')
            ->orWhere('country', '')
            ->update(['country' => 'مصر']);

        // Update country column default value
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 100)->default('مصر')->change();
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->string('country', 100)->default('مصر')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->change();
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->change();
        });
    }
};
