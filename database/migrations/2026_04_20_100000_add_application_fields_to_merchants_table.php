<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (! Schema::hasColumn('merchants', 'accepted_terms')) {
                $table->boolean('accepted_terms')
                    ->default(false)
                    ->after('approved')
                    ->comment('Whether the merchant accepted T&C during registration');
            }
            if (! Schema::hasColumn('merchants', 'rejection_reason')) {
                $table->text('rejection_reason')
                    ->nullable()
                    ->after('accepted_terms')
                    ->comment('Admin rejection reason shown to the merchant');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (Schema::hasColumn('merchants', 'accepted_terms')) {
                $table->dropColumn('accepted_terms');
            }
            if (Schema::hasColumn('merchants', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
};
