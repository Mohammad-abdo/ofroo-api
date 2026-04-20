<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppPolicy;
use App\Models\Offer;
use App\Models\Setting;
use App\Models\SocialLink;
use App\Support\ApiMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Static/CMS-style endpoints consumed by the mobile app:
 *  - GET /api/mobile/support         (item 4)
 *  - GET /api/mobile/app/about       (item 5)
 *  - GET /api/mobile/app/policy      (item 6 — mobile only)
 *  - GET /api/mobile/app/share       (item 3)
 *  - GET /api/mobile/offers/{id}/share (item 2)
 */
class AppContentController extends Controller
{
    /**
     * Help & Support info (email + WhatsApp), administered from the dashboard
     * through the existing `settings` table.
     *
     * Keys used:
     *   - support_email
     *   - support_whatsapp
     *   - support_phone (fallback)
     */
    public function support(): JsonResponse
    {
        $email = (string) Setting::getValue('support_email', '');
        $whatsapp = (string) Setting::getValue('support_whatsapp', '');
        if ($whatsapp === '') {
            $whatsapp = (string) Setting::getValue('support_phone', '');
        }

        $whatsappLink = $whatsapp !== ''
            ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp)
            : '';

        return response()->json([
            'data' => [
                'email' => $email,
                'whatsapp_number' => $whatsapp,
                'whatsapp_link' => $whatsappLink,
            ],
        ]);
    }

    /**
     * About app + social links (platform, url, icon).
     *
     * Sections are managed from the admin dashboard via
     *   /api/admin/app-sections?type=about   (CRUD)
     *
     * The response also keeps the legacy `description*` fields so older
     * mobile clients continue to render correctly — any new client should
     * prefer the `sections` array.
     */
    public function about(Request $request): JsonResponse
    {
        $language = $request->get('language', 'ar');

        $sections = $this->sectionsFor(AppPolicy::TYPE_ABOUT, $language, [
            'settings_key_ar' => 'app_description_ar',
            'settings_key_en' => 'app_description_en',
            'legacy_key_ar' => 'static_about_ar',
            'legacy_key_en' => 'static_about_en',
            'default_title_ar' => 'عن التطبيق',
            'default_title_en' => 'About App',
        ]);

        $descriptionAr = $sections[0]['description_ar'] ?? '';
        $descriptionEn = $sections[0]['description_en'] ?? '';
        $description = $language === 'en'
            ? ($descriptionEn !== '' ? $descriptionEn : $descriptionAr)
            : ($descriptionAr !== '' ? $descriptionAr : $descriptionEn);

        return response()->json([
            'data' => [
                'sections' => $sections,
                'description' => $description,
                'description_ar' => $descriptionAr,
                'description_en' => $descriptionEn,
                'social_links' => $this->socialLinks(),
                'app_version' => (string) Setting::getValue('app_version', config('app.version', '1.0.0')),
            ],
        ]);
    }

    /**
     * Privacy policy sections managed from the admin dashboard.
     * Falls back to the legacy `static_privacy_*` setting if the `app_policies`
     * table has no rows of type=privacy yet, so the mobile app never receives
     * an empty array during the transition period.
     */
    public function policy(Request $request): JsonResponse
    {
        $language = $request->get('language', 'ar');

        $sections = $this->sectionsFor(AppPolicy::TYPE_PRIVACY, $language, [
            'legacy_key_ar' => 'static_privacy_ar',
            'legacy_key_en' => 'static_privacy_en',
            'default_title_ar' => 'سياسة الخصوصية',
            'default_title_en' => 'Privacy Policy',
        ]);

        return response()->json(['data' => $sections]);
    }

    /**
     * Legal pages — all four content pages managed from the admin dashboard.
     *
     * Returns:
     *   - شروط الاستخدام   (terms)
     *   - سياسة الخصوصية  (privacy)
     *   - شروط التاجر      (merchant_terms)
     *   - قواعد المنصة    (platform_rules)
     *
     * Each item contains `{ type, label_ar, label_en, sections[] }`.
     * Sections are admin-managed rows from the `app_policies` table.
     *
     * GET /api/mobile/app/legal-pages
     * Optional query: ?language=ar|en   (default: ar)
     * Optional query: ?type=terms       (filter to a single type)
     */
    public function legalPages(Request $request): JsonResponse
    {
        $language = $request->get('language', 'ar');
        $filterType = $request->get('type');

        $typeConfig = [
            AppPolicy::TYPE_TERMS => [
                'label_ar' => 'شروط الاستخدام',
                'label_en' => 'Terms of Use',
                'default_title_ar' => 'شروط الاستخدام',
                'default_title_en' => 'Terms of Use',
            ],
            AppPolicy::TYPE_PRIVACY => [
                'label_ar' => 'سياسة الخصوصية',
                'label_en' => 'Privacy Policy',
                'legacy_key_ar' => 'static_privacy_ar',
                'legacy_key_en' => 'static_privacy_en',
                'default_title_ar' => 'سياسة الخصوصية',
                'default_title_en' => 'Privacy Policy',
            ],
            AppPolicy::TYPE_MERCHANT_TERMS => [
                'label_ar' => 'شروط التاجر',
                'label_en' => 'Merchant Terms',
                'default_title_ar' => 'شروط التاجر',
                'default_title_en' => 'Merchant Terms',
            ],
            AppPolicy::TYPE_PLATFORM_RULES => [
                'label_ar' => 'قواعد المنصة',
                'label_en' => 'Platform Rules',
                'default_title_ar' => 'قواعد المنصة',
                'default_title_en' => 'Platform Rules',
            ],
        ];

        if ($filterType !== null && array_key_exists($filterType, $typeConfig)) {
            $typeConfig = [$filterType => $typeConfig[$filterType]];
        }

        $result = [];
        foreach ($typeConfig as $type => $config) {
            $fallback = array_diff_key($config, array_flip(['label_ar', 'label_en']));
            $sections = $this->sectionsFor($type, $language, $fallback);
            $result[] = [
                'type'     => $type,
                'label_ar' => $config['label_ar'],
                'label_en' => $config['label_en'],
                'label'    => $language === 'en' ? $config['label_en'] : $config['label_ar'],
                'sections' => $sections,
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Load and shape sections for the mobile app, preferring admin-managed
     * rows in `app_policies` and falling back to legacy settings keys.
     *
     * @param  array{
     *     settings_key_ar?: string,
     *     settings_key_en?: string,
     *     legacy_key_ar?: string,
     *     legacy_key_en?: string,
     *     default_title_ar?: string,
     *     default_title_en?: string,
     * }  $fallback
     * @return array<int, array<string, mixed>>
     */
    protected function sectionsFor(string $type, string $language, array $fallback): array
    {
        if (Schema::hasTable('app_policies')) {
            $rows = AppPolicy::query()
                ->ofType($type)
                ->where('is_active', true)
                ->orderBy('order_index')
                ->orderBy('id')
                ->get();

            if ($rows->isNotEmpty()) {
                return $rows->map(function (AppPolicy $p) use ($language) {
                    return [
                        'id' => (int) $p->id,
                        'type' => (string) $p->type,
                        'title' => $language === 'en'
                            ? ($p->title_en ?: ($p->title_ar ?? ''))
                            : ($p->title_ar ?: ($p->title_en ?? '')),
                        'title_ar' => (string) ($p->title_ar ?? ''),
                        'title_en' => (string) ($p->title_en ?? ''),
                        'description' => $language === 'en'
                            ? ($p->description_en ?: ($p->description_ar ?? ''))
                            : ($p->description_ar ?: ($p->description_en ?? '')),
                        'description_ar' => (string) ($p->description_ar ?? ''),
                        'description_en' => (string) ($p->description_en ?? ''),
                    ];
                })->values()->all();
            }
        }

        $fromSettingAr = isset($fallback['settings_key_ar'])
            ? (string) Setting::getValue($fallback['settings_key_ar'], '')
            : '';
        $fromSettingEn = isset($fallback['settings_key_en'])
            ? (string) Setting::getValue($fallback['settings_key_en'], '')
            : '';
        $legacyAr = isset($fallback['legacy_key_ar'])
            ? (string) Setting::getValue($fallback['legacy_key_ar'], '')
            : '';
        $legacyEn = isset($fallback['legacy_key_en'])
            ? (string) Setting::getValue($fallback['legacy_key_en'], '')
            : '';

        $descriptionAr = $fromSettingAr !== '' ? $fromSettingAr : $legacyAr;
        $descriptionEn = $fromSettingEn !== '' ? $fromSettingEn : $legacyEn;

        if ($descriptionAr === '' && $descriptionEn === '') {
            return [];
        }

        $titleAr = (string) ($fallback['default_title_ar'] ?? '');
        $titleEn = (string) ($fallback['default_title_en'] ?? '');

        return [[
            'id' => 1,
            'type' => $type,
            'title' => $language === 'en' ? ($titleEn ?: $titleAr) : ($titleAr ?: $titleEn),
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'description' => $language === 'en'
                ? ($descriptionEn !== '' ? $descriptionEn : $descriptionAr)
                : ($descriptionAr !== '' ? $descriptionAr : $descriptionEn),
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
        ]];
    }

    /**
     * Share OFROO app via social media platforms (item 3).
     */
    public function shareApp(): JsonResponse
    {
        $androidUrl = (string) Setting::getValue('play_store_url', '');
        $iosUrl = (string) Setting::getValue('app_store_url', '');
        $webLanding = (string) Setting::getValue('app_landing_url', '');
        if ($webLanding === '') {
            $webLanding = rtrim((string) config('app.url', ''), '/');
        }

        $primaryLink = $webLanding !== '' ? $webLanding : ($androidUrl !== '' ? $androidUrl : $iosUrl);
        $messageAr = (string) Setting::getValue(
            'app_share_message_ar',
            'حمّل تطبيق OFROO للحصول على أفضل العروض والكوبونات'
        );
        $messageEn = (string) Setting::getValue(
            'app_share_message_en',
            'Download the OFROO app for the best offers and coupons'
        );

        $text = $messageAr !== '' ? $messageAr : $messageEn;
        $textEncoded = rawurlencode(trim($text . ' ' . $primaryLink));

        $platforms = [
            [
                'platform' => 'whatsapp',
                'share_url' => 'https://wa.me/?text=' . $textEncoded,
                'icon' => ApiMediaUrl::publicAbsolute('images/share/whatsapp.png'),
            ],
            [
                'platform' => 'facebook',
                'share_url' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($primaryLink),
                'icon' => ApiMediaUrl::publicAbsolute('images/share/facebook.png'),
            ],
            [
                'platform' => 'snapchat',
                // Snapchat web share uses creative kit URL; fallback deep link works on mobile.
                'share_url' => 'https://www.snapchat.com/scan?attachmentUrl=' . rawurlencode($primaryLink),
                'icon' => ApiMediaUrl::publicAbsolute('images/share/snapchat.png'),
            ],
            [
                'platform' => 'tiktok',
                // TikTok has no first-party web share, use a deep link the app handles.
                'share_url' => 'https://www.tiktok.com/upload?lang=en&url=' . rawurlencode($primaryLink),
                'icon' => ApiMediaUrl::publicAbsolute('images/share/tiktok.png'),
            ],
        ];

        return response()->json([
            'data' => [
                'app_link' => $primaryLink,
                'android_url' => $androidUrl,
                'ios_url' => $iosUrl,
                'message_ar' => $messageAr,
                'message_en' => $messageEn,
                'platforms' => $platforms,
            ],
        ]);
    }

    /**
     * Share a single offer with friends (item 2).
     * Returns a payload that includes the offer preview and a deep link
     * that opens the OFROO app directly on the offer details screen.
     */
    public function shareOffer(Request $request, string $offerId): JsonResponse
    {
        $offer = Offer::with(['merchant', 'category'])->findOrFail($offerId);

        $scheme = (string) Setting::getValue('app_deep_link_scheme', 'ofroo');
        $webLanding = (string) Setting::getValue('app_landing_url', rtrim((string) config('app.url', ''), '/'));

        $deepLink = $scheme . '://offers/' . $offer->id;
        $webLink = rtrim($webLanding, '/') . '/offers/' . $offer->id;
        $universalLink = (string) Setting::getValue('app_universal_link_base', '');
        if ($universalLink !== '') {
            $universalLink = rtrim($universalLink, '/') . '/offers/' . $offer->id;
        } else {
            $universalLink = $webLink;
        }

        $language = $request->get('language', 'ar');
        $title = $language === 'en'
            ? ($offer->title_en ?? $offer->title ?? '')
            : ($offer->title ?? $offer->title_en ?? '');
        $description = $language === 'en'
            ? ($offer->description_en ?? $offer->description ?? '')
            : ($offer->description ?? $offer->description_en ?? '');

        $images = ApiMediaUrl::absoluteList($offer->offer_images ?? []);
        $cover = $images[0] ?? '';

        $shareText = $title !== ''
            ? ($language === 'en'
                ? "Check out this offer on OFROO: {$title}"
                : "شاهد هذا العرض على OFROO: {$title}")
            : ($language === 'en' ? 'Check out this offer on OFROO' : 'شاهد هذا العرض على OFROO');

        $textEncoded = rawurlencode($shareText . ' ' . $universalLink);

        return response()->json([
            'data' => [
                'offer' => [
                    'id' => $offer->id,
                    'title' => $title,
                    'title_ar' => $offer->title ?? '',
                    'title_en' => $offer->title_en ?? '',
                    'description' => $description,
                    'price' => (float) ($offer->price ?? 0),
                    'discount' => (float) ($offer->discount ?? 0),
                    'image' => $cover,
                    'images' => $images,
                    'merchant' => $offer->merchant ? [
                        'id' => $offer->merchant->id,
                        'company_name' => $offer->merchant->company_name
                            ?? $offer->merchant->company_name_ar
                            ?? $offer->merchant->company_name_en
                            ?? '',
                        'logo_url' => ApiMediaUrl::publicAbsolute(
                            is_string($offer->merchant->logo_url) ? $offer->merchant->logo_url : ''
                        ),
                    ] : null,
                ],
                'share' => [
                    'text' => $shareText,
                    'app_link' => $deepLink,
                    'deep_link' => $deepLink,
                    'web_link' => $webLink,
                    'universal_link' => $universalLink,
                    'platforms' => [
                        [
                            'platform' => 'whatsapp',
                            'share_url' => 'https://wa.me/?text=' . $textEncoded,
                        ],
                        [
                            'platform' => 'facebook',
                            'share_url' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($universalLink),
                        ],
                        [
                            'platform' => 'snapchat',
                            'share_url' => 'https://www.snapchat.com/scan?attachmentUrl=' . rawurlencode($universalLink),
                        ],
                        [
                            'platform' => 'tiktok',
                            'share_url' => 'https://www.tiktok.com/upload?lang=en&url=' . rawurlencode($universalLink),
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Build a normalised list of social links for About/Share responses.
     *
     * @return array<int, array<string, string>>
     */
    private function socialLinks(): array
    {
        if (! Schema::hasTable('social_links')) {
            return [];
        }

        return SocialLink::query()
            ->orderBy('platform')
            ->get()
            ->filter(fn (SocialLink $link) => is_string($link->url) && trim($link->url) !== '')
            ->map(fn (SocialLink $link) => [
                'platform' => (string) $link->platform,
                'url' => (string) $link->url,
                'icon' => ApiMediaUrl::publicAbsolute('images/social/' . $link->platform . '.png'),
            ])
            ->values()
            ->all();
    }
}
