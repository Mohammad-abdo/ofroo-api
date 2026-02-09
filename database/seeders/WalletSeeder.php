<?php

namespace Database\Seeders;

use App\Models\AdminWallet;
use App\Models\MerchantWallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');

        // Create Admin Wallet
        $adminWallet = AdminWallet::getOrCreate();
        $adminWallet->update([
            'balance' => $faker->randomFloat(2, 100000, 1000000),
            'currency' => 'EGP',
        ]);

        // Create admin wallet transactions
        for ($i = 0; $i < 100; $i++) {
            $amount = $faker->randomFloat(2, 100, 5000);
            $balanceBefore = $adminWallet->balance;
            $adminWallet->balance += $amount;
            $balanceAfter = $adminWallet->balance;

            WalletTransaction::create([
                'wallet_id' => $adminWallet->id,
                'wallet_type' => 'admin',
                'transaction_type' => $faker->randomElement(['credit', 'debit', 'commission', 'refund', 'adjustment', 'fee']),
                'related_type' => $faker->optional(0.7)->randomElement(['App\\Models\\Order', 'App\\Models\\Withdrawal']),
                'related_id' => $faker->optional(0.7)->numberBetween(1, 1000),
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'note' => $faker->optional(0.5)->sentence(),
                'created_by_user_id' => $faker->optional(0.6)->numberBetween(1, 10),
                'metadata' => [
                    'description' => $faker->sentence(),
                ],
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
            ]);

            $adminWallet->save();
        }

        // Create wallet transactions for merchant wallets
        $merchantWallets = MerchantWallet::all();
        foreach ($merchantWallets as $merchantWallet) {
            for ($i = 0; $i < 30; $i++) {
                $amount = $faker->randomFloat(2, 10, 1000);
                $transactionType = $faker->randomElement(['credit', 'debit', 'payout', 'commission', 'refund', 'fee', 'adjustment']);
                $balanceBefore = $merchantWallet->balance;
                
                if (in_array($transactionType, ['credit', 'refund'])) {
                    $merchantWallet->balance += $amount;
                } else {
                    $merchantWallet->balance -= $amount;
                }
                
                $balanceAfter = $merchantWallet->balance;

                WalletTransaction::create([
                    'wallet_id' => $merchantWallet->id,
                    'wallet_type' => 'merchant',
                    'transaction_type' => $transactionType,
                    'related_type' => $faker->optional(0.7)->randomElement(['App\\Models\\Order', 'App\\Models\\Withdrawal', 'App\\Models\\FinancialTransaction']),
                    'related_id' => $faker->optional(0.7)->numberBetween(1, 1000),
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'note' => $faker->optional(0.5)->sentence(),
                    'created_by_user_id' => $faker->optional(0.6)->numberBetween(1, 10),
                    'metadata' => [
                        'description' => $faker->sentence(),
                    ],
                    'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                ]);

                $merchantWallet->save();
            }
        }
    }
}

