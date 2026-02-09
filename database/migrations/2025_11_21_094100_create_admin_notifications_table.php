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
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255)->comment('Notification title');
            $table->string('title_ar', 255)->nullable()->comment('عنوان الإشعار بالعربية');
            $table->string('title_en', 255)->nullable()->comment('Notification title in English');
            $table->text('message')->comment('Notification message');
            $table->text('message_ar')->nullable()->comment('نص الإشعار بالعربية');
            $table->text('message_en')->nullable()->comment('Notification message in English');
            $table->enum('type', ['info', 'success', 'warning', 'error', 'promotion', 'system'])->default('info')->comment('Notification type');
            $table->enum('target_audience', ['all', 'users', 'merchants', 'admins', 'specific'])->default('all')->comment('Target audience');
            $table->json('target_user_ids')->nullable()->comment('Specific user IDs if target_audience is specific');
            $table->json('target_merchant_ids')->nullable()->comment('Specific merchant IDs if target_audience is specific');
            $table->string('action_url', 500)->nullable()->comment('Action URL when clicked');
            $table->string('action_text', 100)->nullable()->comment('Action button text');
            $table->string('image_url', 500)->nullable()->comment('Notification image URL');
            $table->boolean('is_sent')->default(false)->comment('Whether notification has been sent');
            $table->dateTime('scheduled_at')->nullable()->comment('Scheduled send time');
            $table->dateTime('sent_at')->nullable()->comment('Actual send time');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('Admin who created the notification');
            $table->timestamps();
            
            $table->index('type');
            $table->index('target_audience');
            $table->index('is_sent');
            $table->index('scheduled_at');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};

