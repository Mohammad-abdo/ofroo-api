<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin role
        Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            [
                'name_ar' => 'مدير',
                'name_en' => 'Admin',
                'description' => 'System administrator with full access',
                'description_ar' => 'مدير النظام مع صلاحيات كاملة',
                'description_en' => 'System administrator with full access',
                'permissions' => ['*'],
            ]
        );

        // Merchant role
        Role::firstOrCreate(
            ['name' => 'merchant', 'guard_name' => 'web'],
            [
                'name_ar' => 'تاجر',
                'name_en' => 'Merchant',
                'description' => 'Merchant account for managing offers and orders',
                'description_ar' => 'حساب تاجر لإدارة العروض والطلبات',
                'description_en' => 'Merchant account for managing offers and orders',
                'permissions' => ['manage_offers', 'view_orders', 'activate_coupons', 'manage_locations', 'view_financial'],
            ]
        );

        // User role
        Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'web'],
            [
                'name_ar' => 'مستخدم',
                'name_en' => 'User',
                'description' => 'Regular user account',
                'description_ar' => 'حساب مستخدم عادي',
                'description_en' => 'Regular user account',
                'permissions' => ['view_offers', 'purchase_coupons', 'view_wallet', 'create_reviews'],
            ]
        );
    }
}
