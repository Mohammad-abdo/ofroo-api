<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    /**
     * Generate coupon PDF
     */
    public function generateCouponPdf(Coupon $coupon): string
    {
        $user = $coupon->user;
        $language = $user->language ?? 'ar';

        $data = [
            'coupon' => $coupon,
            'offer' => $coupon->offer,
            'merchant' => $coupon->offer->merchant,
            'language' => $language,
        ];

        $pdf = Pdf::loadView('pdfs.coupon', $data);
        
        $filename = 'coupon_' . $coupon->coupon_code . '.pdf';
        $path = 'coupons/' . $filename;
        
        Storage::disk('public')->put($path, $pdf->output());
        
        return Storage::url($path);
    }

    /**
     * Generate order PDF with all coupons
     */
    public function generateOrderPdf(Order $order): string
    {
        $user = $order->user;
        $language = $user->language ?? 'ar';

        $data = [
            'order' => $order,
            'coupons' => $order->coupons,
            'language' => $language,
        ];

        $pdf = Pdf::loadView('pdfs.order', $data);
        
        $filename = 'order_' . $order->id . '.pdf';
        $path = 'orders/' . $filename;
        
        Storage::disk('public')->put($path, $pdf->output());
        
        return Storage::url($path);
    }

}

