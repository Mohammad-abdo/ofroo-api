<?php

namespace App\Services;

use App\Models\PaymentGateway;
use App\Models\Payment;
use App\Models\Order;

class PaymentGatewayService
{
    /**
     * Process payment
     */
    public function processPayment(?Order $order, string $gatewayName, array $paymentData): Payment
    {
        $gateway = PaymentGateway::where('name', $gatewayName)
            ->where('is_active', true)
            ->firstOrFail();

        $amount = $order ? $order->total_amount : ($paymentData['amount'] ?? 0);

        // Create payment record (order_id will be set later if order is created)
        $payment = Payment::create([
            'order_id' => $order?->id,
            'amount' => $amount,
            'gateway' => $gatewayName,
            'status' => 'pending',
            'transaction_id' => $paymentData['transaction_id'] ?? null,
            'metadata' => $paymentData,
        ]);

        // Process based on gateway
        switch ($gatewayName) {
            case 'knet':
                return $this->processKNET($payment, $paymentData);
            case 'visa':
            case 'mastercard':
                return $this->processCard($payment, $paymentData);
            case 'apple_pay':
            case 'google_pay':
                return $this->processMobilePay($payment, $paymentData);
            default:
                throw new \Exception("Unsupported gateway: {$gatewayName}");
        }
    }

    /**
     * Update payment with order ID
     */
    public function linkPaymentToOrder(Payment $payment, Order $order): void
    {
        $payment->update(['order_id' => $order->id]);
    }

    /**
     * Process KNET payment
     */
    protected function processKNET(Payment $payment, array $data): Payment
    {
        // KNET integration logic here
        // This is a placeholder - implement actual KNET API integration
        
        $payment->update([
            'status' => 'success',
            'transaction_id' => $data['transaction_id'] ?? 'KNET-' . time(),
        ]);

        return $payment;
    }

    /**
     * Process card payment
     */
    protected function processCard(Payment $payment, array $data): Payment
    {
        // Card payment integration logic here
        // This is a placeholder - implement actual payment gateway integration
        
        $payment->update([
            'status' => 'success',
            'transaction_id' => $data['transaction_id'] ?? 'CARD-' . time(),
        ]);

        return $payment;
    }

    /**
     * Process mobile payment
     */
    protected function processMobilePay(Payment $payment, array $data): Payment
    {
        // Mobile payment integration logic here
        
        $payment->update([
            'status' => 'success',
            'transaction_id' => $data['transaction_id'] ?? 'MOBILE-' . time(),
        ]);

        return $payment;
    }
}

