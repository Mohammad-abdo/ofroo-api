<?php

namespace Database\Seeders;

use App\Models\Governorate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Admin dashboard staff: operations, content, finance — linked to RoleSeeder roles
 * (employee, data_entry, accountant) with scoped permissions.
 */
class AdminStaffSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->whereIn('name', ['employee', 'data_entry', 'accountant'])->get()->keyBy('name');
        if ($roles->count() < 3) {
            $this->command->warn('AdminStaffSeeder: run RoleSeeder first (need employee, data_entry, accountant roles).');

            return;
        }

        $gov = Governorate::with('cities')->first();
        if (! $gov || $gov->cities->isEmpty()) {
            $this->command->warn('AdminStaffSeeder: no governorate/cities. Run GovernorateSeeder first.');

            return;
        }
        $city = $gov->cities->first();

        $staff = [
            [
                'email' => 'ops.manager@ofroo.com',
                'name' => 'Sara El-Masry',
                'role' => 'employee',
                'phone' => '+201100000001',
                'language' => 'ar',
            ],
            [
                'email' => 'support.lead@ofroo.com',
                'name' => 'Youssef Khaled',
                'role' => 'employee',
                'phone' => '+201100000002',
                'language' => 'ar',
            ],
            [
                'email' => 'content.editor@ofroo.com',
                'name' => 'Nour Hassan',
                'role' => 'data_entry',
                'phone' => '+201100000003',
                'language' => 'en',
            ],
            [
                'email' => 'catalog.admin@ofroo.com',
                'name' => 'Omar Farouk',
                'role' => 'data_entry',
                'phone' => '+201100000004',
                'language' => 'ar',
            ],
            [
                'email' => 'finance@ofroo.com',
                'name' => 'Layla Ibrahim',
                'role' => 'accountant',
                'phone' => '+201100000005',
                'language' => 'en',
            ],
        ];

        foreach ($staff as $row) {
            $role = $roles->get($row['role']);
            if (! $role) {
                continue;
            }

            User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'password' => Hash::make('password'),
                    'language' => $row['language'],
                    'role_id' => $role->id,
                    'email_verified_at' => now(),
                    'country' => 'مصر',
                    'gender' => 'female',
                    'city_id' => $city->id,
                    'governorate_id' => $gov->id,
                ]
            );
        }

        $this->command->info('Seeded '.count($staff).' admin staff users (password: password). Roles: employee, data_entry, accountant.');
    }
}
