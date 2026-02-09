<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class CartSeeder extends Seeder
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

        // Create carts for 30 users
        $selectedUsers = $users->random(min(30, $users->count()));

        foreach ($selectedUsers as $user) {
            $cart = Cart::firstOrCreate([
                'user_id' => $user->id,
            ]);

            // Add 1-5 items to each cart
            $itemsCount = $faker->numberBetween(1, 5);
            $selectedOffers = $offers->random($itemsCount);

            foreach ($selectedOffers as $offer) {
                $quantity = $faker->numberBetween(1, 3);
                CartItem::create([
                    'cart_id' => $cart->id,
                    'offer_id' => $offer->id,
                    'quantity' => $quantity,
                    'price_at_add' => round($offer->price, 2),
                ]);
            }
        }
    }
}
