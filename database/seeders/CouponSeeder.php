<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\Offer;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Uses refactored coupon schema: offer_id, title, description, price, discount, discount_type, barcode, expires_at, status.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
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

        $statuses = ['active', 'used', 'expired'];
        $discountTypes = ['percent', 'amount'];

        foreach ($merchants as $merchant) {
            $merchantOffers = $offers->where('merchant_id', $merchant->id);
            if ($merchantOffers->isEmpty()) {
                continue;
            }

            $couponsToCreate = $faker->numberBetween(5, 15);
            for ($i = 0; $i < $couponsToCreate; $i++) {
                $offer = $merchantOffers->random();
                $status = $faker->randomElement($statuses);
                $expiresAt = $status === 'expired'
                    ? $faker->dateTimeBetween('-30 days', '-1 day')
                    : $faker->dateTimeBetween('now', '+90 days');
                $discountType = $faker->randomElement($discountTypes);
                $discountVal = $discountType === 'percent'
                    ? $faker->randomElement([10, 15, 20, 25, 30, 40, 50])
                    : $faker->randomFloat(2, 5, 50);
                $barcode = 'CUP-' . strtoupper($faker->unique()->bothify('????##??'));

                $data = [
                    'offer_id' => $offer->id,
                    'title' => $offer->title ?? 'كوبون خصم',
                    'description' => $faker->optional(0.6)->realText(150),
                    'price' => (float) $offer->price,
                    'discount' => $discountVal,
                    'discount_type' => $discountType,
                    'barcode' => $barcode,
                    'expires_at' => $expiresAt,
                    'status' => $status,
                ];

                if (\Schema::hasColumn('coupons', 'coupon_code')) {
                    $data['coupon_code'] = $barcode;
                }

                Coupon::create($data);
            }
        }

        $this->command->info('Coupons seeded successfully.');
    }
}
