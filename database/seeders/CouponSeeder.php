<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\Offer;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $fakerEn = Faker::create('en_US');
        $merchants = Merchant::where('approved', true)->get();
        $offers = Offer::where('status', 'active')->get();

        if ($merchants->isEmpty()) {
            $this->command->warn('No merchants found. Please run MerchantSeeder first.');
            return;
        }
        if ($offers->isEmpty()) {
            $this->command->warn('No offers found. Please run OfferSeeder first.');
            return;
        }

        $statuses = ['active', 'active', 'active', 'used', 'expired', 'inactive'];
        $discountTypes = ['percent', 'amount'];

        $titlePairsAr = [
            'كوبون خصم حصري', 'عرض اليوم الخاص', 'كوبون موسمي',
            'خصم العضوية المميزة', 'كوبون الافتتاح', 'عرض نهاية الأسبوع',
            'خصم مبكر للعملاء', 'عرض العائلة', 'كوبون الرمضان',
            'خصم الجمعة البيضاء',
        ];
        $titlePairsEn = [
            'Exclusive Discount Coupon', 'Today Special Offer', 'Seasonal Coupon',
            'Premium Membership Discount', 'Grand Opening Coupon', 'Weekend Deal',
            'Early Bird Discount', 'Family Offer', 'Ramadan Coupon',
            'Black Friday Discount',
        ];

        foreach ($merchants as $merchant) {
            $merchantOffers = $offers->where('merchant_id', $merchant->id);
            if ($merchantOffers->isEmpty()) continue;

            $couponsToCreate = $faker->numberBetween(5, 15);
            for ($i = 0; $i < $couponsToCreate; $i++) {
                $offer = $merchantOffers->random();
                $status = $faker->randomElement($statuses);
                $startsAt = $faker->dateTimeBetween('-60 days', 'now');
                $expiresAt = $status === 'expired'
                    ? $faker->dateTimeBetween('-30 days', '-1 day')
                    : $faker->dateTimeBetween('now', '+90 days');
                $discountType = $faker->randomElement($discountTypes);
                $discountVal = $discountType === 'percentage'
                    ? $faker->randomElement([5, 10, 15, 20, 25, 30, 40, 50])
                    : $faker->randomFloat(2, 5, 50);
                $barcode = 'CPN-' . strtoupper($faker->unique()->bothify('????##??'));
                $price = (float) ($offer->price ?: $faker->randomFloat(2, 20, 500));
                $usageLimit = $faker->randomElement([0, 1, 5, 10, 20, 50, 100]);
                $timesUsed = $status === 'used'
                    ? ($usageLimit > 0 ? $usageLimit : $faker->numberBetween(1, 20))
                    : $faker->numberBetween(0, max(0, $usageLimit - 1));
                $titleIdx = $faker->numberBetween(0, count($titlePairsAr) - 1);

                $offerImages = $offer->offer_images ?? [];
                $firstImage = is_array($offerImages) && !empty($offerImages)
                    ? (str_starts_with($offerImages[0], 'http') || str_starts_with($offerImages[0], '/') ? $offerImages[0] : asset('storage/' . $offerImages[0]))
                    : '';

                $data = [
                    'offer_id'            => $offer->id,
                    'title'               => $titlePairsAr[$titleIdx],
                    'title_ar'            => $titlePairsAr[$titleIdx],
                    'title_en'            => $titlePairsEn[$titleIdx],
                    'description'         => $faker->realText(150),
                    'description_ar'      => $faker->realText(150),
                    'description_en'      => $fakerEn->text(120),
                    'price'               => $price,
                    'discount'            => $discountVal,
                    'discount_type'       => $discountType,
                    'barcode'             => $barcode,
                    'coupon_code'         => $barcode,
                    'starts_at'           => $startsAt,
                    'expires_at'          => $expiresAt,
                    'status'              => $status,
                    'usage_limit'         => $usageLimit,
                    'times_used'          => $timesUsed,
                    'image'               => $firstImage,
                ];

                try {
                    Coupon::create($data);
                } catch (\Throwable $e) {
                    $this->command->warn("Coupon skip: {$e->getMessage()}");
                }
            }
        }

        $this->command->info('Coupons seeded (' . Coupon::count() . ' total).');
    }
}
