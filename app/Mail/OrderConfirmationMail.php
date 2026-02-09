<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Services\PdfService;

class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;
    public array $coupons;
    public string $language;
    public ?string $pdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order, array $coupons, string $language = 'ar', ?string $pdfPath = null)
    {
        $this->order = $order;
        $this->coupons = $coupons;
        $this->language = $language;
        $this->pdfPath = $pdfPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->language === 'ar' 
            ? 'تأكيد الطلب - OFROO' 
            : 'Order Confirmation - OFROO';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = $this->language === 'ar' 
            ? 'emails.order-confirmation-ar' 
            : 'emails.order-confirmation-en';

        return new Content(
            view: $view,
            with: [
                'order' => $this->order,
                'coupons' => $this->coupons,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->pdfPath) {
            $attachments[] = Attachment::fromPath(storage_path('app/public/' . str_replace('/storage/', '', $this->pdfPath)))
                ->as('order_' . $this->order->id . '.pdf');
        }

        return $attachments;
    }
}
