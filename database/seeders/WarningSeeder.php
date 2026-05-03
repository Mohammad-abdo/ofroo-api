<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\MerchantWarning;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class WarningSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $merchants = Merchant::all();
        $admins = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->pluck('id')->toArray();

        if ($merchants->isEmpty() || empty($admins)) {
            $this->command->warn('Merchants or admin users missing.');

            return;
        }

        $warningTypes = [
            ['type' => 'violation', 'msg_ar' => 'مخالفة سياسة المنصة', 'msg_en' => 'Platform policy violation'],
            ['type' => 'quality', 'msg_ar' => 'جودة الخدمة أقل من المعايير المطلوبة', 'msg_en' => 'Service quality below required standards'],
            ['type' => 'compliance', 'msg_ar' => 'عدم الامتثال لشروط الاستخدام', 'msg_en' => 'Non-compliance with terms of use'],
            ['type' => 'fraud', 'msg_ar' => 'اشتباه في نشاط احتيالي', 'msg_en' => 'Suspected fraudulent activity'],
            ['type' => 'coupon_abuse', 'msg_ar' => 'إساءة استخدام نظام الكوبونات', 'msg_en' => 'Coupon system abuse detected'],
            ['type' => 'late_delivery', 'msg_ar' => 'تأخر متكرر في تقديم الخدمة', 'msg_en' => 'Repeated service delivery delays'],
            ['type' => 'customer_complaint', 'msg_ar' => 'شكاوى متعددة من العملاء', 'msg_en' => 'Multiple customer complaints received'],
            ['type' => 'inactivity', 'msg_ar' => 'عدم نشاط لفترة طويلة', 'msg_en' => 'Prolonged inactivity on the platform'],
        ];

        foreach ($merchants as $merchant) {
            $count = $faker->numberBetween(0, 4);
            for ($i = 0; $i < $count; $i++) {
                $warnData = $faker->randomElement($warningTypes);
                $issuedAt = Carbon::createFromInterface($faker->dateTimeBetween('-6 months', 'now'))->utc();
                $active = $faker->boolean(60);

                try {
                    MerchantWarning::create([
                        'merchant_id' => $merchant->id,
                        'admin_id' => $faker->randomElement($admins),
                        'warning_type' => $warnData['type'],
                        'message' => $warnData['msg_ar'].' — '.$warnData['msg_en'],
                        'issued_at' => $issuedAt,
                        'expires_at' => ($ex = $faker->optional(0.5)->dateTimeBetween('now', '+6 months'))
                            ? Carbon::createFromInterface($ex)->utc()
                            : null,
                        'active' => $active,
                        'metadata' => [
                            'severity' => $faker->randomElement(['low', 'medium', 'high', 'critical']),
                            'note' => $faker->optional(0.3)->sentence(),
                        ],
                        'created_at' => $issuedAt,
                    ]);
                } catch (\Throwable) {
                }
            }
        }

        $this->command->info('Warnings seeded ('.MerchantWarning::count().' total).');
    }
}
