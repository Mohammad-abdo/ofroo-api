<?php

namespace Database\Seeders;

use App\Models\AdminNotification;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->pluck('id')->toArray();

        $types = ['info', 'success', 'warning', 'error', 'promotion', 'system'];
        $audiences = ['all', 'users', 'merchants', 'admins'];

        $notifications = [
            ['ar' => 'مرحباً بك في منصة أوفرو!', 'en' => 'Welcome to OFROO Platform!', 'type' => 'info'],
            ['ar' => 'تحديث جديد: تحسينات في سرعة التطبيق', 'en' => 'New Update: App Speed Improvements', 'type' => 'system'],
            ['ar' => 'عروض رمضان متاحة الآن!', 'en' => 'Ramadan Offers Available Now!', 'type' => 'promotion'],
            ['ar' => 'صيانة مجدولة يوم الجمعة', 'en' => 'Scheduled Maintenance on Friday', 'type' => 'warning'],
            ['ar' => 'تم ترقية حسابك بنجاح', 'en' => 'Your Account Has Been Upgraded', 'type' => 'success'],
            ['ar' => 'تنبيه: تحديث شروط الاستخدام', 'en' => 'Alert: Terms of Use Updated', 'type' => 'warning'],
            ['ar' => 'خصم 20% على جميع الكوبونات اليوم', 'en' => '20% Off All Coupons Today', 'type' => 'promotion'],
            ['ar' => 'خطأ في معالجة الدفع — يتم الحل', 'en' => 'Payment Processing Error — Being Resolved', 'type' => 'error'],
            ['ar' => 'ميزة جديدة: محفظة التاجر', 'en' => 'New Feature: Merchant Wallet', 'type' => 'info'],
            ['ar' => 'تقرير المبيعات الشهري جاهز', 'en' => 'Monthly Sales Report Ready', 'type' => 'info'],
            ['ar' => 'تم إطلاق نظام الباركود الجديد', 'en' => 'New Barcode System Launched', 'type' => 'system'],
            ['ar' => 'تذكير: تحديث بيانات الحساب البنكي', 'en' => 'Reminder: Update Bank Account Info', 'type' => 'warning'],
        ];

        $messagesAr = [
            'يرجى مراجعة لوحة التحكم للاطلاع على التفاصيل.',
            'ستتم معالجة الطلبات خلال 24 ساعة.',
            'اضغط لمعرفة المزيد عن هذا التحديث.',
            'نعمل على تحسين تجربتك باستمرار.',
            'تواصل مع الدعم الفني في حال وجود أي استفسار.',
        ];
        $messagesEn = [
            'Please check the dashboard for details.',
            'Requests will be processed within 24 hours.',
            'Click to learn more about this update.',
            'We are continuously improving your experience.',
            'Contact support if you have any questions.',
        ];

        for ($i = 0; $i < 50; $i++) {
            $notif = $faker->randomElement($notifications);
            $isSent = $faker->boolean(70);

            try {
                AdminNotification::create([
                    'title' => $notif['ar'],
                    'title_ar' => $notif['ar'],
                    'title_en' => $notif['en'],
                    'message' => $faker->randomElement($messagesAr),
                    'message_ar' => $faker->randomElement($messagesAr),
                    'message_en' => $faker->randomElement($messagesEn),
                    'type' => $notif['type'],
                    'target_audience' => $faker->randomElement($audiences),
                    'target_user_ids' => null,
                    'target_merchant_ids' => null,
                    'action_url' => $faker->optional(0.3)->url(),
                    'action_text' => $faker->optional(0.3)->randomElement(['عرض', 'مزيد', 'فتح', 'View', 'More', 'Open']),
                    'image_url' => $faker->optional(0.2)->imageUrl(400, 200),
                    'is_sent' => $isSent,
                    'scheduled_at' => ! $isSent
                        ? Carbon::createFromInterface($faker->dateTimeBetween('now', '+30 days'))->utc()
                        : null,
                    'sent_at' => $isSent
                        ? Carbon::createFromInterface($faker->dateTimeBetween('-3 months', 'now'))->utc()
                        : null,
                    'created_by' => ! empty($admins) ? $faker->randomElement($admins) : null,
                    'created_at' => Carbon::createFromInterface($faker->dateTimeBetween('-6 months', 'now'))->utc(),
                ]);
            } catch (\Throwable) {
            }
        }

        $this->command->info('Notifications seeded ('.AdminNotification::count().' total).');
    }
}
