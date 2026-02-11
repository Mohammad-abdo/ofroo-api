<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Governorate;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class MerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $merchantRole = Role::where('name', 'merchant')->first();
        $governorates = Governorate::with('cities')->get();

        if ($governorates->isEmpty()) {
            $this->command->warn('No governorates found. Run GovernorateSeeder first.');
            return;
        }

        $genders = ['male', 'female'];

        $merchants = [
            [
                'name' => 'City Stars Mall',
                'name_ar' => 'سيتي ستارز',
                'name_en' => 'City Stars Mall',
                'description' => 'Large shopping mall in Cairo',
                'description_ar' => 'مجمع تجاري كبير في القاهرة',
                'description_en' => 'Large shopping mall in Cairo',
                'address' => 'Nasr City, Cairo, Egypt',
                'address_ar' => 'مدينة نصر، القاهرة، مصر',
                'address_en' => 'Nasr City, Cairo, Egypt',
                'lat' => 30.0626,
                'lng' => 31.3219,
            ],
            [
                'name' => 'Koshary Abou Tarek',
                'name_ar' => 'كشري أبو طارق',
                'name_en' => 'Koshary Abou Tarek',
                'description' => 'Traditional Egyptian restaurant',
                'description_ar' => 'مطعم مصري تقليدي',
                'description_en' => 'Traditional Egyptian restaurant',
                'address' => 'Downtown, Cairo, Egypt',
                'address_ar' => 'وسط البلد، القاهرة، مصر',
                'address_en' => 'Downtown, Cairo, Egypt',
                'lat' => 30.0444,
                'lng' => 31.2357,
            ],
            [
                'name' => 'Mall of Arabia',
                'name_ar' => 'مول العرب',
                'name_en' => 'Mall of Arabia',
                'description' => 'Shopping mall in Giza',
                'description_ar' => 'مجمع تجاري في الجيزة',
                'description_en' => 'Shopping mall in Giza',
                'address' => '6th October City, Giza, Egypt',
                'address_ar' => 'مدينة 6 أكتوبر، الجيزة، مصر',
                'address_en' => '6th October City, Giza, Egypt',
                'lat' => 29.9697,
                'lng' => 30.9564,
            ],
            [
                'name' => 'Cairo Festival City',
                'name_ar' => 'كايرو فستيفال سيتي',
                'name_en' => 'Cairo Festival City',
                'description' => 'Popular shopping destination',
                'description_ar' => 'وجهة تسوق شعبية',
                'description_en' => 'Popular shopping destination',
                'address' => 'New Cairo, Egypt',
                'address_ar' => 'القاهرة الجديدة، مصر',
                'address_en' => 'New Cairo, Egypt',
                'lat' => 30.0131,
                'lng' => 31.6850,
            ],
            [
                'name' => 'El Tahrir Restaurant',
                'name_ar' => 'مطعم التحرير',
                'name_en' => 'El Tahrir Restaurant',
                'description' => 'Fast food restaurant chain',
                'description_ar' => 'سلسلة مطاعم الوجبات السريعة',
                'description_en' => 'Fast food restaurant chain',
                'address' => 'Multiple Locations, Egypt',
                'address_ar' => 'مواقع متعددة، مصر',
                'address_en' => 'Multiple Locations, Egypt',
                'lat' => 30.0444,
                'lng' => 31.2357,
            ],
        ];

        foreach ($merchants as $index => $merchantData) {
            $email = 'merchant' . ($index + 1) . '@ofroo.com';
            $phoneNumber = '+20' . $faker->unique()->numerify('##########');
            $gov = $governorates->random();
            $city = $gov->cities->random();

            $merchantUser = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $merchantData['name'],
                    'phone' => $phoneNumber,
                    'password' => Hash::make('password'),
                    'language' => 'ar',
                    'role_id' => $merchantRole->id,
                    'email_verified_at' => now(),
                    'country' => 'مصر',
                    'gender' => $faker->randomElement($genders),
                    'city_id' => $city->id,
                    'governorate_id' => $gov->id,
                ]
            );

            $merchant = Merchant::firstOrCreate(
                ['user_id' => $merchantUser->id],
                [
                    'company_name' => $merchantData['name'],
                    'company_name_ar' => $merchantData['name_ar'],
                    'company_name_en' => $merchantData['name_en'],
                    'description' => $merchantData['description'],
                    'description_ar' => $merchantData['description_ar'],
                    'description_en' => $merchantData['description_en'],
                    'address' => $merchantData['address'],
                    'address_ar' => $merchantData['address_ar'],
                    'address_en' => $merchantData['address_en'],
                    'phone' => $phoneNumber,
                    'whatsapp_link' => 'https://wa.me/' . str_replace('+', '', $phoneNumber),
                    'city' => $city->name_ar,
                    'country' => 'مصر',
                    'approved' => true,
                ]
            );

            if (!$merchant->wasRecentlyCreated) {
                continue;
            }
            Branch::create([
                'merchant_id' => $merchant->id,
                'name' => $merchantData['name'],
                'name_ar' => $merchantData['name_ar'],
                'name_en' => $merchantData['name_en'],
                'lat' => $merchantData['lat'],
                'lng' => $merchantData['lng'],
                'address' => $merchantData['address'],
                'address_ar' => $merchantData['address_ar'],
                'address_en' => $merchantData['address_en'],
                'is_active' => true,
                'google_place_id' => 'ChIJ' . $faker->bothify('????????????????'),
                'opening_hours' => [
                    'monday' => '10:00-22:00',
                    'tuesday' => '10:00-22:00',
                    'wednesday' => '10:00-22:00',
                    'thursday' => '10:00-22:00',
                    'friday' => '14:00-22:00',
                    'saturday' => '10:00-22:00',
                    'sunday' => '10:00-22:00',
                ],
            ]);
        }

        // Create 20 more random merchants
        for ($i = 6; $i <= 25; $i++) {
            $email = "merchant{$i}@example.com";
            $phoneNumber = '+20' . $faker->unique()->numerify('##########');
            $gov = $governorates->random();
            $city = $gov->cities->random();

            $merchantUser = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $faker->company(),
                    'phone' => $phoneNumber,
                    'password' => Hash::make('password'),
                    'language' => 'ar',
                    'role_id' => $merchantRole->id,
                    'email_verified_at' => $faker->optional(0.9)->dateTimeBetween('-6 months', 'now'),
                    'country' => 'مصر',
                    'gender' => $faker->randomElement($genders),
                    'city_id' => $city->id,
                    'governorate_id' => $gov->id,
                ]
            );

            $merchant = Merchant::firstOrCreate(
                ['user_id' => $merchantUser->id],
                [
                    'company_name' => $faker->company(),
                    'company_name_ar' => $faker->company() . ' (عربي)',
                    'company_name_en' => $faker->company(),
                    'description' => $faker->text(200),
                    'description_ar' => $faker->realText(200),
                    'description_en' => $faker->text(200),
                    'address' => $faker->address(),
                    'address_ar' => $faker->address(),
                    'address_en' => $faker->address(),
                    'phone' => $phoneNumber,
                    'whatsapp_link' => 'https://wa.me/' . str_replace('+', '', $phoneNumber),
                    'city' => $city->name_ar,
                    'country' => 'مصر',
                    'approved' => $faker->boolean(80),
                ]
            );

            if (!$merchant->wasRecentlyCreated) {
                continue;
            }
            Branch::create([
                'merchant_id' => $merchant->id,
                'name' => $merchant->company_name,
                'name_ar' => $merchant->company_name_ar,
                'name_en' => $merchant->company_name_en,
                'lat' => $faker->latitude(30.0, 31.5),
                'lng' => $faker->longitude(29.0, 32.5),
                'address' => $faker->address(),
                'address_ar' => $faker->address(),
                'address_en' => $faker->address(),
                'is_active' => true,
                'google_place_id' => 'ChIJ' . $faker->bothify('????????????????'),
                'opening_hours' => [
                    'monday' => $faker->time('H:i') . '-' . $faker->time('H:i'),
                    'tuesday' => $faker->time('H:i') . '-' . $faker->time('H:i'),
                    'wednesday' => $faker->time('H:i') . '-' . $faker->time('H:i'),
                    'thursday' => $faker->time('H:i') . '-' . $faker->time('H:i'),
                    'friday' => $faker->time('H:i') . '-' . $faker->time('H:i'),
                    'saturday' => $faker->time('H:i') . '-' . $faker->time('H:i'),
                    'sunday' => $faker->time('H:i') . '-' . $faker->time('H:i'),
                ],
            ]);
        }
    }
}
