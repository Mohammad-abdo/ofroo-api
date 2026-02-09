<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\FinancialTransaction;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Withdrawal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class FinancialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $merchants = Merchant::where('approved', true)->get();
        $orders = Order::all();
        $payments = Payment::all();

        foreach ($merchants as $merchant) {
            // Create merchant wallet
            $wallet = MerchantWallet::firstOrCreate(
                ['merchant_id' => $merchant->id],
                [
                    'balance' => $faker->randomFloat(2, 0, 10000),
                    'pending_balance' => $faker->randomFloat(2, 0, 5000),
                ]
            );

            $currentBalance = $wallet->balance;

            // Create financial transactions
            for ($i = 0; $i < 50; $i++) {
                $amount = $faker->randomFloat(2, 10, 1000);
                $transactionType = $faker->randomElement(['order_revenue', 'commission', 'withdrawal', 'refund', 'expense', 'subscription']);
                $transactionFlow = in_array($transactionType, ['refund', 'commission', 'withdrawal', 'expense']) ? 'outgoing' : 'incoming';
                
                $balanceBefore = $currentBalance;
                if ($transactionFlow === 'incoming') {
                    $currentBalance += $amount;
                } else {
                    $currentBalance -= $amount;
                }
                $balanceAfter = $currentBalance;

                // Get valid order and payment IDs
                $orderId = null;
                $paymentId = null;
                
                // Get orders for this merchant
                $merchantOrders = $orders->where('merchant_id', $merchant->id);
                
                if ($merchantOrders->isNotEmpty() && $faker->boolean(50)) {
                    $order = $faker->randomElement($merchantOrders->toArray());
                    $orderId = $order['id'];
                    
                    // If we have an order, try to get a payment for it
                    if ($faker->boolean(30)) {
                        $orderPayments = $payments->where('order_id', $orderId);
                        if ($orderPayments->isNotEmpty()) {
                            $payment = $faker->randomElement($orderPayments->toArray());
                            $paymentId = $payment['id'];
                        }
                    }
                }

                FinancialTransaction::create([
                    'merchant_id' => $merchant->id,
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'transaction_type' => $transactionType,
                    'transaction_flow' => $transactionFlow,
                    'amount' => $amount,
                    'balance_before' => round($balanceBefore, 2),
                    'balance_after' => round($balanceAfter, 2),
                    'description' => $faker->sentence(),
                    'description_ar' => $faker->optional(0.7)->realText(100),
                    'description_en' => $faker->optional(0.7)->text(100),
                    'reference_number' => $faker->optional(0.6)->bothify('REF#########'),
                    'metadata' => [
                        'note' => $faker->optional(0.3)->sentence(),
                    ],
                    'status' => 'completed',
                    'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                ]);
            }

            // Update wallet balance
            $wallet->update(['balance' => $currentBalance]);

            // Create expenses
            $expenseTypes = [
                ['type' => 'advertising', 'ar' => 'إعلانات', 'en' => 'Advertising'],
                ['type' => 'subscription', 'ar' => 'اشتراك', 'en' => 'Subscription'],
                ['type' => 'fees', 'ar' => 'رسوم', 'en' => 'Fees'],
                ['type' => 'other', 'ar' => 'أخرى', 'en' => 'Other'],
            ];

            for ($i = 0; $i < 20; $i++) {
                $expenseType = $faker->randomElement($expenseTypes);
                Expense::create([
                    'merchant_id' => $merchant->id,
                    'expense_type' => $expenseType['type'],
                    'expense_type_ar' => $expenseType['ar'],
                    'expense_type_en' => $expenseType['en'],
                    'category' => $faker->optional(0.6)->word(),
                    'category_ar' => $faker->optional(0.6)->word(),
                    'category_en' => $faker->optional(0.6)->word(),
                    'amount' => $faker->randomFloat(2, 50, 2000),
                    'description' => $faker->sentence(),
                    'description_ar' => $faker->optional(0.7)->realText(100),
                    'description_en' => $faker->optional(0.7)->text(100),
                    'expense_date' => $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                    'receipt_url' => $faker->optional(0.5)->url(),
                    'metadata' => [
                        'note' => $faker->optional(0.3)->sentence(),
                    ],
                ]);
            }

            // Create withdrawals
            for ($i = 0; $i < 10; $i++) {
                $status = $faker->randomElement(['pending', 'approved', 'rejected', 'completed']);
                Withdrawal::create([
                    'merchant_id' => $merchant->id,
                    'amount' => $faker->randomFloat(2, 100, 5000),
                    'withdrawal_method' => $faker->randomElement(['bank_transfer', 'paypal']),
                    'account_details' => $faker->optional(0.8) ? json_encode([
                        'bank_name' => $faker->company(),
                        'account_number' => $faker->bankAccountNumber(),
                        'iban' => $faker->iban(),
                    ]) : null,
                    'status' => $status,
                    'approved_by' => $status === 'approved' || $status === 'completed' ? $faker->optional(0.7)->numberBetween(1, 10) : null,
                    'approved_at' => $status === 'approved' || $status === 'completed' ? $faker->dateTimeBetween('-2 months', 'now') : null,
                    'completed_at' => $status === 'completed' ? $faker->dateTimeBetween('-1 month', 'now') : null,
                    'rejection_reason' => $status === 'rejected' ? $faker->sentence() : null,
                    'admin_notes' => $faker->optional(0.3)->sentence(),
                    'requested_at' => $faker->dateTimeBetween('-3 months', 'now'),
                ]);
            }
        }
    }
}
