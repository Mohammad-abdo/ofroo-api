<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Locations first (users/merchants need city_id, governorate_id)
            GovernorateSeeder::class,
            // Core seeders
            RoleSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            MerchantSeeder::class,
            OfferSeeder::class,
            CouponSeeder::class,

            // Order and cart seeders
            OrderSeeder::class,
            CartSeeder::class,

            // Financial seeders
            FinancialSeeder::class,

            // Review and loyalty seeders
            ReviewSeeder::class,
            LoyaltySeeder::class,

            // Support seeders
            SupportSeeder::class,

            // Settings seeders
            SettingsSeeder::class,

            // Wallet seeders
            WalletSeeder::class,

            // Activity logs
            ActivityLogSeeder::class,
        ]);
    }
}