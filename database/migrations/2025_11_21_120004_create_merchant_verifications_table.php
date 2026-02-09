<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->string('business_registration_doc_path', 500)->nullable()->comment('Commercial registration document');
            $table->string('id_card_path', 500)->nullable()->comment('Owner ID card');
            $table->string('tax_registration_doc_path', 500)->nullable()->comment('Tax registration document');
            $table->string('proof_of_address_path', 500)->nullable()->comment('Proof of address');
            $table->json('additional_docs')->nullable()->comment('Additional documents JSON');
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending')->comment('Verification status');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users')->onDelete('set null')->comment('Admin reviewer');
            $table->timestamp('reviewed_at')->nullable()->comment('Review timestamp');
            $table->text('rejection_reason')->nullable()->comment('Rejection reason');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('status');
            $table->index('reviewed_by_admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_verifications');
    }
};


