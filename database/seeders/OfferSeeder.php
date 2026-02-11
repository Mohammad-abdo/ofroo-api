<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\Offer;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class OfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $categories = Category::all();
        $merchants = Merchant::where('approved', true)->get();

        $offerTitles = [
            ['ar' => 'خصم 50% على جميع المنتجات', 'en' => '50% Discount on All Products'],
            ['ar' => 'عرض خاص: خصم 30%', 'en' => 'Special Offer: 30% Off'],
            ['ar' => 'شراء اثنين واحصل على واحد مجاناً', 'en' => 'Buy Two Get One Free'],
            ['ar' => 'خصم 25% على الوجبات', 'en' => '25% Off on Meals'],
            ['ar' => 'عرض العائلة: خصم 40%', 'en' => 'Family Offer: 40% Off'],
            ['ar' => 'خصم 20% على جميع الأصناف', 'en' => '20% Off on All Items'],
            ['ar' => 'عرض نهاية الأسبوع', 'en' => 'Weekend Special'],
            ['ar' => 'خصم 35% على الملابس', 'en' => '35% Off on Clothing'],
            ['ar' => 'عرض الصيف الكبير', 'en' => 'Big Summer Sale'],
            ['ar' => 'خصم 15% على الإلكترونيات', 'en' => '15% Off on Electronics'],
        ];

        for ($i = 0; $i < 100; $i++) {
            $merchant = $merchants->random();
            $category = $categories->random();
            $branch = Branch::where('merchant_id', $merchant->id)->first();
            $title = $offerTitles[array_rand($offerTitles)];

            $originalPrice = $faker->randomFloat(2, 10, 500);
            $discountPercent = $faker->randomElement([10, 15, 20, 25, 30, 35, 40, 50]);
            $price = $originalPrice * (1 - $discountPercent / 100);
            $status = $faker->randomElement(['active', 'pending', 'expired', 'draft']);
            $startDate = $faker->dateTimeBetween('-30 days', 'now');
            $endDate = $faker->dateTimeBetween('now', '+60 days');

            $offer = Offer::create([
                'merchant_id' => $merchant->id,
                'category_id' => $category->id,
                'title' => $title['ar'],
                'description' => $faker->realText(300),
                'price' => round($price, 2),
                'discount' => $discountPercent,
                'offer_images' => [
                    'offers/image' . $faker->numberBetween(1, 10) . '.jpg',
                ],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);

            if ($branch) {
                $offer->branches()->sync([$branch->id]);
            }

            $barcode = 'OFF-' . strtoupper($faker->unique()->bothify('########'));
            Coupon::create([
                'offer_id' => $offer->id,
                'title' => $title['ar'],
                'description' => $faker->realText(150),
                'price' => round($price, 2),
                'discount' => $discountPercent,
                'discount_type' => 'percent',
                'barcode' => $barcode,
                'expires_at' => $endDate,
                'status' => 'active',
                'coupon_code' => $barcode,
            ]);
        }
    }
}