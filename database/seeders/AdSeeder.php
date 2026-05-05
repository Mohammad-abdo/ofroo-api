<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Offer;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class AdSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $merchants = Merchant::where('approved', true)->pluck('id')->toArray();
        $categories = Category::whereNull('parent_id')->pluck('id')->toArray();

        // Only seed banner + video (safe: INSERT only, no deletes).
        $adTypes = ['banner', 'video'];
        $targetPerType = [
            'banner' => 20,
            'video' => 10,
        ];

        $adTitles = [
            ['ar' => 'عرض خاص - خصم حتى 50%', 'en' => 'Special Offer - Up to 50% Off'],
            ['ar' => 'تسوّق الآن واستمتع بالعروض', 'en' => 'Shop Now & Enjoy Deals'],
            ['ar' => 'أفضل المنتجات بأقل الأسعار', 'en' => 'Best Products at Lowest Prices'],
            ['ar' => 'جديد! تشكيلة الموسم', 'en' => 'New! Season Collection'],
            ['ar' => 'اشترِ واحد واحصل على الثاني مجاناً', 'en' => 'Buy One Get One Free'],
            ['ar' => 'عروض رمضان الحصرية', 'en' => 'Exclusive Ramadan Offers'],
            ['ar' => 'تخفيضات نهاية الموسم', 'en' => 'End of Season Sale'],
            ['ar' => 'احصل على كاش باك 10%', 'en' => 'Get 10% Cashback'],
            ['ar' => 'أول طلب مجاناً!', 'en' => 'First Order Free!'],
            ['ar' => 'سجّل واحصل على كوبون خصم', 'en' => 'Register & Get Discount Coupon'],
        ];

        // Idempotent seeding: only create missing rows up to targetPerType (no duplicates on rerun).
        $existingCounts = Ad::query()
            ->whereIn('ad_type', $adTypes)
            ->selectRaw('ad_type, COUNT(*) as c')
            ->groupBy('ad_type')
            ->pluck('c', 'ad_type')
            ->all();

        foreach ($adTypes as $type) {
            $existing = (int) ($existingCounts[$type] ?? 0);
            $target = (int) ($targetPerType[$type] ?? 0);
            $toCreate = max(0, $target - $existing);
            if ($toCreate <= 0) {
                continue;
            }

            $baseOrderIndex = (int) (Ad::query()->max('order_index') ?? 0);

            for ($i = 0; $i < $toCreate; $i++) {
                $title = $faker->randomElement($adTitles);
                $isActive = $faker->boolean(75);
                $startDate = Carbon::createFromInterface($faker->dateTimeBetween('-60 days', 'now'))->utc();
                $endDate = Carbon::createFromInterface($faker->dateTimeBetween('now', '+90 days'))->utc();
                $merchantId = $faker->optional(0.5) ? $faker->randomElement($merchants) : null;
                $offerId = null;
                if ($merchantId) {
                    $offerId = Offer::query()->where('merchant_id', $merchantId)->inRandomOrder()->value('id');
                }

                try {
                    Ad::create([
                        'title' => $title['ar'],
                        'title_ar' => $title['ar'],
                        'title_en' => $title['en'],
                        'description' => $faker->realText(200),
                        'description_ar' => $faker->realText(200),
                        'description_en' => $faker->text(150),
                        'image_url' => 'ads/banner_'.$faker->numberBetween(1, 10).'.jpg',
                        // For video ads, seed a demo video URL (can be replaced by real uploads later).
                        'video_url' => $type === 'video'
                            ? $faker->randomElement([
                                'https://samplelib.com/lib/preview/mp4/sample-5s.mp4',
                                'https://samplelib.com/lib/preview/mp4/sample-10s.mp4',
                            ])
                            : null,
                        'images' => $faker->optional(0.4) ? [
                            'ads/slide_'.$faker->numberBetween(1, 5).'.jpg',
                            'ads/slide_'.$faker->numberBetween(6, 10).'.jpg',
                        ] : null,
                        'link_url' => $faker->optional(0.6)->url(),
                        'ad_type' => $type,
                        'merchant_id' => $merchantId,
                        'offer_id' => $offerId,
                        'category_id' => $faker->optional(0.3) ? $faker->randomElement($categories) : null,
                        'is_active' => $isActive,
                        'order_index' => $baseOrderIndex + $i + 1,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'clicks_count' => $faker->numberBetween(0, 5000),
                        'views_count' => $faker->numberBetween(100, 50000),
                        'total_budget' => $faker->optional(0.4)->randomFloat(2, 100, 10000),
                    ]);
                } catch (\Throwable $e) {
                    $this->command->warn("Ad skip: {$e->getMessage()}");
                }
            }
        }

        $this->command->info('Ads seeded ('.Ad::count().' total).');
    }
}
