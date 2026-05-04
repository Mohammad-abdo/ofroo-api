<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'offer_id')) {
                $table->foreignId('offer_id')->nullable()->after('order_id')->constrained('offers')->nullOnDelete();
                $table->index(['offer_id', 'visible_to_public']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'offer_id')) {
                $table->dropForeign(['offer_id']);
                $table->dropColumn('offer_id');
            }
        });
    }
};
