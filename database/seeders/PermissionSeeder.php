<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Default permissions for OFROO admin panel.
     */
    public function run(): void
    {
        $permissions = [
            // Users
            ['name' => 'users.view', 'group' => 'users', 'name_ar' => 'عرض المستخدمين', 'name_en' => 'View users', 'description' => 'View users list', 'description_ar' => 'عرض قائمة المستخدمين', 'description_en' => 'View users list'],
            ['name' => 'users.create', 'group' => 'users', 'name_ar' => 'إضافة مستخدم', 'name_en' => 'Create user', 'description' => 'Create new user', 'description_ar' => 'إنشاء مستخدم جديد', 'description_en' => 'Create new user'],
            ['name' => 'users.update', 'group' => 'users', 'name_ar' => 'تعديل مستخدم', 'name_en' => 'Update user', 'description' => 'Update user data', 'description_ar' => 'تعديل بيانات المستخدم', 'description_en' => 'Update user data'],
            ['name' => 'users.delete', 'group' => 'users', 'name_ar' => 'حذف مستخدم', 'name_en' => 'Delete user', 'description' => 'Delete user', 'description_ar' => 'حذف المستخدم', 'description_en' => 'Delete user'],

            // Merchants
            ['name' => 'merchants.view', 'group' => 'merchants', 'name_ar' => 'عرض التجار', 'name_en' => 'View merchants', 'description' => 'View merchants list', 'description_ar' => 'عرض قائمة التجار', 'description_en' => 'View merchants list'],
            ['name' => 'merchants.create', 'group' => 'merchants', 'name_ar' => 'إضافة تاجر', 'name_en' => 'Create merchant', 'description' => 'Create new merchant', 'description_ar' => 'إنشاء تاجر جديد', 'description_en' => 'Create new merchant'],
            ['name' => 'merchants.update', 'group' => 'merchants', 'name_ar' => 'تعديل تاجر', 'name_en' => 'Update merchant', 'description' => 'Update merchant data', 'description_ar' => 'تعديل بيانات التاجر', 'description_en' => 'Update merchant data'],
            ['name' => 'merchants.delete', 'group' => 'merchants', 'name_ar' => 'حذف تاجر', 'name_en' => 'Delete merchant', 'description' => 'Delete merchant', 'description_ar' => 'حذف التاجر', 'description_en' => 'Delete merchant'],
            ['name' => 'merchants.verify', 'group' => 'merchants', 'name_ar' => 'التحقق من التاجر', 'name_en' => 'Verify merchant', 'description' => 'Approve or reject merchant verification', 'description_ar' => 'الموافقة أو رفض التحقق من التاجر', 'description_en' => 'Approve or reject merchant verification'],

            // Categories
            ['name' => 'categories.view', 'group' => 'categories', 'name_ar' => 'عرض التصنيفات', 'name_en' => 'View categories', 'description' => 'View categories list', 'description_ar' => 'عرض قائمة التصنيفات', 'description_en' => 'View categories list'],
            ['name' => 'categories.create', 'group' => 'categories', 'name_ar' => 'إضافة تصنيف', 'name_en' => 'Create category', 'description' => 'Create new category', 'description_ar' => 'إنشاء تصنيف جديد', 'description_en' => 'Create new category'],
            ['name' => 'categories.update', 'group' => 'categories', 'name_ar' => 'تعديل تصنيف', 'name_en' => 'Update category', 'description' => 'Update category data', 'description_ar' => 'تعديل بيانات التصنيف', 'description_en' => 'Update category data'],
            ['name' => 'categories.delete', 'group' => 'categories', 'name_ar' => 'حذف تصنيف', 'name_en' => 'Delete category', 'description' => 'Delete category', 'description_ar' => 'حذف التصنيف', 'description_en' => 'Delete category'],

            // Offers
            ['name' => 'offers.view', 'group' => 'offers', 'name_ar' => 'عرض العروض', 'name_en' => 'View offers', 'description' => 'View offers list', 'description_ar' => 'عرض قائمة العروض', 'description_en' => 'View offers list'],
            ['name' => 'offers.create', 'group' => 'offers', 'name_ar' => 'إضافة عرض', 'name_en' => 'Create offer', 'description' => 'Create new offer', 'description_ar' => 'إنشاء عرض جديد', 'description_en' => 'Create new offer'],
            ['name' => 'offers.update', 'group' => 'offers', 'name_ar' => 'تعديل عرض', 'name_en' => 'Update offer', 'description' => 'Update offer data', 'description_ar' => 'تعديل بيانات العرض', 'description_en' => 'Update offer data'],
            ['name' => 'offers.delete', 'group' => 'offers', 'name_ar' => 'حذف عرض', 'name_en' => 'Delete offer', 'description' => 'Delete offer', 'description_ar' => 'حذف العرض', 'description_en' => 'Delete offer'],

            // Coupons
            ['name' => 'coupons.view', 'group' => 'coupons', 'name_ar' => 'عرض الكوبونات', 'name_en' => 'View coupons', 'description' => 'View coupons list', 'description_ar' => 'عرض قائمة الكوبونات', 'description_en' => 'View coupons list'],
            ['name' => 'coupons.create', 'group' => 'coupons', 'name_ar' => 'إضافة كوبون', 'name_en' => 'Create coupon', 'description' => 'Create new coupon', 'description_ar' => 'إنشاء كوبون جديد', 'description_en' => 'Create new coupon'],
            ['name' => 'coupons.update', 'group' => 'coupons', 'name_ar' => 'تعديل كوبون', 'name_en' => 'Update coupon', 'description' => 'Update coupon data', 'description_ar' => 'تعديل بيانات الكوبون', 'description_en' => 'Update coupon data'],
            ['name' => 'coupons.delete', 'group' => 'coupons', 'name_ar' => 'حذف كوبون', 'name_en' => 'Delete coupon', 'description' => 'Delete coupon', 'description_ar' => 'حذف الكوبون', 'description_en' => 'Delete coupon'],

            // Orders
            ['name' => 'orders.view', 'group' => 'orders', 'name_ar' => 'عرض الطلبات', 'name_en' => 'View orders', 'description' => 'View orders list', 'description_ar' => 'عرض قائمة الطلبات', 'description_en' => 'View orders list'],
            ['name' => 'orders.update', 'group' => 'orders', 'name_ar' => 'تعديل طلب', 'name_en' => 'Update order', 'description' => 'Update order status', 'description_ar' => 'تعديل حالة الطلب', 'description_en' => 'Update order status'],

            // Reports
            ['name' => 'reports.view', 'group' => 'reports', 'name_ar' => 'عرض التقارير', 'name_en' => 'View reports', 'description' => 'View and export reports', 'description_ar' => 'عرض وتصدير التقارير', 'description_en' => 'View and export reports'],

            // Withdrawals
            ['name' => 'withdrawals.view', 'group' => 'withdrawals', 'name_ar' => 'عرض طلبات السحب', 'name_en' => 'View withdrawals', 'description' => 'View withdrawal requests', 'description_ar' => 'عرض طلبات السحب', 'description_en' => 'View withdrawal requests'],
            ['name' => 'withdrawals.approve', 'group' => 'withdrawals', 'name_ar' => 'الموافقة على السحب', 'name_en' => 'Approve withdrawal', 'description' => 'Approve or reject withdrawal', 'description_ar' => 'الموافقة أو رفض طلب السحب', 'description_en' => 'Approve or reject withdrawal'],

            // Support
            ['name' => 'support.view', 'group' => 'support', 'name_ar' => 'عرض التذاكر', 'name_en' => 'View tickets', 'description' => 'View support tickets', 'description_ar' => 'عرض تذاكر الدعم', 'description_en' => 'View support tickets'],
            ['name' => 'support.assign', 'group' => 'support', 'name_ar' => 'تعيين تذكرة', 'name_en' => 'Assign ticket', 'description' => 'Assign ticket to agent', 'description_ar' => 'تعيين التذكرة لموظف', 'description_en' => 'Assign ticket to agent'],
            ['name' => 'support.resolve', 'group' => 'support', 'name_ar' => 'حل تذكرة', 'name_en' => 'Resolve ticket', 'description' => 'Resolve support ticket', 'description_ar' => 'حل تذكرة الدعم', 'description_en' => 'Resolve support ticket'],

            // Settings
            ['name' => 'settings.view', 'group' => 'settings', 'name_ar' => 'عرض الإعدادات', 'name_en' => 'View settings', 'description' => 'View app settings', 'description_ar' => 'عرض إعدادات التطبيق', 'description_en' => 'View app settings'],
            ['name' => 'settings.update', 'group' => 'settings', 'name_ar' => 'تعديل الإعدادات', 'name_en' => 'Update settings', 'description' => 'Update app settings', 'description_ar' => 'تعديل إعدادات التطبيق', 'description_en' => 'Update app settings'],

            // Roles & Permissions
            ['name' => 'roles.view', 'group' => 'roles', 'name_ar' => 'عرض الأدوار', 'name_en' => 'View roles', 'description' => 'View roles and permissions', 'description_ar' => 'عرض الأدوار والصلاحيات', 'description_en' => 'View roles and permissions'],
            ['name' => 'roles.create', 'group' => 'roles', 'name_ar' => 'إضافة دور', 'name_en' => 'Create role', 'description' => 'Create new role', 'description_ar' => 'إنشاء دور جديد', 'description_en' => 'Create new role'],
            ['name' => 'roles.update', 'group' => 'roles', 'name_ar' => 'تعديل دور', 'name_en' => 'Update role', 'description' => 'Update role and assign permissions', 'description_ar' => 'تعديل الدور وتعيين الصلاحيات', 'description_en' => 'Update role and assign permissions'],
            ['name' => 'roles.delete', 'group' => 'roles', 'name_ar' => 'حذف دور', 'name_en' => 'Delete role', 'description' => 'Delete role', 'description_ar' => 'حذف الدور', 'description_en' => 'Delete role'],
            ['name' => 'permissions.manage', 'group' => 'roles', 'name_ar' => 'إدارة الصلاحيات', 'name_en' => 'Manage permissions', 'description' => 'Create, update, delete permissions', 'description_ar' => 'إنشاء وتعديل وحذف الصلاحيات', 'description_en' => 'Create, update, delete permissions'],

            // Warnings
            ['name' => 'warnings.view', 'group' => 'warnings', 'name_ar' => 'عرض التحذيرات', 'name_en' => 'View warnings', 'description' => 'View merchant warnings', 'description_ar' => 'عرض تحذيرات التجار', 'description_en' => 'View merchant warnings'],
            ['name' => 'warnings.manage', 'group' => 'warnings', 'name_ar' => 'إدارة التحذيرات', 'name_en' => 'Manage warnings', 'description' => 'Add or remove warnings', 'description_ar' => 'إضافة أو إزالة التحذيرات', 'description_en' => 'Add or remove warnings'],
        ];

        foreach ($permissions as $item) {
            Permission::firstOrCreate(
                ['name' => $item['name']],
                $item
            );
        }
    }
}
