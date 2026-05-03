<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removes unused column bank_account_details (confirmed empty in dev snapshot).
     * offers.coupon_id is NOT dropped here — still required by application logic.
     */
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            if (Schema::hasColumn('withdrawals', 'bank_account_details')) {
                $table->dropColumn('bank_account_details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            if (! Schema::hasColumn('withdrawals', 'bank_account_details')) {
                $table->json('bank_account_details')->nullable()->after('admin_notes')->comment('Bank account details (encrypted)');
            }
        });
    }
};
