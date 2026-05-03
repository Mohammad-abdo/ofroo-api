<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppCouponSetting;
use App\Models\AppPolicy;
use App\Models\Setting;
use App\Models\SocialLink;
use App\Support\ImageUploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /** Persisted in `app_coupon_settings` and `social_links`, not the generic `settings` table. */
    private const DEDICATED_APP_SETTING_KEYS = [
        'max_coupons_per_merchant', 'coupon_expiry_days', 'auto_cancel_enabled', 'days_before_cancel',
        'grace_period_hours', 'notify_merchant', 'notify_user', 'auto_refund',
        'instagram_url', 'facebook_url', 'twitter_url', 'youtube_url', 'snapchat_url', 'telegram_url', 'tiktok_url', 'whatsapp_url',
    ];

    /**
     * Get settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $settings = Cache::remember('app_settings', 3600, function () {
            return Setting::all()->mapWithKeys(function ($setting) {
                return [$setting->key => [
                    'value' => $setting->value,
                    'type' => $setting->type,
                    'description' => $setting->description,
                    'description_ar' => $setting->description_ar,
                    'description_en' => $setting->description_en,
                ]];
            });
        });

        $settings = $this->overlayAppCouponSettingsAndSocialLinks($settings);

        return response()->json([
            'data' => $settings,
            'static_sections' => $this->getStaticSectionsForAdmin(),
            'endpoints' => [
                'app_sections_crud' => '/api/admin/app-sections',
                'app_policies_crud' => '/api/admin/app-policies',
                'mobile_policy' => '/api/mobile/app/policy',
                'mobile_about' => '/api/mobile/app/about',
                'mobile_support' => '/api/mobile/support',
            ],
        ]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getStaticSectionsForAdmin(): array
    {
        $out = [
            AppPolicy::TYPE_PRIVACY => [],
            AppPolicy::TYPE_ABOUT => [],
            AppPolicy::TYPE_SUPPORT => [],
        ];

        if (! Schema::hasTable('app_policies')) {
            return $out;
        }

        AppPolicy::query()
            ->orderBy('type')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get()
            ->each(function (AppPolicy $p) use (&$out) {
                $type = (string) ($p->type ?? AppPolicy::TYPE_PRIVACY);
                if (! isset($out[$type])) {
                    $out[$type] = [];
                }
                $out[$type][] = [
                    'id' => (int) $p->id,
                    'type' => $type,
                    'title_ar' => (string) ($p->title_ar ?? ''),
                    'title_en' => (string) ($p->title_en ?? ''),
                    'description_ar' => (string) ($p->description_ar ?? ''),
                    'description_en' => (string) ($p->description_en ?? ''),
                    'order_index' => (int) $p->order_index,
                    'is_active' => (bool) $p->is_active,
                ];
            });

        return $out;
    }

    /**
     * Upload application logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => ImageUploadRules::requiredFileMax(2048),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $request->hasFile('logo')) {
            return response()->json([
                'message' => 'No logo file provided',
            ], 422);
        }

        $file = $request->file('logo');

        $logoName = 'app_logo_'.time().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('settings', $logoName, 'public');
        $logoUrl = asset('storage/'.$path);

        Setting::updateOrCreate(
            ['key' => 'app_logo'],
            [
                'value' => $logoUrl,
                'type' => 'string',
            ]
        );

        Cache::forget('app_settings');

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'data' => [
                'logo_url' => $logoUrl,
            ],
        ]);
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $settingsToUpdate = [];

        if ($request->has('settings') && is_array($request->settings)) {
            $request->validate([
                'settings' => 'required|array',
                'settings.*.key' => 'required|string',
                'settings.*.value' => 'required',
            ]);
            $settingsToUpdate = [];
            $dedicatedKv = [];
            foreach ($request->settings as $item) {
                $k = $item['key'] ?? null;
                if ($k === null || $k === '') {
                    continue;
                }
                if (in_array($k, self::DEDICATED_APP_SETTING_KEYS, true)) {
                    $dedicatedKv[$k] = $item['value'] ?? null;
                } else {
                    $settingsToUpdate[] = $item;
                }
            }
            $this->syncDedicatedSettingsFromKeyValue($dedicatedKv);
        } else {
            $validated = $request->validate([
                'app_name' => 'nullable|string|max:255',
                'default_language' => 'nullable|in:ar,en',
                'max_coupons_per_merchant' => 'nullable|integer|min:1',
                'coupon_expiry_days' => 'nullable|integer|min:1',
                'auto_cancel_enabled' => 'nullable|boolean',
                'days_before_cancel' => 'nullable|integer|min:1',
                'grace_period_hours' => 'nullable|integer|min:0',
                'notify_merchant' => 'nullable|boolean',
                'notify_user' => 'nullable|boolean',
                'auto_refund' => 'nullable|boolean',
                'instagram_url' => 'nullable|string|max:500',
                'facebook_url' => 'nullable|string|max:500',
                'twitter_url' => 'nullable|string|max:500',
                'youtube_url' => 'nullable|string|max:500',
                'snapchat_url' => 'nullable|string|max:500',
                'telegram_url' => 'nullable|string|max:500',
                'tiktok_url' => 'nullable|string|max:500',
                'whatsapp_url' => 'nullable|string|max:500',
                'static_complaints_ar' => 'nullable|string',
                'static_complaints_en' => 'nullable|string',
                'static_privacy_ar' => 'nullable|string',
                'static_privacy_en' => 'nullable|string',
                'static_support_ar' => 'nullable|string',
                'static_support_en' => 'nullable|string',
                'static_about_ar' => 'nullable|string',
                'static_about_en' => 'nullable|string',
                'support_email' => 'nullable|email|max:255',
                'support_whatsapp' => 'nullable|string|max:32',
                'support_phone' => 'nullable|string|max:32',
                'app_description_ar' => 'nullable|string',
                'app_description_en' => 'nullable|string',
                'app_version' => 'nullable|string|max:32',
                'play_store_url' => 'nullable|url|max:500',
                'app_store_url' => 'nullable|url|max:500',
                'app_landing_url' => 'nullable|url|max:500',
                'app_share_message_ar' => 'nullable|string|max:1000',
                'app_share_message_en' => 'nullable|string|max:1000',
                'app_deep_link_scheme' => 'nullable|string|max:64',
                'app_universal_link_base' => 'nullable|url|max:500',
                'currency' => 'nullable|string|max:8',
            ]);

            foreach ($validated as $key => $value) {
                if (in_array($key, self::DEDICATED_APP_SETTING_KEYS, true)) {
                    continue;
                }
                $settingsToUpdate[] = [
                    'key' => $key,
                    'value' => $value,
                ];
            }
            $this->syncDedicatedSettingsFromKeyValue($validated);
        }

        foreach ($settingsToUpdate as $settingData) {
            $key = $settingData['key'] ?? $settingData[0] ?? null;
            $value = $settingData['value'] ?? $settingData[1] ?? null;

            if ($key === null || $key === '') {
                continue;
            }

            if ($value === null) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_array($value)
                        ? json_encode($value)
                        : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
                    'type' => $settingData['type'] ?? $this->detectSettingType($value),
                ]
            );
        }

        Cache::forget('app_settings');

        return response()->json([
            'message' => 'Settings updated successfully',
        ]);
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $settings
     * @return Collection<string, array<string, mixed>>
     */
    private function overlayAppCouponSettingsAndSocialLinks($settings)
    {
        if (! Schema::hasTable('app_coupon_settings')) {
            return $settings;
        }

        $policy = AppCouponSetting::query()->first();
        if ($policy) {
            $settings['max_coupons_per_merchant'] = $this->adminSettingMeta((string) $policy->max_coupons_per_merchant, 'integer');
            $settings['coupon_expiry_days'] = $this->adminSettingMeta((string) $policy->coupon_expiry_days, 'integer');
            $settings['auto_cancel_enabled'] = $this->adminSettingMeta($policy->auto_cancel_enabled ? '1' : '0', 'boolean');
            $settings['days_before_cancel'] = $this->adminSettingMeta((string) $policy->days_before_cancel, 'integer');
            $settings['grace_period_hours'] = $this->adminSettingMeta((string) $policy->grace_period_hours, 'integer');
            $settings['notify_merchant'] = $this->adminSettingMeta($policy->notify_merchant ? '1' : '0', 'boolean');
            $settings['notify_user'] = $this->adminSettingMeta($policy->notify_user ? '1' : '0', 'boolean');
            $settings['auto_refund'] = $this->adminSettingMeta($policy->auto_refund ? '1' : '0', 'boolean');
        }

        if (Schema::hasTable('social_links')) {
            foreach (SocialLink::query()->orderBy('platform')->get() as $link) {
                $reqKey = SocialLink::PLATFORM_TO_REQUEST_KEY[$link->platform] ?? ($link->platform.'_url');
                $settings[$reqKey] = $this->adminSettingMeta((string) ($link->url ?? ''), 'string');
            }
        }

        return $settings;
    }

    /**
     * @return array{value: string, type: string, description: null, description_ar: null, description_en: null}
     */
    private function adminSettingMeta(string $value, string $type): array
    {
        return [
            'value' => $value,
            'type' => $type,
            'description' => null,
            'description_ar' => null,
            'description_en' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $kv
     */
    private function syncDedicatedSettingsFromKeyValue(array $kv): void
    {
        if ($kv === [] || ! Schema::hasTable('app_coupon_settings')) {
            return;
        }

        $policy = AppCouponSetting::current();
        $policyUpdates = [];

        if (array_key_exists('max_coupons_per_merchant', $kv) && $kv['max_coupons_per_merchant'] !== null) {
            $policyUpdates['max_coupons_per_merchant'] = max(1, (int) $kv['max_coupons_per_merchant']);
        }
        if (array_key_exists('coupon_expiry_days', $kv) && $kv['coupon_expiry_days'] !== null) {
            $policyUpdates['coupon_expiry_days'] = max(1, (int) $kv['coupon_expiry_days']);
        }
        if (array_key_exists('auto_cancel_enabled', $kv)) {
            $policyUpdates['auto_cancel_enabled'] = filter_var($kv['auto_cancel_enabled'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('days_before_cancel', $kv) && $kv['days_before_cancel'] !== null) {
            $policyUpdates['days_before_cancel'] = max(1, (int) $kv['days_before_cancel']);
        }
        if (array_key_exists('grace_period_hours', $kv) && $kv['grace_period_hours'] !== null) {
            $policyUpdates['grace_period_hours'] = max(0, (int) $kv['grace_period_hours']);
        }
        if (array_key_exists('notify_merchant', $kv)) {
            $policyUpdates['notify_merchant'] = filter_var($kv['notify_merchant'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('notify_user', $kv)) {
            $policyUpdates['notify_user'] = filter_var($kv['notify_user'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('auto_refund', $kv)) {
            $policyUpdates['auto_refund'] = filter_var($kv['auto_refund'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($policyUpdates !== []) {
            $policy->update($policyUpdates);
        }

        if (! Schema::hasTable('social_links')) {
            return;
        }

        foreach (SocialLink::PLATFORM_TO_REQUEST_KEY as $platform => $requestKey) {
            if (! array_key_exists($requestKey, $kv)) {
                continue;
            }
            $raw = $kv[$requestKey];
            $url = $raw === null || $raw === '' ? null : (string) $raw;
            SocialLink::query()->updateOrCreate(
                ['platform' => $platform],
                ['url' => $url]
            );
        }
    }

    private function detectSettingType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_array($value)) {
            return 'array';
        }

        return 'string';
    }
}
