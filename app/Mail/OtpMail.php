<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public string $otp;
    public string $language;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $otp, string $language = 'ar')
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->language = $language;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->language === 'ar' 
            ? 'رمز التحقق - OFROO' 
            : 'Verification Code - OFROO';

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
            ? 'emails.otp-ar' 
            : 'emails.otp-en';

        return new Content(
            view: $view,
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
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
        return [];
    }
}
