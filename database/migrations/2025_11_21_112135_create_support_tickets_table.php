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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 50)->unique()->comment('Unique ticket number');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('User who created ticket');
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->onDelete('set null')->comment('Merchant (if complaint against merchant)');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null')->comment('Support staff assigned');
            $table->string('category', 50)->comment('Category: technical, financial, content, fraud, other');
            $table->string('category_ar', 50)->nullable()->comment('الفئة بالعربية');
            $table->string('category_en', 50)->nullable()->comment('Category in English');
            $table->string('subject', 255)->comment('Ticket subject');
            $table->text('description')->comment('Ticket description');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->comment('Ticket priority');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed', 'cancelled'])->default('open')->comment('Ticket status');
            $table->json('metadata')->nullable()->comment('Additional ticket data');
            $table->timestamp('resolved_at')->nullable()->comment('Resolution timestamp');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('merchant_id');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('category');
            $table->index('priority');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
