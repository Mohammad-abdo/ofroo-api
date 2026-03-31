<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class PaymentProcessingService
{
    public function __construct(
        protected CouponService $couponService,
        protected WalletService $walletService,
        protected LoyaltyService $loyaltyService,
        protected InvoiceService $invoiceService
    ) {}

    public function processPayment(User $user, Offer $offer, int $quantity, string $paymentMethod): array
    {
        $quantity = max(1, min($quantity, 10));
        $unitPrice = $offer->price;
        $totalAmount = $unitPrice * $quantity;

        $result = match ($paymentMethod) {
            'wallet' => $this->processWalletPayment($user, $totalAmount),
            'card', 'Fawry', 'knet', 'apple_pay' => $this->processGatewayPayment($user, $offer, $quantity, $paymentMethod),
            default => ['success' => false, 'message' => 'Invalid payment method'],
        };

        return $result;
    }

    protected function processWalletPayment(User $user, float $amount): array
    {
        $balance = $this->walletService->getBalance($user);

        if ($balance < $amount) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance',
                'required' => $amount,
                'available' => $balance,
            ];
        }

        return [
            'success' => true,
            'payment_method' => 'wallet',
            'transaction_id' => null,
        ];
    }

    protected function processGatewayPayment(User $user, Offer $offer, int $quantity, string $gateway): array
    {
        return [
            'success' => true,
            'payment_method' => $gateway,
            'transaction_id' => 'GW_' . time() . '_' . rand(1000, 9999),
        ];
    }

    public function createOrder(User $user, Offer $offer, int $quantity, string $paymentMethod, ?string $transactionId = null): Order
    {
        return DB::transaction(function () use ($user, $offer, $quantity, $paymentMethod, $transactionId) {
            $totalAmount = $offer->price * $quantity;

            $order = Order::create([
                'user_id' => $user->id,
                'merchant_id' => $offer->merchant_id,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'status' => 'completed',
                'transaction_id' => $transactionId,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'offer_id' => $offer->id,
                'quantity' => $quantity,
                'unit_price' => $offer->price,
                'total_price' => $totalAmount,
            ]);

            $this->couponService->createCouponsForOrder($order, $offer, $quantity);

            if ($paymentMethod === 'wallet') {
                $this->walletService->deduct($user, $totalAmount, 'purchase', $order->id);
            }

            $this->loyaltyService->awardPoints($user, $totalAmount);

            try {
                $this->invoiceService->generateForOrder($order);
            } catch (Exception $e) {
                // Log but don't fail the order
                \Log::warning('Failed to generate invoice for order ' . $order->id . ': ' . $e->getMessage());
            }

            return $order;
        });
    }

    public function refund(Order $order): bool
    {
        if ($order->payment_status !== 'paid') {
            return false;
        }

        return DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'cancelled',
                'payment_status' => 'refunded',
            ]);

            if ($order->payment_method === 'wallet') {
                $this->walletService->refund($order->user, $order->total_amount, $order->id);
            }

            foreach ($order->coupons as $coupon) {
                $coupon->update(['status' => 'cancelled']);
            }

            $this->loyaltyService->revokePoints($order->user, $order->total_amount);

            return true;
        });
    }
}
