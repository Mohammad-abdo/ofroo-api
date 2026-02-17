<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Offer;
use App\Models\User;
use App\Models\Role;
use App\Models\Governorate;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CartForPhoneUserSeeder extends Seeder
{
    public const PHONE = '+201012345678';

    /**
     * Seed cart data for user with phone +201012345678.
     * Creates the user if not found, then creates a cart with 2–5 items from active offers.
     */
    public function run(): void
    {
        $userRole = Role::where('name', 'user')->first();
        if (! $userRole) {
            $this->command->warn('Role "user" not found. Run RoleSeeder first.');
            return;
        }

        $user = User::where('phone', self::PHONE)->first();

        if (! $user) {
            $gov = Governorate::with('cities')->first();
            $city = $gov?->cities?->first();
            $user = User::create([
                'name' => 'Cart Test User',
                'email' => 'cartuser@example.com',
                'phone' => self::PHONE,
                'password' => Hash::make('password'),
                'language' => 'ar',
                'role_id' => $userRole->id,
                'email_verified_at' => now(),
                'country' => 'مصر',
                'gender' => 'male',
                'city_id' => $city?->id,
                'governorate_id' => $gov?->id,
            ]);
            $this->command->info('Created user with phone ' . self::PHONE . ' (email: cartuser@example.com, password: password).');
        }

        $offers = Offer::where('status', 'active')->get();
        if ($offers->isEmpty()) {
            $this->command->warn('No active offers found. Run OfferSeeder first.');
            return;
        }

        $cart = Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['user_id' => $user->id]
        );

        // Remove existing items so we seed a fresh set
        $cart->items()->delete();

        $count = min(5, $offers->count());
        $selectedOffers = $offers->random($count);

        foreach ($selectedOffers as $offer) {
            CartItem::create([
                'cart_id' => $cart->id,
                'offer_id' => $offer->id,
                'quantity' => rand(1, 3),
                'price_at_add' => round((float) $offer->price, 2),
            ]);
        }

        $this->command->info('Seeded cart for user ' . self::PHONE . ' with ' . $count . ' item(s). Cart ID: ' . $cart->id);
    }
}
