<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
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

        $governorates = Governorate::with('cities')->get();
        if ($governorates->isEmpty()) {
            $this->command->warn('No governorates found. Run GovernorateSeeder first.');
            return;
        }

        $genders = ['male', 'female'];

        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@ofroo.com'],
            [
                'name' => 'Admin User',
                'phone' => '+201234567890',
                'password' => Hash::make('password'),
                'language' => 'en',
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
                'country' => 'مصر',
                'gender' => $faker->randomElement($genders),
                'city_id' => $governorates->first()->cities->first()?->id,
                'governorate_id' => $governorates->first()->id,
            ]
        );

        // Create 50 regular users (firstOrCreate by email so re-seed doesn't duplicate)
        for ($i = 1; $i <= 50; $i++) {
            $gov = $governorates->random();
            $city = $gov->cities->random();
            User::firstOrCreate(
                ['email' => "user{$i}@example.com"],
                [
                    'name' => $faker->name(),
                    'phone' => '+20' . $faker->unique()->numerify('##########'),
                    'password' => Hash::make('password'),
                    'language' => $faker->randomElement(['ar', 'en']),
                    'role_id' => $userRole->id,
                    'email_verified_at' => $faker->optional(0.8)->dateTimeBetween('-1 year', 'now'),
                    'last_location_lat' => $faker->latitude(30.0, 31.5),
                    'last_location_lng' => $faker->longitude(29.0, 32.5),
                    'country' => 'مصر',
                    'gender' => $faker->randomElement($genders),
                    'city_id' => $city->id,
                    'governorate_id' => $gov->id,
                ]
            );
        }
    }
}
