<?php

namespace App\Services\Otp;

class PhoneNormalizer
{
    /**
     * Digits only, no + or spaces (required by Welniz and many SMS gateways).
     */
    public static function digitsOnly(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
