<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            if (!Schema::hasColumn('withdrawals', 'method')) {
                $table->enum('method', ['bank', 'manual'])->default('bank')->after('amount')->comment('Withdrawal method');
            }
            if (!Schema::hasColumn('withdrawals', 'requested_at')) {
                $table->timestamp('requested_at')->nullable()->after('status')->comment('Request timestamp');
            }
            if (!Schema::hasColumn('withdrawals', 'processed_by_admin_id')) {
                $table->foreignId('processed_by_admin_id')->nullable()->after('requested_at')->constrained('users')->onDelete('set null')->comment('Admin who processed');
            }
            if (!Schema::hasColumn('withdrawals', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('processed_by_admin_id')->comment('Processing timestamp');
            }
            if (!Schema::hasColumn('withdrawals', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('processed_at')->comment('Admin notes');
            }
            if (!Schema::hasColumn('withdrawals', 'bank_account_details')) {
                $table->json('bank_account_details')->nullable()->after('admin_notes')->comment('Bank account details (encrypted)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            // Drop foreign key first if it exists
            if (Schema::hasColumn('withdrawals', 'processed_by_admin_id')) {
                $table->dropForeign(['processed_by_admin_id']);
            }
        });
        
        Schema::table('withdrawals', function (Blueprint $table) {
            // Now drop columns
            if (Schema::hasColumn('withdrawals', 'method')) {
                $table->dropColumn('method');
            }
            if (Schema::hasColumn('withdrawals', 'requested_at')) {
                $table->dropColumn('requested_at');
            }
            if (Schema::hasColumn('withdrawals', 'processed_by_admin_id')) {
                $table->dropColumn('processed_by_admin_id');
            }
            if (Schema::hasColumn('withdrawals', 'processed_at')) {
                $table->dropColumn('processed_at');
            }
            if (Schema::hasColumn('withdrawals', 'admin_notes')) {
                $table->dropColumn('admin_notes');
            }
            if (Schema::hasColumn('withdrawals', 'bank_account_details')) {
                $table->dropColumn('bank_account_details');
            }
        });
    }
};


