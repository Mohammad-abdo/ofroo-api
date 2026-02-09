<?php

namespace Database\Seeders;

use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class LoyaltySeeder extends Seeder
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
        $orders = Order::where('payment_status', 'paid')->get();

        $tiers = ['bronze', 'silver', 'gold', 'platinum'];

        foreach ($users as $user) {
            // Create loyalty point account
            $totalPoints = $faker->numberBetween(0, 5000);
            $pointsUsed = $faker->numberBetween(0, min($totalPoints, 3000));
            $pointsExpired = $faker->numberBetween(0, 500);
            $tier = $faker->randomElement($tiers);

            $loyaltyPoint = LoyaltyPoint::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'total_points' => $totalPoints,
                    'tier' => $tier,
                    'points_used' => $pointsUsed,
                    'points_expired' => $pointsExpired,
                ]
            );

            // Create loyalty transactions
            for ($i = 0; $i < 30; $i++) {
                $transactionType = $faker->randomElement(['earned', 'redeemed', 'expired', 'bonus']);
                $points = $faker->numberBetween(10, 500);
                
                // Points should be negative for redeemed and expired
                $pointsValue = in_array($transactionType, ['redeemed', 'expired']) ? -$points : $points;
                
                $order = $faker->optional(0.5) ? $orders->random() : null;

                $expiresAt = null;
                if (($transactionType === 'earned' || $transactionType === 'bonus') && $faker->boolean(70)) {
                    $expiresAt = $faker->dateTimeBetween('now', '+1 year')->format('Y-m-d');
                }

                LoyaltyTransaction::create([
                    'user_id' => $user->id,
                    'order_id' => $order ? $order->id : null,
                    'transaction_type' => $transactionType,
                    'points' => $pointsValue,
                    'description' => $faker->sentence(),
                    'expires_at' => $expiresAt,
                    'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                ]);
            }
        }
    }
}
