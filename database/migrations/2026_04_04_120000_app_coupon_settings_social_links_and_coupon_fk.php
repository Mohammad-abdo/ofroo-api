<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_coupon_settings')) {
            Schema::create('app_coupon_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('max_coupons_per_merchant')->default(50);
                $table->unsignedInteger('coupon_expiry_days')->default(30);
                $table->boolean('auto_cancel_enabled')->default(false);
                $table->unsignedInteger('days_before_cancel')->default(7);
                $table->unsignedInteger('grace_period_hours')->default(24);
                $table->boolean('notify_merchant')->default(false);
                $table->boolean('notify_user')->default(false);
                $table->boolean('auto_refund')->default(false);
                $table->timestamps();
            });

            DB::table('app_coupon_settings')->insert([
                'id' => 1,
                'max_coupons_per_merchant' => 50,
                'coupon_expiry_days' => 30,
                'auto_cancel_enabled' => false,
                'days_before_cancel' => 7,
                'grace_period_hours' => 24,
                'notify_merchant' => false,
                'notify_user' => false,
                'auto_refund' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('social_links')) {
            Schema::create('social_links', function (Blueprint $table) {
                $table->id();
                $table->string('platform', 32)->unique();
                $table->string('url', 500)->nullable();
                $table->timestamps();
            });

            $platforms = ['instagram', 'facebook', 'twitter', 'youtube', 'snapchat', 'telegram', 'tiktok', 'whatsapp'];
            foreach ($platforms as $platform) {
                DB::table('social_links')->insert([
                    'platform' => $platform,
                    'url' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('settings')) {
            $get = static function (string $key): ?string {
                $row = DB::table('settings')->where('key', $key)->first();

                return $row ? (string) $row->value : null;
            };

            $policyUpdates = array_filter([
                'max_coupons_per_merchant' => ($v = $get('max_coupons_per_merchant')) !== null && $v !== '' ? max(1, (int) $v) : null,
                'coupon_expiry_days' => ($v = $get('coupon_expiry_days')) !== null && $v !== '' ? max(1, (int) $v) : null,
                'auto_cancel_enabled' => ($v = $get('auto_cancel_enabled')) !== null ? filter_var($v, FILTER_VALIDATE_BOOLEAN) : null,
                'days_before_cancel' => ($v = $get('days_before_cancel')) !== null && $v !== '' ? max(1, (int) $v) : null,
                'grace_period_hours' => ($v = $get('grace_period_hours')) !== null && $v !== '' ? max(0, (int) $v) : null,
                'notify_merchant' => ($v = $get('notify_merchant')) !== null ? filter_var($v, FILTER_VALIDATE_BOOLEAN) : null,
                'notify_user' => ($v = $get('notify_user')) !== null ? filter_var($v, FILTER_VALIDATE_BOOLEAN) : null,
                'auto_refund' => ($v = $get('auto_refund')) !== null ? filter_var($v, FILTER_VALIDATE_BOOLEAN) : null,
            ], fn ($v) => $v !== null);
            if ($policyUpdates !== []) {
                $policyUpdates['updated_at'] = now();
                DB::table('app_coupon_settings')->where('id', 1)->update($policyUpdates);
            }

            $map = [
                'instagram' => 'instagram_url',
                'facebook' => 'facebook_url',
                'twitter' => 'twitter_url',
                'youtube' => 'youtube_url',
                'snapchat' => 'snapchat_url',
                'telegram' => 'telegram_url',
                'tiktok' => 'tiktok_url',
                'whatsapp' => 'whatsapp_url',
            ];
            foreach ($map as $platform => $settingKey) {
                $url = $get($settingKey);
                if ($url !== null && $url !== '') {
                    DB::table('social_links')->where('platform', $platform)->update([
                        'url' => $url,
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('coupons') && ! Schema::hasColumn('coupons', 'coupon_setting_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->foreignId('coupon_setting_id')
                    ->default(1)
                    ->after('offer_id')
                    ->constrained('app_coupon_settings')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'coupon_setting_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropForeign(['coupon_setting_id']);
                $table->dropColumn('coupon_setting_id');
            });
        }
        Schema::dropIfExists('social_links');
        Schema::dropIfExists('app_coupon_settings');
    }
};
