<?php

use App\Models\Ad;
use App\Models\Offer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (! Schema::hasColumn('ads', 'offer_id')) {
                $table->foreignId('offer_id')
                    ->nullable()
                    ->after('merchant_id')
                    ->constrained('offers')
                    ->nullOnDelete();
                $table->index(['offer_id', 'merchant_id']);
            }
        });

        // Safe backfill (no deletes): if an ad has merchant_id and offer_id is null,
        // attach any existing offer for that merchant.
        if (Schema::hasColumn('ads', 'offer_id') && Schema::hasColumn('ads', 'merchant_id')) {
            Ad::query()
                ->whereNull('offer_id')
                ->whereNotNull('merchant_id')
                ->select(['id', 'merchant_id'])
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $ad) {
                        $offerId = Offer::query()
                            ->where('merchant_id', $ad->merchant_id)
                            ->orderByDesc('id')
                            ->value('id');
                        if ($offerId) {
                            Ad::query()->whereKey($ad->id)->update(['offer_id' => (int) $offerId]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (Schema::hasColumn('ads', 'offer_id')) {
                $table->dropForeign(['offer_id']);
                $table->dropIndex(['offer_id', 'merchant_id']);
                $table->dropColumn('offer_id');
            }
        });
    }
};

