<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\TaxSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');

        // Create Settings
        $settings = [
            ['key' => 'site_name', 'value' => 'OFROO', 'type' => 'text'],
            ['key' => 'site_name_ar', 'value' => 'أوفرو', 'type' => 'text'],
            ['key' => 'site_email', 'value' => 'info@ofroo.com', 'type' => 'email'],
            ['key' => 'site_phone', 'value' => '+201234567890', 'type' => 'text'],
            ['key' => 'commission_rate', 'value' => '6', 'type' => 'number'],
            ['key' => 'currency', 'value' => 'EGP', 'type' => 'text'],
            ['key' => 'default_language', 'value' => 'ar', 'type' => 'text'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                ]
            );
        }

        // Create Payment Gateways
        $gateways = [
            [
                'name' => 'knet',
                'display_name' => 'KNET',
                'display_name_ar' => 'كي نت',
                'display_name_en' => 'KNET',
                'is_active' => true,
                'credentials' => [
                    'merchant_id' => '123456',
                    'api_key' => 'test_key',
                ],
                'settings' => [
                    'test_mode' => true,
                ],
                'fee_percentage' => 0.5,
                'fee_fixed' => 0.5,
                'order_index' => 1,
            ],
            [
                'name' => 'visa',
                'display_name' => 'Visa',
                'display_name_ar' => 'فيزا',
                'display_name_en' => 'Visa',
                'is_active' => true,
                'credentials' => [
                    'merchant_id' => '789012',
                    'api_key' => 'test_key',
                ],
                'settings' => [
                    'test_mode' => true,
                ],
                'fee_percentage' => 1.0,
                'fee_fixed' => 1.0,
                'order_index' => 2,
            ],
            [
                'name' => 'mastercard',
                'display_name' => 'Mastercard',
                'display_name_ar' => 'ماستركارد',
                'display_name_en' => 'Mastercard',
                'is_active' => true,
                'credentials' => [
                    'merchant_id' => '345678',
                    'api_key' => 'test_key',
                ],
                'settings' => [
                    'test_mode' => true,
                ],
                'fee_percentage' => 1.0,
                'fee_fixed' => 1.0,
                'order_index' => 3,
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::firstOrCreate(
                ['name' => $gateway['name']],
                $gateway
            );
        }

        // Create Tax Settings
        TaxSetting::firstOrCreate(
            ['country_code' => 'EG'],
            [
                'country_code' => 'EG',
                'tax_name' => 'VAT',
                'tax_name_ar' => 'ضريبة القيمة المضافة',
                'tax_name_en' => 'Value Added Tax',
                'tax_rate' => 14,
                'is_active' => true,
                'exempt_categories' => [],
            ]
        );
    }
}
