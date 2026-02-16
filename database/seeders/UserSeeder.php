<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = $this->makeFaker();
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();

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
                'gender' => $faker('randomElement', [$genders]),
                'city_id' => ($firstCity = $governorates->first()->cities->first()) ? $firstCity->id : null,
                'governorate_id' => $governorates->first()->id,
            ]
        );

        // Create 50 regular users
        for ($i = 1; $i <= 50; $i++) {
            $gov = $governorates->random();
            $city = $gov->cities->random();
            User::firstOrCreate(
                ['email' => "user{$i}@example.com"],
                [
                    'name' => $faker('name'),
                    'phone' => '+20' . $faker('numerify', ['##########']),
                    'password' => Hash::make('password'),
                    'language' => $faker('randomElement', [['ar', 'en']]),
                    'role_id' => $userRole->id,
                    'email_verified_at' => now(),
                    'last_location_lat' => $faker('latitude', [30.0, 31.5]),
                    'last_location_lng' => $faker('longitude', [29.0, 32.5]),
                    'country' => 'مصر',
                    'gender' => $faker('randomElement', [$genders]),
                    'city_id' => $city->id,
                    'governorate_id' => $gov->id,
                ]
            );
        }
    }

    /**
     * Use Laravel's built-in faker (from Factory) so Faker is loaded from framework.
     */
    private function makeFaker(): \Closure
    {
        if (class_exists(\Faker\Factory::class)) {
            $faker = \Faker\Factory::create('ar_EG');
            return function (string $method, array $args = []) use ($faker) {
                return $faker->$method(...$args);
            };
        }
        // Fallback when fakerphp/faker not installed (e.g. composer install --no-dev)
        return function (string $method, array $args = []) {
            return match ($method) {
                'randomElement' => $args[0][array_rand($args[0])],
                'name' => 'User ' . random_int(1000, 9999),
                'numerify' => (string) random_int(1000000000, 9999999999),
                'latitude' => round(($args[0] ?? 30.0) + (mt_getrandmax() ? mt_rand() / mt_getrandmax() : 0.5) * (($args[1] ?? 31.5) - ($args[0] ?? 30.0)), 6),
                'longitude' => round(($args[0] ?? 29.0) + (mt_getrandmax() ? mt_rand() / mt_getrandmax() : 0.5) * (($args[1] ?? 32.5) - ($args[0] ?? 29.0)), 6),
                default => $args[0] ?? null,
            };
        };
    }
}
