<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->onDelete('cascade')->comment('Merchant ID');
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade')->comment('Admin who issued warning');
            $table->string('warning_type', 50)->comment('Warning type: violation, quality, compliance, etc');
            $table->text('message')->comment('Warning message');
            $table->timestamp('issued_at')->comment('Issue timestamp');
            $table->timestamp('expires_at')->nullable()->comment('Expiration timestamp');
            $table->boolean('active')->default(true)->comment('Active status');
            $table->json('metadata')->nullable()->comment('Additional metadata');
            $table->timestamps();
            
            $table->index('merchant_id');
            $table->index('admin_id');
            $table->index('active');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_warnings');
    }
};


