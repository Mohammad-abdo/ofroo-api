<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $users = User::all();

        $activities = [
            'user.login',
            'user.logout',
            'user.profile_updated',
            'user.password_changed',
            'user.blocked',
            'user.unblocked',
            'offer.created',
            'offer.updated',
            'offer.deleted',
            'offer.approved',
            'offer.rejected',
            'coupon.created',
            'coupon.updated',
            'coupon.activated',
            'coupon.expired',
            'coupon.barcode_generated',
            'order.created',
            'order.completed',
            'order.cancelled',
            'order.refunded',
            'payment.completed',
            'payment.failed',
            'merchant.approved',
            'merchant.rejected',
            'merchant.suspended',
            'merchant.warning_issued',
            'merchant.staff_added',
            'merchant.staff_removed',
            'wallet.credit',
            'wallet.debit',
            'wallet.withdrawal_requested',
            'wallet.withdrawal_approved',
            'wallet.withdrawal_rejected',
            'commission.calculated',
            'commission.paid',
            'ad.created',
            'ad.updated',
            'ad.deleted',
            'notification.sent',
            'notification.scheduled',
            'role.created',
            'role.updated',
            'role.permissions_changed',
            'settings.updated',
        ];

        $modelTypes = [
            'App\\Models\\Order',
            'App\\Models\\Offer',
            'App\\Models\\User',
            'App\\Models\\Coupon',
            'App\\Models\\Merchant',
            'App\\Models\\Withdrawal',
            'App\\Models\\Commission',
            'App\\Models\\Ad',
            'App\\Models\\AdminNotification',
        ];

        for ($i = 0; $i < 500; $i++) {
            $user = $faker->optional(0.85) ? $users->random() : null;
            $action = $faker->randomElement($activities);
            $modelType = $faker->optional(0.6)->randomElement($modelTypes);

            try {
                ActivityLog::create([
                    'user_id'     => $user?->id,
                    'actor_role'  => $user?->role?->name ?? ($user ? 'user' : null),
                    'action'      => $action,
                    'model_type'  => $modelType,
                    'model_id'    => $modelType ? $faker->numberBetween(1, 500) : null,
                    'target_type' => $faker->optional(0.3)->randomElement(['App\\Models\\Merchant', 'App\\Models\\User']),
                    'target_id'   => $faker->optional(0.3)->numberBetween(1, 100),
                    'description' => $faker->sentence(),
                    'ip_address'  => $faker->ipv4(),
                    'user_agent'  => $faker->userAgent(),
                    'old_values'  => $faker->optional(0.25)->randomElement([
                        ['status' => 'pending'], ['status' => 'active'], ['balance' => 500],
                    ]),
                    'new_values'  => $faker->optional(0.25)->randomElement([
                        ['status' => 'active'], ['status' => 'completed'], ['balance' => 1500],
                    ]),
                    'metadata'    => [
                        'key'   => $faker->word(),
                        'value' => $faker->word(),
                        'source' => $faker->randomElement(['web', 'api', 'system', 'cron']),
                    ],
                    'created_at'  => $faker->dateTimeBetween('-6 months', 'now'),
                ]);
            } catch (\Throwable) {}
        }

        $this->command->info('Activity logs seeded (' . ActivityLog::count() . ' total).');
    }
}
