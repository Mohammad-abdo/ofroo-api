<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Log;

/**
 * In-app admin dashboard alerts (rows in admin_notifications).
 * These are separate from FCM / user push notifications.
 */
class AdminAlertService
{
    public function offerPendingReview(Offer $offer, Merchant $merchant): void
    {
        try {
            $merchantName = $merchant->company_name ?? $merchant->name ?? ('#'.$merchant->id);
            $offerTitle = $offer->title ?? $offer->title_ar ?? $offer->title_en ?? ('#'.$offer->id);

            AdminNotification::create([
                'title' => 'New offer pending review',
                'title_ar' => 'عرض جديد بانتظار المراجعة',
                'title_en' => 'New offer pending review',
                'message' => "Merchant «{$merchantName}» submitted offer «{$offerTitle}» for review.",
                'message_ar' => "قدّم التاجر «{$merchantName}» عرضاً «{$offerTitle}» للمراجعة.",
                'message_en' => "Merchant «{$merchantName}» submitted offer «{$offerTitle}» for review.",
                'type' => 'warning',
                'target_audience' => 'admins',
                'action_url' => '/admin/offers/'.$offer->id,
                'action_text' => 'Open offer',
                'is_sent' => true,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AdminAlertService::offerPendingReview failed', [
                'offer_id' => $offer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function couponCreatedByMerchant(Coupon $coupon, Merchant $merchant): void
    {
        try {
            $merchantName = $merchant->company_name ?? $merchant->name ?? ('#'.$merchant->id);
            $label = $coupon->title ?? $coupon->title_ar ?? $coupon->coupon_code ?? ('#'.$coupon->id);
            $code = $coupon->coupon_code ? " ({$coupon->coupon_code})" : '';

            AdminNotification::create([
                'title' => 'New coupon from merchant',
                'title_ar' => 'كوبون جديد من تاجر',
                'title_en' => 'New coupon from merchant',
                'message' => "Merchant «{$merchantName}» created coupon «{$label}»{$code}.",
                'message_ar' => "أنشأ التاجر «{$merchantName}» كوبون «{$label}»{$code}.",
                'message_en' => "Merchant «{$merchantName}» created coupon «{$label}»{$code}.",
                'type' => 'info',
                'target_audience' => 'admins',
                'action_url' => '/admin/coupons/'.$coupon->id,
                'action_text' => 'Open coupon',
                'is_sent' => true,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AdminAlertService::couponCreatedByMerchant failed', [
                'coupon_id' => $coupon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function merchantWithdrawalRequested(Withdrawal $withdrawal, Merchant $merchant): void
    {
        try {
            $merchantName = $merchant->company_name ?? $merchant->name ?? ('#'.$merchant->id);
            $amount = number_format((float) $withdrawal->amount, 2);

            AdminNotification::create([
                'title' => 'Withdrawal request',
                'title_ar' => 'طلب سحب من تاجر',
                'title_en' => 'Withdrawal request',
                'message' => "Merchant «{$merchantName}» requested withdrawal of {$amount} EGP (request #{$withdrawal->id}).",
                'message_ar' => "قدّم التاجر «{$merchantName}» طلب سحب بقيمة {$amount} جنيه (طلب رقم {$withdrawal->id}).",
                'message_en' => "Merchant «{$merchantName}» requested withdrawal of {$amount} EGP (request #{$withdrawal->id}).",
                'type' => 'warning',
                'target_audience' => 'admins',
                'action_url' => '/admin/withdrawals',
                'action_text' => 'Withdrawals',
                'is_sent' => true,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AdminAlertService::merchantWithdrawalRequested failed', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
