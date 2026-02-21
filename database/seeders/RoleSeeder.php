<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Roles with full permission assignment (أدوار كاملة: مدير، تاجر، مستخدم، موظف، مدخل بيانات، محاسب).
     */
    public function run(): void
    {
        $allPermissionIds = Permission::pluck('id')->toArray();

        $roles = [
            [
                'name' => 'admin',
                'guard_name' => 'web',
                'name_ar' => 'مدير النظام',
                'name_en' => 'Admin',
                'description' => 'Full access to all features',
                'description_ar' => 'صلاحيات كاملة على كل الميزات',
                'description_en' => 'Full access to all features',
                'permission_names' => null, // null = all
            ],
            [
                'name' => 'merchant',
                'guard_name' => 'web',
                'name_ar' => 'تاجر',
                'name_en' => 'Merchant',
                'description' => 'Merchant account for managing offers and orders',
                'description_ar' => 'حساب تاجر لإدارة العروض والطلبات',
                'description_en' => 'Merchant account for managing offers and orders',
                'permission_names' => [], // Merchants use their own guard/routes
            ],
            [
                'name' => 'user',
                'guard_name' => 'web',
                'name_ar' => 'مستخدم',
                'name_en' => 'User',
                'description' => 'Regular user account',
                'description_ar' => 'حساب مستخدم عادي',
                'description_en' => 'Regular user account',
                'permission_names' => [],
            ],
            [
                'name' => 'employee',
                'guard_name' => 'web',
                'name_ar' => 'موظف',
                'name_en' => 'Employee',
                'description' => 'Staff with limited admin access',
                'description_ar' => 'موظف بصلاحيات محدودة في لوحة الإدارة',
                'description_en' => 'Staff with limited admin access',
                'permission_names' => [
                    'dashboard.view', 'users.view', 'users.update',
                    'merchants.view', 'merchants.update', 'merchants.verify',
                    'categories.view', 'categories.update', 'malls.view', 'malls.update',
                    'offers.view', 'offers.create', 'offers.update',
                    'coupons.view', 'coupons.create', 'coupons.update',
                    'orders.view', 'orders.update', 'sales.view',
                    'reports.view', 'notifications.view', 'notifications.send',
                    'reviews.view', 'support.view', 'support.assign',
                ],
            ],
            [
                'name' => 'data_entry',
                'guard_name' => 'web',
                'name_ar' => 'مدخل بيانات',
                'name_en' => 'Data Entry',
                'description' => 'Data entry staff - add and edit content',
                'description_ar' => 'مدخل بيانات - إضافة وتعديل المحتوى',
                'description_en' => 'Data entry staff - add and edit content',
                'permission_names' => [
                    'dashboard.view', 'merchants.view', 'merchants.create', 'merchants.update',
                    'categories.view', 'categories.create', 'categories.update',
                    'malls.view', 'malls.create', 'malls.update',
                    'offers.view', 'offers.create', 'offers.update',
                    'coupons.view', 'coupons.create', 'coupons.update',
                    'orders.view', 'orders.create', 'orders.update', 'sales.view',
                    'ads.view', 'ads.create', 'ads.update',
                    'banners.view', 'banners.create', 'banners.update',
                    'reviews.view',
                ],
            ],
            [
                'name' => 'accountant',
                'guard_name' => 'web',
                'name_ar' => 'محاسب',
                'name_en' => 'Accountant',
                'description' => 'Access to financial data and reports',
                'description_ar' => 'الوصول للبيانات المالية والتقارير',
                'description_en' => 'Access to financial data and reports',
                'permission_names' => [
                    'dashboard.view', 'orders.view', 'sales.view', 'sales.export',
                    'reports.view', 'financial.view',
                    'withdrawals.view', 'withdrawals.approve',
                    'invoices.view', 'invoices.generate',
                    'merchants.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissionNames = $roleData['permission_names'];
            unset($roleData['permission_names']);

            $role = Role::firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => $roleData['guard_name']],
                $roleData
            );

            if ($permissionNames === null) {
                $role->permissions()->sync($allPermissionIds);
            } elseif (!empty($permissionNames)) {
                $ids = Permission::whereIn('name', $permissionNames)->pluck('id')->toArray();
                $role->permissions()->sync($ids);
            } else {
                $role->permissions()->sync([]);
            }
        }
    }
}
