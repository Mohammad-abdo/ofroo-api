<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Coupon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class EmailService
{
    /**
     * Send OTP email
     */
    public function sendOtp(User $user, string $otp): void
    {
        $language = $user->language ?? 'ar';
        
        // TODO: Create OTP email template
        // Mail::to($user->email)->queue(new OtpEmail($user, $otp, $language));
    }

    /**
     * Send order confirmation email with coupons
     */
    public function sendOrderConfirmation(Order $order, array $coupons): void
    {
        $user = $order->user;
        $language = $user->language ?? 'ar';
        
        // TODO: Create order confirmation email template with PDF attachment
        // Mail::to($user->email)->queue(new OrderConfirmationEmail($order, $coupons, $language));
    }

    /**
     * Send coupon activated notification
     */
    public function sendCouponActivated(Coupon $coupon): void
    {
        $user = $coupon->user;
        if (!$user) return;
        
        $language = $user->language ?? 'ar';
        
        // TODO: Create coupon activated email template
        // Mail::to($user->email)->queue(new CouponActivatedEmail($coupon, $language));
    }

    /**
     * Send review request email
     */
    public function sendReviewRequest(Order $order): void
    {
        $user = $order->user;
        $language = $user->language ?? 'ar';
        
        // TODO: Create review request email template
        // Mail::to($user->email)->queue(new ReviewRequestEmail($order, $language));
    }

    /**
     * Send merchant approval notification
     */
    public function sendMerchantApproval(\App\Models\Merchant $merchant): void
    {
        $user = $merchant->user;
        $language = $user->language ?? 'ar';
        
        // TODO: Create merchant approval email template
        // Mail::to($user->email)->queue(new MerchantApprovalEmail($merchant, $language));
    }
}


