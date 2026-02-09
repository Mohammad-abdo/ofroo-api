<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();
        $merchantRole = Role::where('name', 'merchant')->first();

        // Egypt governorates
        $egyptGovernorates = ['القاهرة', 'الجيزة', 'الإسكندرية', 'المنصورة', 'طنطا', 'أسيوط', 'الأقصر', 'أسوان', 'بورسعيد', 'السويس', 'الإسماعيلية', 'شبرا الخيمة', 'زقازيق', 'بنها', 'كفر الشيخ', 'دمياط', 'المنيا', 'سوهاج', 'قنا', 'البحر الأحمر', 'مطروح', 'شمال سيناء', 'جنوب سيناء', 'الوادي الجديد', 'البحيرة', 'الدقهلية', 'الشرقية', 'القليوبية', 'الفيوم', 'بني سويف'];

        // Admin users
        User::firstOrCreate(
            ['email' => 'admin@ofroo.com'],
            [
                'name' => 'Admin User',
                'phone' => '+201234567890',
                'password' => Hash::make('password'),
                'language' => 'en',
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
                'city' => $faker->randomElement($egyptGovernorates),
                'country' => 'مصر',
            ]
        );

        // Create 50 regular users
        for ($i = 1; $i <= 50; $i++) {
            User::create([
                'name' => $faker->name(),
                'email' => "user{$i}@example.com",
                'phone' => '+20' . $faker->unique()->numerify('##########'),
                'password' => Hash::make('password'),
                'language' => $faker->randomElement(['ar', 'en']),
                'role_id' => $userRole->id,
                'email_verified_at' => $faker->optional(0.8)->dateTimeBetween('-1 year', 'now'),
                'last_location_lat' => $faker->latitude(30.0, 31.5),
                'last_location_lng' => $faker->longitude(29.0, 32.5),
                'city' => $faker->randomElement($egyptGovernorates),
                'country' => 'مصر',
            ]);
        }
    }
}