<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class SupportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $users = User::all();
        $merchants = Merchant::all();
        $admins = User::whereHas('role', function ($query) {
            $query->where('name', 'admin');
        })->get();

        $categories = [
            'technical' => ['ar' => 'تقني', 'en' => 'Technical'],
            'financial' => ['ar' => 'مالي', 'en' => 'Financial'],
            'content' => ['ar' => 'محتوى', 'en' => 'Content'],
            'fraud' => ['ar' => 'احتيال', 'en' => 'Fraud'],
            'other' => ['ar' => 'أخرى', 'en' => 'Other'],
        ];

        // Next ticket number: max existing numeric part + 1 (avoid duplicate key on re-seed)
        $maxNum = SupportTicket::query()
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(ticket_number, '-', -1) AS UNSIGNED)), 0) as n")
            ->value('n');
        $start = (int) $maxNum + 1;

        // Create 100 support tickets
        for ($i = 0; $i < 100; $i++) {
            $user = $users->random();
            $merchant = $faker->optional(0.3) ? $merchants->random() : null;
            $assignedTo = $faker->optional(0.5) ? $admins->random() : null;
            $category = $faker->randomElement(array_keys($categories));

            $ticketNumber = 'TKT-' . str_pad($start + $i, 6, '0', STR_PAD_LEFT);

            SupportTicket::create([
                'ticket_number' => $ticketNumber,
                'user_id' => $user->id,
                'merchant_id' => $merchant ? $merchant->id : null,
                'assigned_to' => $assignedTo ? $assignedTo->id : null,
                'category' => $category,
                'category_ar' => $categories[$category]['ar'],
                'category_en' => $categories[$category]['en'],
                'subject' => $faker->sentence(),
                'description' => $faker->realText(500),
                'priority' => $faker->randomElement(['low', 'medium', 'high', 'urgent']),
                'status' => $faker->randomElement(['open', 'in_progress', 'resolved', 'closed', 'cancelled']),
                'metadata' => [
                    'source' => $faker->randomElement(['web', 'mobile', 'email']),
                ],
                'created_at' => $faker->dateTimeBetween('-3 months', 'now'),
                'resolved_at' => $faker->optional(0.4)->dateTimeBetween('-2 months', 'now'),
            ]);
        }
    }
}
