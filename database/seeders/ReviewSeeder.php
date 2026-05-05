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
        // Reviews are shown on offer details by offer_id + visible_to_public=true
        $offers = Offer::query()->withCount('coupons')->get();
        $orders = Order::where('payment_status', 'paid')->get();

        if ($users->isEmpty() || $offers->isEmpty()) {
            $this->command?->warn('Skipping ReviewSeeder: no users or offers found.');

            return;
        }

        // Create a few public reviews per offer (prefer offers that have coupons).
        // Important: This seeder ONLY inserts rows (no deletes, no touching images/storage).
        $offersForReviews = $offers
            ->filter(fn (Offer $o) => (int) ($o->coupons_count ?? 0) > 0)
            ->values();
        if ($offersForReviews->isEmpty()) {
            $offersForReviews = $offers;
        }

        foreach ($offersForReviews as $offer) {
            $reviewsToCreate = $faker->numberBetween(2, 6);

            for ($i = 0; $i < $reviewsToCreate; $i++) {
                $user = $users->random();
                $order = $faker->optional(0.7)->randomElement($orders->toArray());

                // Determine moderation action - if moderated, use 'deleted' or 'hidden', otherwise 'none'
                $isModerated = $faker->boolean(10); // keep most seeded offer reviews public
                $moderationAction = 'none';
                if ($isModerated) {
                    $moderationAction = $faker->randomElement(['deleted', 'hidden']);
                }

                Review::create([
                    'user_id' => $user->id,
                    'merchant_id' => $offer->merchant_id,
                    'order_id' => $order ? $order['id'] : null,
                    'offer_id' => $offer->id,
                    'rating' => $faker->numberBetween(1, 5),
                    'notes' => $faker->optional(0.8)->text(200),
                    'notes_ar' => $faker->optional(0.8)->realText(200),
                    'notes_en' => $faker->optional(0.8)->text(200),
                    // Offer reviews should show on offer details:
                    'visible_to_public' => ! $isModerated,
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
}
