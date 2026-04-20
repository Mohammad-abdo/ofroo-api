<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles the merchant application lifecycle for the mobile app:
 *  - GET /api/mobile/merchant/application/status  → check approval status
 */
class MerchantApplicationController extends Controller
{
    /**
     * Return the current application status for the authenticated user's merchant.
     *
     * Status values returned:
     *   pending  → merchant row exists, approved = false, status ≠ disabled
     *   accepted → approved = true
     *   rejected → status = disabled (admin explicitly disabled/rejected)
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $merchant = Merchant::where('user_id', $user->id)->first();

        if (! $merchant) {
            return response()->json([
                'message'    => 'لا يوجد طلب تسجيل تاجر مرتبط بهذا الحساب',
                'message_ar' => 'لا يوجد طلب تسجيل تاجر مرتبط بهذا الحساب',
                'message_en' => 'No merchant application found for this account.',
            ], 404);
        }

        if ((bool) $merchant->approved === true) {
            $status    = 'accepted';
            $statusAr  = 'مقبول';
            $messageAr = 'تم قبول طلبك، يمكنك الآن استخدام حساب التاجر.';
            $messageEn = 'Your application has been accepted. You can now use your merchant account.';
        } elseif ($merchant->status === 'disabled') {
            $status    = 'rejected';
            $statusAr  = 'مرفوض';
            $messageAr = 'تم رفض طلبك.';
            $messageEn = 'Your application has been rejected.';
        } else {
            $status    = 'pending';
            $statusAr  = 'قيد المراجعة';
            $messageAr = 'طلبك قيد المراجعة، سيتم إشعارك عند اتخاذ القرار.';
            $messageEn = 'Your application is under review. You will be notified once a decision is made.';
        }

        return response()->json([
            'data' => [
                'status'           => $status,
                'status_ar'        => $statusAr,
                'message'          => $messageAr,
                'message_ar'       => $messageAr,
                'message_en'       => $messageEn,
                'merchant_id'      => $merchant->id,
                'company_name'     => $merchant->company_name ?? $merchant->company_name_ar ?? '',
                'accepted_terms'   => (bool) $merchant->accepted_terms,
                'rejection_reason' => $status === 'rejected' ? ($merchant->rejection_reason ?? null) : null,
                'applied_at'       => $merchant->created_at?->toIso8601String(),
            ],
        ]);
    }
}
