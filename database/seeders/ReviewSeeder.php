<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
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
        $offers = Offer::all();
        $orders = Order::where('payment_status', 'paid')->get();

        // Create 150 reviews
        for ($i = 0; $i < 150; $i++) {
            $user = $users->random();
            $offer = $offers->random();
            $order = $faker->optional(0.7)->randomElement($orders->toArray());

            // Determine moderation action - if moderated, use 'deleted' or 'hidden', otherwise 'none'
            $isModerated = $faker->boolean(30); // 30% chance of being moderated
            $moderationAction = 'none';
            if ($isModerated) {
                $moderationAction = $faker->randomElement(['deleted', 'hidden']);
            }

            Review::create([
                'user_id' => $user->id,
                'merchant_id' => $offer->merchant_id,
                'order_id' => $order ? $order['id'] : null,
                'rating' => $faker->numberBetween(1, 5),
                'notes' => $faker->optional(0.8)->text(200),
                'notes_ar' => $faker->optional(0.8)->realText(200),
                'notes_en' => $faker->optional(0.8)->text(200),
                'visible_to_public' => $faker->boolean(80),
                'moderated_by_admin_id' => $isModerated ? $faker->numberBetween(1, 10) : null,
                'moderation_action' => $moderationAction,
                'moderation_reason' => $isModerated ? $faker->optional(0.7)->sentence() : null,
                // db:seed runs Model::unguarded(): TIMESTAMP columns reject some Faker datetimes (TZ / invalid wall times).
                'moderation_at' => $isModerated
                    ? Carbon::createFromInterface($faker->dateTimeBetween('-3 months', 'now'))->utc()
                    : null,
            ]);
        }
    }
}
