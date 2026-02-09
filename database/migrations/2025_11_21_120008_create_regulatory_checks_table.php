<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->string('check_type', 50)->comment('Check type: kyc, compliance, tax, etc');
            $table->enum('result', ['passed', 'failed', 'pending', 'requires_review'])->default('pending')->comment('Check result');
            $table->text('details')->nullable()->comment('Check details');
            $table->text('notes')->nullable()->comment('Admin notes');
            $table->timestamp('checked_at')->comment('Check timestamp');
            $table->foreignId('checked_by_admin_id')->nullable()->constrained('users')->onDelete('set null')->comment('Admin who performed check');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('check_type');
            $table->index('result');
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_checks');
    }
};


