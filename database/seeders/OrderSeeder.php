<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $users = User::whereHas('role', function ($query) {
            $query->where('name', 'user');
        })->get();
        $offers = Offer::where('status', 'active')->get();

        // Create 200 orders
        for ($i = 0; $i < 200; $i++) {
            $user = $users->random();
            $offer = $offers->random();
            $quantity = $faker->numberBetween(1, 5);
            $unitPrice = $offer->price;
            $totalPrice = $unitPrice * $quantity;
            $totalAmount = $totalPrice; // No tax in the current structure

            $paymentStatus = $faker->randomElement(['pending', 'paid', 'failed']);
            $paymentMethod = $faker->randomElement(['cash', 'card', 'none']);

            $order = Order::create([
                'user_id' => $user->id,
                'merchant_id' => $offer->merchant_id,
                'total_amount' => round($totalAmount, 2),
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'notes' => $faker->optional(0.3)->sentence(),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'offer_id' => $offer->id,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'total_price' => round($totalPrice, 2),
            ]);

            // Create payment if order is paid
            if ($paymentStatus === 'paid') {
                Payment::create([
                    'order_id' => $order->id,
                    'transaction_id' => 'TXN' . $faker->unique()->numerify('##########'),
                    'amount' => round($totalAmount, 2),
                    'gateway' => $paymentMethod === 'card' ? $faker->randomElement(['knet', 'visa', 'mastercard']) : null,
                    'status' => 'success',
                    'response' => [
                        'transaction_id' => 'TXN' . $faker->unique()->numerify('##########'),
                        'status' => 'success',
                    ],
                ]);

                // Create coupons for the order
                // Link to the offer's coupon template if it exists, otherwise create new ones
                $offerCoupon = $offer->coupon; // The template coupon created by merchant
                
                for ($j = 0; $j < $quantity; $j++) {
                    $couponStatus = $faker->randomElement(['reserved', 'paid', 'activated', 'used', 'expired']);
                    
                    Coupon::create([
                        'order_id' => $order->id,
                        'offer_id' => $offer->id,
                        'category_id' => $offer->category_id, // Required field
                        'user_id' => $user->id,
                        'coupon_code' => 'CPN' . strtoupper($faker->unique()->bothify('########')),
                        'barcode_value' => $faker->unique()->numerify('##########'),
                        'usage_limit' => $offerCoupon ? $offerCoupon->usage_limit : 1, // Use offer's coupon template limit
                        'times_used' => $couponStatus === 'used' ? 1 : 0,
                        'status' => $couponStatus,
                        'reserved_at' => $order->created_at,
                        'activated_at' => in_array($couponStatus, ['activated', 'used']) ? $faker->dateTimeBetween($order->created_at, 'now') : null,
                        'used_at' => $couponStatus === 'used' ? $faker->dateTimeBetween($order->created_at, 'now') : null,
                        'created_by' => $offer->merchant_id,
                        'created_by_type' => 'system', // Created by system when order is paid
                    ]);
                }
            }
        }
    }
}
