<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class MerchantStaffSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $merchants = Merchant::where('approved', true)->get();
        $userRole = Role::where('name', 'user')->first();

        if ($merchants->isEmpty()) {
            $this->command->warn('No merchants. Run MerchantSeeder first.');
            return;
        }

        $roles = [
            ['role' => 'manager', 'ar' => 'مدير', 'en' => 'Manager', 'perms' => [
                'can_create_offers' => true, 'can_edit_offers' => true,
                'can_activate_coupons' => true, 'can_view_reports' => true, 'can_manage_staff' => true,
            ]],
            ['role' => 'cashier', 'ar' => 'كاشير', 'en' => 'Cashier', 'perms' => [
                'can_create_offers' => false, 'can_edit_offers' => false,
                'can_activate_coupons' => true, 'can_view_reports' => false, 'can_manage_staff' => false,
            ]],
            ['role' => 'scanner', 'ar' => 'ماسح باركود', 'en' => 'Barcode Scanner', 'perms' => [
                'can_create_offers' => false, 'can_edit_offers' => false,
                'can_activate_coupons' => true, 'can_view_reports' => false, 'can_manage_staff' => false,
            ]],
            ['role' => 'coupon_activation', 'ar' => 'موظف تفعيل كوبونات', 'en' => 'Coupon Activation Staff', 'perms' => [
                'can_create_offers' => false, 'can_edit_offers' => false,
                'can_activate_coupons' => true, 'can_view_reports' => false, 'can_manage_staff' => false,
            ]],
            ['role' => 'staff', 'ar' => 'موظف', 'en' => 'Staff', 'perms' => [
                'can_create_offers' => false, 'can_edit_offers' => true,
                'can_activate_coupons' => true, 'can_view_reports' => true, 'can_manage_staff' => false,
            ]],
        ];

        $staffIdx = 1;
        foreach ($merchants as $merchant) {
            $staffCount = $faker->numberBetween(2, 5);
            for ($i = 0; $i < $staffCount; $i++) {
                $roleData = $faker->randomElement($roles);
                $email = "staff{$staffIdx}@ofroo.com";
                $phone = '+20' . $faker->unique()->numerify('##########');

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name'              => $faker->name(),
                        'phone'             => $phone,
                        'password'          => Hash::make('password'),
                        'language'          => 'ar',
                        'role_id'           => $userRole ? $userRole->id : null,
                        'email_verified_at' => now(),
                        'country'           => 'مصر',
                    ]
                );

                try {
                    MerchantStaff::firstOrCreate(
                        ['merchant_id' => $merchant->id, 'user_id' => $user->id],
                        [
                            'role'                  => $roleData['role'],
                            'role_ar'               => $roleData['ar'],
                            'role_en'               => $roleData['en'],
                            'permissions'           => ['coupon_activation' => $roleData['perms']['can_activate_coupons']],
                            'can_create_offers'     => $roleData['perms']['can_create_offers'],
                            'can_edit_offers'       => $roleData['perms']['can_edit_offers'],
                            'can_activate_coupons'  => $roleData['perms']['can_activate_coupons'],
                            'can_view_reports'      => $roleData['perms']['can_view_reports'],
                            'can_manage_staff'      => $roleData['perms']['can_manage_staff'],
                            'is_active'             => $faker->boolean(85),
                        ]
                    );
                } catch (\Throwable) {}

                $staffIdx++;
            }
        }

        $this->command->info('Merchant staff seeded (' . MerchantStaff::count() . ' total).');
    }
}
