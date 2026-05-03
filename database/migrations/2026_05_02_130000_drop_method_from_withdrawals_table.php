<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `method` column duplicated semantics now covered by `withdrawal_method`.
 * No application reads `$withdrawal->method` outside the model fillable list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            if (Schema::hasColumn('withdrawals', 'method')) {
                $table->dropColumn('method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            if (! Schema::hasColumn('withdrawals', 'method')) {
                $table->enum('method', ['bank', 'manual'])->default('bank')->after('amount')->comment('Withdrawal method');
            }
        });
    }
};
