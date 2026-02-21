<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Full permissions for OFROO admin panel (أدوار وصلاحيات كاملة).
     */
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'group' => 'dashboard', 'group_ar' => 'لوحة التحكم', 'group_en' => 'Dashboard', 'name_ar' => 'عرض لوحة التحكم', 'name_en' => 'View dashboard', 'description' => 'View main dashboard', 'description_ar' => 'عرض لوحة التحكم الرئيسية', 'description_en' => 'View main dashboard'],

            // Users
            ['name' => 'users.view', 'group' => 'users', 'group_ar' => 'المستخدمين', 'group_en' => 'Users', 'name_ar' => 'عرض المستخدمين', 'name_en' => 'View users', 'description' => 'View users list', 'description_ar' => 'عرض قائمة المستخدمين', 'description_en' => 'View users list'],
            ['name' => 'users.create', 'group' => 'users', 'group_ar' => 'المستخدمين', 'group_en' => 'Users', 'name_ar' => 'إضافة مستخدم', 'name_en' => 'Create user', 'description' => 'Create new user', 'description_ar' => 'إنشاء مستخدم جديد', 'description_en' => 'Create new user'],
            ['name' => 'users.update', 'group' => 'users', 'group_ar' => 'المستخدمين', 'group_en' => 'Users', 'name_ar' => 'تعديل مستخدم', 'name_en' => 'Update user', 'description' => 'Update user data', 'description_ar' => 'تعديل بيانات المستخدم', 'description_en' => 'Update user data'],
            ['name' => 'users.delete', 'group' => 'users', 'group_ar' => 'المستخدمين', 'group_en' => 'Users', 'name_ar' => 'حذف مستخدم', 'name_en' => 'Delete user', 'description' => 'Delete user', 'description_ar' => 'حذف المستخدم', 'description_en' => 'Delete user'],
            ['name' => 'users.export', 'group' => 'users', 'group_ar' => 'المستخدمين', 'group_en' => 'Users', 'name_ar' => 'تصدير المستخدمين', 'name_en' => 'Export users', 'description' => 'Export users list', 'description_ar' => 'تصدير قائمة المستخدمين', 'description_en' => 'Export users list'],

            // Merchants
            ['name' => 'merchants.view', 'group' => 'merchants', 'group_ar' => 'التجار', 'group_en' => 'Merchants', 'name_ar' => 'عرض التجار', 'name_en' => 'View merchants', 'description' => 'View merchants list', 'description_ar' => 'عرض قائمة التجار', 'description_en' => 'View merchants list'],
            ['name' => 'merchants.create', 'group' => 'merchants', 'group_ar' => 'التجار', 'group_en' => 'Merchants', 'name_ar' => 'إضافة تاجر', 'name_en' => 'Create merchant', 'description' => 'Create new merchant', 'description_ar' => 'إنشاء تاجر جديد', 'description_en' => 'Create new merchant'],
            ['name' => 'merchants.update', 'group' => 'merchants', 'group_ar' => 'التجار', 'group_en' => 'Merchants', 'name_ar' => 'تعديل تاجر', 'name_en' => 'Update merchant', 'description' => 'Update merchant data', 'description_ar' => 'تعديل بيانات التاجر', 'description_en' => 'Update merchant data'],
            ['name' => 'merchants.delete', 'group' => 'merchants', 'group_ar' => 'التجار', 'group_en' => 'Merchants', 'name_ar' => 'حذف تاجر', 'name_en' => 'Delete merchant', 'description' => 'Delete merchant', 'description_ar' => 'حذف التاجر', 'description_en' => 'Delete merchant'],
            ['name' => 'merchants.verify', 'group' => 'merchants', 'group_ar' => 'التجار', 'group_en' => 'Merchants', 'name_ar' => 'التحقق من التاجر', 'name_en' => 'Verify merchant', 'description' => 'Approve or reject merchant verification', 'description_ar' => 'الموافقة أو رفض التحقق من التاجر', 'description_en' => 'Approve or reject merchant verification'],

            // Categories
            ['name' => 'categories.view', 'group' => 'categories', 'group_ar' => 'التصنيفات', 'group_en' => 'Categories', 'name_ar' => 'عرض التصنيفات', 'name_en' => 'View categories', 'description' => 'View categories list', 'description_ar' => 'عرض قائمة التصنيفات', 'description_en' => 'View categories list'],
            ['name' => 'categories.create', 'group' => 'categories', 'group_ar' => 'التصنيفات', 'group_en' => 'Categories', 'name_ar' => 'إضافة تصنيف', 'name_en' => 'Create category', 'description' => 'Create new category', 'description_ar' => 'إنشاء تصنيف جديد', 'description_en' => 'Create new category'],
            ['name' => 'categories.update', 'group' => 'categories', 'group_ar' => 'التصنيفات', 'group_en' => 'Categories', 'name_ar' => 'تعديل تصنيف', 'name_en' => 'Update category', 'description' => 'Update category data', 'description_ar' => 'تعديل بيانات التصنيف', 'description_en' => 'Update category data'],
            ['name' => 'categories.delete', 'group' => 'categories', 'group_ar' => 'التصنيفات', 'group_en' => 'Categories', 'name_ar' => 'حذف تصنيف', 'name_en' => 'Delete category', 'description' => 'Delete category', 'description_ar' => 'حذف التصنيف', 'description_en' => 'Delete category'],

            // Malls
            ['name' => 'malls.view', 'group' => 'malls', 'group_ar' => 'المولات', 'group_en' => 'Malls', 'name_ar' => 'عرض المولات', 'name_en' => 'View malls', 'description' => 'View malls list', 'description_ar' => 'عرض قائمة المولات', 'description_en' => 'View malls list'],
            ['name' => 'malls.create', 'group' => 'malls', 'group_ar' => 'المولات', 'group_en' => 'Malls', 'name_ar' => 'إضافة مول', 'name_en' => 'Create mall', 'description' => 'Create new mall', 'description_ar' => 'إنشاء مول جديد', 'description_en' => 'Create new mall'],
            ['name' => 'malls.update', 'group' => 'malls', 'group_ar' => 'المولات', 'group_en' => 'Malls', 'name_ar' => 'تعديل مول', 'name_en' => 'Update mall', 'description' => 'Update mall data', 'description_ar' => 'تعديل بيانات المول', 'description_en' => 'Update mall data'],
            ['name' => 'malls.delete', 'group' => 'malls', 'group_ar' => 'المولات', 'group_en' => 'Malls', 'name_ar' => 'حذف مول', 'name_en' => 'Delete mall', 'description' => 'Delete mall', 'description_ar' => 'حذف المول', 'description_en' => 'Delete mall'],

            // Offers
            ['name' => 'offers.view', 'group' => 'offers', 'group_ar' => 'العروض', 'group_en' => 'Offers', 'name_ar' => 'عرض العروض', 'name_en' => 'View offers', 'description' => 'View offers list', 'description_ar' => 'عرض قائمة العروض', 'description_en' => 'View offers list'],
            ['name' => 'offers.create', 'group' => 'offers', 'group_ar' => 'العروض', 'group_en' => 'Offers', 'name_ar' => 'إضافة عرض', 'name_en' => 'Create offer', 'description' => 'Create new offer', 'description_ar' => 'إنشاء عرض جديد', 'description_en' => 'Create new offer'],
            ['name' => 'offers.update', 'group' => 'offers', 'group_ar' => 'العروض', 'group_en' => 'Offers', 'name_ar' => 'تعديل عرض', 'name_en' => 'Update offer', 'description' => 'Update offer data', 'description_ar' => 'تعديل بيانات العرض', 'description_en' => 'Update offer data'],
            ['name' => 'offers.delete', 'group' => 'offers', 'group_ar' => 'العروض', 'group_en' => 'Offers', 'name_ar' => 'حذف عرض', 'name_en' => 'Delete offer', 'description' => 'Delete offer', 'description_ar' => 'حذف العرض', 'description_en' => 'Delete offer'],

            // Coupons
            ['name' => 'coupons.view', 'group' => 'coupons', 'group_ar' => 'الكوبونات', 'group_en' => 'Coupons', 'name_ar' => 'عرض الكوبونات', 'name_en' => 'View coupons', 'description' => 'View coupons list', 'description_ar' => 'عرض قائمة الكوبونات', 'description_en' => 'View coupons list'],
            ['name' => 'coupons.create', 'group' => 'coupons', 'group_ar' => 'الكوبونات', 'group_en' => 'Coupons', 'name_ar' => 'إضافة كوبون', 'name_en' => 'Create coupon', 'description' => 'Create new coupon', 'description_ar' => 'إنشاء كوبون جديد', 'description_en' => 'Create new coupon'],
            ['name' => 'coupons.update', 'group' => 'coupons', 'group_ar' => 'الكوبونات', 'group_en' => 'Coupons', 'name_ar' => 'تعديل كوبون', 'name_en' => 'Update coupon', 'description' => 'Update coupon data', 'description_ar' => 'تعديل بيانات الكوبون', 'description_en' => 'Update coupon data'],
            ['name' => 'coupons.delete', 'group' => 'coupons', 'group_ar' => 'الكوبونات', 'group_en' => 'Coupons', 'name_ar' => 'حذف كوبون', 'name_en' => 'Delete coupon', 'description' => 'Delete coupon', 'description_ar' => 'حذف الكوبون', 'description_en' => 'Delete coupon'],

            // Orders & Sales (الطلبات / المبيعات)
            ['name' => 'orders.view', 'group' => 'orders', 'group_ar' => 'الطلبات', 'group_en' => 'Orders', 'name_ar' => 'عرض الطلبات', 'name_en' => 'View orders', 'description' => 'View orders list', 'description_ar' => 'عرض قائمة الطلبات', 'description_en' => 'View orders list'],
            ['name' => 'orders.create', 'group' => 'orders', 'group_ar' => 'الطلبات', 'group_en' => 'Orders', 'name_ar' => 'إنشاء طلب', 'name_en' => 'Create order', 'description' => 'Create order', 'description_ar' => 'إنشاء طلب', 'description_en' => 'Create order'],
            ['name' => 'orders.update', 'group' => 'orders', 'group_ar' => 'الطلبات', 'group_en' => 'Orders', 'name_ar' => 'تعديل طلب', 'name_en' => 'Update order', 'description' => 'Update order status', 'description_ar' => 'تعديل حالة الطلب', 'description_en' => 'Update order status'],
            ['name' => 'orders.delete', 'group' => 'orders', 'group_ar' => 'الطلبات', 'group_en' => 'Orders', 'name_ar' => 'حذف طلب', 'name_en' => 'Delete order', 'description' => 'Delete order', 'description_ar' => 'حذف الطلب', 'description_en' => 'Delete order'],
            ['name' => 'sales.view', 'group' => 'sales', 'group_ar' => 'المبيعات', 'group_en' => 'Sales', 'name_ar' => 'متابعة المبيعات', 'name_en' => 'View sales tracking', 'description' => 'View sales and coupon activations', 'description_ar' => 'عرض المبيعات وتفعيلات الكوبونات', 'description_en' => 'View sales and coupon activations'],
            ['name' => 'sales.export', 'group' => 'sales', 'group_ar' => 'المبيعات', 'group_en' => 'Sales', 'name_ar' => 'تصدير المبيعات', 'name_en' => 'Export sales', 'description' => 'Export sales report', 'description_ar' => 'تصدير تقرير المبيعات', 'description_en' => 'Export sales report'],

            // Reports
            ['name' => 'reports.view', 'group' => 'reports', 'group_ar' => 'التقارير', 'group_en' => 'Reports', 'name_ar' => 'عرض التقارير', 'name_en' => 'View reports', 'description' => 'View and export reports', 'description_ar' => 'عرض وتصدير التقارير', 'description_en' => 'View and export reports'],

            // Financial (مالية)
            ['name' => 'financial.view', 'group' => 'financial', 'group_ar' => 'المالية', 'group_en' => 'Financial', 'name_ar' => 'عرض البيانات المالية', 'name_en' => 'View financial', 'description' => 'View financial dashboard and transactions', 'description_ar' => 'عرض لوحة المعاملات المالية', 'description_en' => 'View financial dashboard'],

            // Withdrawals
            ['name' => 'withdrawals.view', 'group' => 'withdrawals', 'group_ar' => 'السحوبات', 'group_en' => 'Withdrawals', 'name_ar' => 'عرض طلبات السحب', 'name_en' => 'View withdrawals', 'description' => 'View withdrawal requests', 'description_ar' => 'عرض طلبات السحب', 'description_en' => 'View withdrawal requests'],
            ['name' => 'withdrawals.approve', 'group' => 'withdrawals', 'group_ar' => 'السحوبات', 'group_en' => 'Withdrawals', 'name_ar' => 'الموافقة على السحب', 'name_en' => 'Approve withdrawal', 'description' => 'Approve or reject withdrawal', 'description_ar' => 'الموافقة أو رفض طلب السحب', 'description_en' => 'Approve or reject withdrawal'],

            // Invoices
            ['name' => 'invoices.view', 'group' => 'invoices', 'group_ar' => 'الفواتير', 'group_en' => 'Invoices', 'name_ar' => 'عرض الفواتير', 'name_en' => 'View invoices', 'description' => 'View invoices', 'description_ar' => 'عرض الفواتير', 'description_en' => 'View invoices'],
            ['name' => 'invoices.generate', 'group' => 'invoices', 'group_ar' => 'الفواتير', 'group_en' => 'Invoices', 'name_ar' => 'إنشاء فاتورة', 'name_en' => 'Generate invoice', 'description' => 'Generate invoice', 'description_ar' => 'إنشاء فاتورة', 'description_en' => 'Generate invoice'],

            // Ads
            ['name' => 'ads.view', 'group' => 'ads', 'group_ar' => 'الإعلانات', 'group_en' => 'Ads', 'name_ar' => 'عرض الإعلانات', 'name_en' => 'View ads', 'description' => 'View ads list', 'description_ar' => 'عرض قائمة الإعلانات', 'description_en' => 'View ads list'],
            ['name' => 'ads.create', 'group' => 'ads', 'group_ar' => 'الإعلانات', 'group_en' => 'Ads', 'name_ar' => 'إضافة إعلان', 'name_en' => 'Create ad', 'description' => 'Create new ad', 'description_ar' => 'إنشاء إعلان جديد', 'description_en' => 'Create new ad'],
            ['name' => 'ads.update', 'group' => 'ads', 'group_ar' => 'الإعلانات', 'group_en' => 'Ads', 'name_ar' => 'تعديل إعلان', 'name_en' => 'Update ad', 'description' => 'Update ad', 'description_ar' => 'تعديل الإعلان', 'description_en' => 'Update ad'],
            ['name' => 'ads.delete', 'group' => 'ads', 'group_ar' => 'الإعلانات', 'group_en' => 'Ads', 'name_ar' => 'حذف إعلان', 'name_en' => 'Delete ad', 'description' => 'Delete ad', 'description_ar' => 'حذف الإعلان', 'description_en' => 'Delete ad'],

            // Banners
            ['name' => 'banners.view', 'group' => 'banners', 'group_ar' => 'البانرات', 'group_en' => 'Banners', 'name_ar' => 'عرض البانرات', 'name_en' => 'View banners', 'description' => 'View banners', 'description_ar' => 'عرض البانرات', 'description_en' => 'View banners'],
            ['name' => 'banners.create', 'group' => 'banners', 'group_ar' => 'البانرات', 'group_en' => 'Banners', 'name_ar' => 'إضافة بانر', 'name_en' => 'Create banner', 'description' => 'Create banner', 'description_ar' => 'إضافة بانر', 'description_en' => 'Create banner'],
            ['name' => 'banners.update', 'group' => 'banners', 'group_ar' => 'البانرات', 'group_en' => 'Banners', 'name_ar' => 'تعديل بانر', 'name_en' => 'Update banner', 'description' => 'Update banner', 'description_ar' => 'تعديل البانر', 'description_en' => 'Update banner'],
            ['name' => 'banners.delete', 'group' => 'banners', 'group_ar' => 'البانرات', 'group_en' => 'Banners', 'name_ar' => 'حذف بانر', 'name_en' => 'Delete banner', 'description' => 'Delete banner', 'description_ar' => 'حذف البانر', 'description_en' => 'Delete banner'],

            // Notifications
            ['name' => 'notifications.view', 'group' => 'notifications', 'group_ar' => 'الإشعارات', 'group_en' => 'Notifications', 'name_ar' => 'عرض الإشعارات', 'name_en' => 'View notifications', 'description' => 'View notifications', 'description_ar' => 'عرض الإشعارات', 'description_en' => 'View notifications'],
            ['name' => 'notifications.send', 'group' => 'notifications', 'group_ar' => 'الإشعارات', 'group_en' => 'Notifications', 'name_ar' => 'إرسال إشعار', 'name_en' => 'Send notification', 'description' => 'Send notification', 'description_ar' => 'إرسال إشعار', 'description_en' => 'Send notification'],

            // Reviews
            ['name' => 'reviews.view', 'group' => 'reviews', 'group_ar' => 'التقييمات', 'group_en' => 'Reviews', 'name_ar' => 'عرض التقييمات', 'name_en' => 'View reviews', 'description' => 'View reviews', 'description_ar' => 'عرض التقييمات', 'description_en' => 'View reviews'],
            ['name' => 'reviews.update', 'group' => 'reviews', 'group_ar' => 'التقييمات', 'group_en' => 'Reviews', 'name_ar' => 'تعديل تقييم', 'name_en' => 'Update review', 'description' => 'Update review', 'description_ar' => 'تعديل التقييم', 'description_en' => 'Update review'],
            ['name' => 'reviews.delete', 'group' => 'reviews', 'group_ar' => 'التقييمات', 'group_en' => 'Reviews', 'name_ar' => 'حذف تقييم', 'name_en' => 'Delete review', 'description' => 'Delete review', 'description_ar' => 'حذف التقييم', 'description_en' => 'Delete review'],

            // Verification (التحقق من التجار)
            ['name' => 'verification.view', 'group' => 'verification', 'group_ar' => 'التحقق', 'group_en' => 'Verification', 'name_ar' => 'عرض طلبات التحقق', 'name_en' => 'View verification', 'description' => 'View verification requests', 'description_ar' => 'عرض طلبات التحقق', 'description_en' => 'View verification requests'],
            ['name' => 'verification.approve', 'group' => 'verification', 'group_ar' => 'التحقق', 'group_en' => 'Verification', 'name_ar' => 'الموافقة على التحقق', 'name_en' => 'Approve verification', 'description' => 'Approve or reject verification', 'description_ar' => 'الموافقة أو رفض التحقق', 'description_en' => 'Approve or reject verification'],

            // Activity Logs
            ['name' => 'activity_logs.view', 'group' => 'activity_logs', 'group_ar' => 'سجل النشاط', 'group_en' => 'Activity Logs', 'name_ar' => 'عرض سجل النشاط', 'name_en' => 'View activity logs', 'description' => 'View activity logs', 'description_ar' => 'عرض سجل النشاط', 'description_en' => 'View activity logs'],

            // Support
            ['name' => 'support.view', 'group' => 'support', 'group_ar' => 'الدعم', 'group_en' => 'Support', 'name_ar' => 'عرض التذاكر', 'name_en' => 'View tickets', 'description' => 'View support tickets', 'description_ar' => 'عرض تذاكر الدعم', 'description_en' => 'View support tickets'],
            ['name' => 'support.assign', 'group' => 'support', 'group_ar' => 'الدعم', 'group_en' => 'Support', 'name_ar' => 'تعيين تذكرة', 'name_en' => 'Assign ticket', 'description' => 'Assign ticket to agent', 'description_ar' => 'تعيين التذكرة لموظف', 'description_en' => 'Assign ticket to agent'],
            ['name' => 'support.resolve', 'group' => 'support', 'group_ar' => 'الدعم', 'group_en' => 'Support', 'name_ar' => 'حل تذكرة', 'name_en' => 'Resolve ticket', 'description' => 'Resolve support ticket', 'description_ar' => 'حل تذكرة الدعم', 'description_en' => 'Resolve support ticket'],

            // Settings
            ['name' => 'settings.view', 'group' => 'settings', 'group_ar' => 'الإعدادات', 'group_en' => 'Settings', 'name_ar' => 'عرض الإعدادات', 'name_en' => 'View settings', 'description' => 'View app settings', 'description_ar' => 'عرض إعدادات التطبيق', 'description_en' => 'View app settings'],
            ['name' => 'settings.update', 'group' => 'settings', 'group_ar' => 'الإعدادات', 'group_en' => 'Settings', 'name_ar' => 'تعديل الإعدادات', 'name_en' => 'Update settings', 'description' => 'Update app settings', 'description_ar' => 'تعديل إعدادات التطبيق', 'description_en' => 'Update app settings'],

            // Roles & Permissions
            ['name' => 'roles.view', 'group' => 'roles', 'group_ar' => 'الأدوار', 'group_en' => 'Roles', 'name_ar' => 'عرض الأدوار', 'name_en' => 'View roles', 'description' => 'View roles and permissions', 'description_ar' => 'عرض الأدوار والصلاحيات', 'description_en' => 'View roles and permissions'],
            ['name' => 'roles.create', 'group' => 'roles', 'group_ar' => 'الأدوار', 'group_en' => 'Roles', 'name_ar' => 'إضافة دور', 'name_en' => 'Create role', 'description' => 'Create new role', 'description_ar' => 'إنشاء دور جديد', 'description_en' => 'Create new role'],
            ['name' => 'roles.update', 'group' => 'roles', 'group_ar' => 'الأدوار', 'group_en' => 'Roles', 'name_ar' => 'تعديل دور', 'name_en' => 'Update role', 'description' => 'Update role and assign permissions', 'description_ar' => 'تعديل الدور وتعيين الصلاحيات', 'description_en' => 'Update role and assign permissions'],
            ['name' => 'roles.delete', 'group' => 'roles', 'group_ar' => 'الأدوار', 'group_en' => 'Roles', 'name_ar' => 'حذف دور', 'name_en' => 'Delete role', 'description' => 'Delete role', 'description_ar' => 'حذف الدور', 'description_en' => 'Delete role'],
            ['name' => 'permissions.manage', 'group' => 'roles', 'group_ar' => 'الأدوار', 'group_en' => 'Roles', 'name_ar' => 'إدارة الصلاحيات', 'name_en' => 'Manage permissions', 'description' => 'Create, update, delete permissions', 'description_ar' => 'إنشاء وتعديل وحذف الصلاحيات', 'description_en' => 'Create, update, delete permissions'],

            // Warnings
            ['name' => 'warnings.view', 'group' => 'warnings', 'group_ar' => 'التحذيرات', 'group_en' => 'Warnings', 'name_ar' => 'عرض التحذيرات', 'name_en' => 'View warnings', 'description' => 'View merchant warnings', 'description_ar' => 'عرض تحذيرات التجار', 'description_en' => 'View merchant warnings'],
            ['name' => 'warnings.manage', 'group' => 'warnings', 'group_ar' => 'التحذيرات', 'group_en' => 'Warnings', 'name_ar' => 'إدارة التحذيرات', 'name_en' => 'Manage warnings', 'description' => 'Add or remove warnings', 'description_ar' => 'إضافة أو إزالة التحذيرات', 'description_en' => 'Add or remove warnings'],
        ];

        foreach ($permissions as $item) {
            Permission::firstOrCreate(
                ['name' => $item['name']],
                array_merge($item, [
                    'group_ar' => $item['group_ar'] ?? $item['group'],
                    'group_en' => $item['group_en'] ?? $item['group'],
                ])
            );
        }
    }
}
