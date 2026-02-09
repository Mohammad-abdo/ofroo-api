<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Offer;
use App\Models\User;

class WhatsappService
{
    /**
     * Generate WhatsApp contact link
     */
    public function generateContactLink(Merchant $merchant, ?Offer $offer = null, ?User $user = null): string
    {
        $phone = $merchant->whatsapp_number ?? $merchant->phone;

        if (!$phone) {
            return '';
        }

        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Build message
        $message = "مرحباً، أريد الاستفسار عن ";

        if ($offer) {
            $message .= "العرض: {$offer->title_ar}";
        } else {
            $message .= "العروض المتاحة";
        }

        if ($user) {
            $message .= "\n\nاسمي: {$user->name}";
            $message .= "\nرقم الهاتف: {$user->phone}";
        }

        // Encode message
        $encodedMessage = urlencode($message);

        // Generate WhatsApp link
        return "https://wa.me/{$phone}?text={$encodedMessage}";
    }

    /**
     * Check if WhatsApp is enabled for merchant
     */
    public function isEnabled(Merchant $merchant): bool
    {
        return $merchant->whatsapp_enabled &&
            ($merchant->whatsapp_number || $merchant->phone);
    }
}