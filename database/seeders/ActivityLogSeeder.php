<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $users = User::all();

        $activities = [
            'user.login',
            'user.logout',
            'offer.created',
            'offer.updated',
            'offer.deleted',
            'order.created',
            'order.completed',
            'coupon.activated',
            'payment.completed',
            'merchant.approved',
        ];

        // Create 500 activity logs
        for ($i = 0; $i < 500; $i++) {
            $user = $faker->optional(0.8) ? $users->random() : null;
            ActivityLog::create([
                'user_id' => $user ? $user->id : null,
                'actor_role' => $user && $user->role ? $user->role->name : ($user ? 'user' : null),
                'action' => $faker->randomElement($activities),
                'model_type' => $faker->optional(0.5)->randomElement(['App\\Models\\Order', 'App\\Models\\Offer', 'App\\Models\\User']),
                'model_id' => $faker->optional(0.5)->numberBetween(1, 1000),
                'target_type' => $faker->optional(0.3)->randomElement(['App\\Models\\Merchant', 'App\\Models\\User']),
                'target_id' => $faker->optional(0.3)->numberBetween(1, 100),
                'description' => $faker->sentence(),
                'ip_address' => $faker->ipv4(),
                'user_agent' => $faker->userAgent(),
                'old_values' => $faker->optional(0.2)->randomElement([['status' => 'pending'], ['status' => 'active']]),
                'new_values' => $faker->optional(0.2)->randomElement([['status' => 'active'], ['status' => 'completed']]),
                'metadata' => [
                    'key' => $faker->word(),
                    'value' => $faker->word(),
                ],
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
            ]);
        }
    }
}

