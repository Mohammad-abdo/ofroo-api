<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Merchant;
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

        $positions = ['home_top', 'home_middle', 'category_top', 'offer_detail', 'sidebar', 'footer'];
        // Include 'video' so /api/mobile/ads/video returns results in demo DB.
        $adTypes = ['banner', 'popup', 'sidebar', 'inline', 'video'];

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

        for ($i = 0; $i < 30; $i++) {
            $title = $faker->randomElement($adTitles);
            $isActive = $faker->boolean(75);
            $startDate = Carbon::createFromInterface($faker->dateTimeBetween('-60 days', 'now'))->utc();
            $endDate = Carbon::createFromInterface($faker->dateTimeBetween('now', '+90 days'))->utc();
            $type = $faker->randomElement($adTypes);

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
                    'position' => $faker->randomElement($positions),
                    'ad_type' => $type,
                    'merchant_id' => $faker->optional(0.5) ? $faker->randomElement($merchants) : null,
                    'category_id' => $faker->optional(0.3) ? $faker->randomElement($categories) : null,
                    'is_active' => $isActive,
                    'order_index' => $i + 1,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'clicks_count' => $faker->numberBetween(0, 5000),
                    'views_count' => $faker->numberBetween(100, 50000),
                    'cost_per_click' => $faker->optional(0.4)->randomFloat(2, 0.1, 5),
                    'total_budget' => $faker->optional(0.4)->randomFloat(2, 100, 10000),
                ]);
            } catch (\Throwable $e) {
                $this->command->warn("Ad skip: {$e->getMessage()}");
            }
        }

        $this->command->info('Ads seeded ('.Ad::count().' total).');
    }
}
