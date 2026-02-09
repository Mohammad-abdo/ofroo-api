<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('merchant_wallets', 'reserved_balance')) {
                $table->decimal('reserved_balance', 14, 2)->default(0)->after('balance')->comment('Reserved balance for pending/held funds');
            }
            if (!Schema::hasColumn('merchant_wallets', 'currency')) {
                $table->string('currency', 3)->default('EGP')->after('reserved_balance')->comment('Currency code');
            }
            if (!Schema::hasColumn('merchant_wallets', 'is_frozen')) {
                $table->boolean('is_frozen')->default(false)->after('currency')->comment('Wallet frozen status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchant_wallets', function (Blueprint $table) {
            $table->dropColumn(['reserved_balance', 'currency', 'is_frozen']);
        });
    }
};


