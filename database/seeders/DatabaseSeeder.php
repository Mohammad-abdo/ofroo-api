<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // 1. Foundation: locations, permissions, roles, users
            GovernorateSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,

            // 2. Business entities
            CategorySeeder::class,
            MallSeeder::class,
            MerchantSeeder::class,

            // 3. Products & coupons
            OfferSeeder::class,
            CouponSeeder::class,

            // 4. Orders & payments
            OrderSeeder::class,
            CartSeeder::class,

            // 5. Financial (wallets, commissions, withdrawals, expenses)
            FinancialSeeder::class,
            WalletSeeder::class,

            // 6. Reviews & loyalty
            ReviewSeeder::class,
            LoyaltySeeder::class,

            // 7. Support tickets
            SupportSeeder::class,

            // 8. Settings & payment gateways
            SettingsSeeder::class,

            // 9. Merchant staff (employees, coupon activation staff)
            MerchantStaffSeeder::class,

            // 10. Ads & banners
            AdSeeder::class,

            // 11. Warnings
            WarningSeeder::class,

            // 12. Admin notifications
            NotificationSeeder::class,

            // 13. Activity logs (last — references all other entities)
            ActivityLogSeeder::class,
        ]);
    }
}
