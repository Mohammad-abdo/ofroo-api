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
        Schema::table('merchants', function (Blueprint $table) {
            if (!Schema::hasColumn('merchants', 'whatsapp_number')) {
                $table->string('whatsapp_number', 50)->nullable()->after('phone')->comment('WhatsApp number');
            }
            if (!Schema::hasColumn('merchants', 'whatsapp_link')) {
                $table->string('whatsapp_link', 500)->nullable()->after('whatsapp_number')->comment('WhatsApp direct link');
            }
            if (!Schema::hasColumn('merchants', 'whatsapp_enabled')) {
                $table->boolean('whatsapp_enabled')->default(true)->after('whatsapp_link')->comment('WhatsApp contact enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'whatsapp_link', 'whatsapp_enabled']);
        });
    }
};
