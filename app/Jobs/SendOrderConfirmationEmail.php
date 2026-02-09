<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Services\PdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public Order $order;
    public array $coupons;
    public string $language;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order, array $coupons, string $language = 'ar')
    {
        $this->order = $order;
        $this->coupons = $coupons;
        $this->language = $language;
    }

    /**
     * Execute the job.
     */
    public function handle(PdfService $pdfService): void
    {
        // Generate PDF
        $pdfPath = $pdfService->generateOrderPdf($this->order);

        // Send email with PDF attachment
        Mail::to($this->order->user->email)->send(
            new OrderConfirmationMail($this->order, $this->coupons, $this->language, $pdfPath)
        );
    }
}
