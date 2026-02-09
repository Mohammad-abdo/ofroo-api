<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (!Schema::hasColumn('merchants', 'status')) {
                $table->enum('status', ['active', 'suspended', 'disabled'])->default('active')->after('approved')->comment('Merchant status');
            }
            if (!Schema::hasColumn('merchants', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('status')->comment('Suspension timestamp');
            }
            if (!Schema::hasColumn('merchants', 'suspended_until')) {
                $table->timestamp('suspended_until')->nullable()->after('suspended_at')->comment('Suspension expiration');
            }
            if (!Schema::hasColumn('merchants', 'suspension_reason')) {
                $table->text('suspension_reason')->nullable()->after('suspended_until')->comment('Suspension reason');
            }
            if (!Schema::hasColumn('merchants', 'suspended_by_admin_id')) {
                $table->foreignId('suspended_by_admin_id')->nullable()->after('suspension_reason')->constrained('users')->onDelete('set null')->comment('Admin who suspended');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            // Drop foreign key first if it exists
            if (Schema::hasColumn('merchants', 'suspended_by_admin_id')) {
                $table->dropForeign(['suspended_by_admin_id']);
            }
        });
        
        Schema::table('merchants', function (Blueprint $table) {
            // Now drop columns
            if (Schema::hasColumn('merchants', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('merchants', 'suspended_at')) {
                $table->dropColumn('suspended_at');
            }
            if (Schema::hasColumn('merchants', 'suspended_until')) {
                $table->dropColumn('suspended_until');
            }
            if (Schema::hasColumn('merchants', 'suspension_reason')) {
                $table->dropColumn('suspension_reason');
            }
            if (Schema::hasColumn('merchants', 'suspended_by_admin_id')) {
                $table->dropColumn('suspended_by_admin_id');
            }
        });
    }
};


