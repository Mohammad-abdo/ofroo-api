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

    /**
     * Digits as gateways expect (often E.164 without +). Egypt local 01xxxxxxxxx → 20…
     */
    public static function digitsForSmsGateway(?string $phone): string
    {
        $digits = self::digitsOnly($phone);
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '20') && strlen($digits) >= 12) {
            return $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '20'.substr($digits, 1);
        }

        return $digits;
    }
}
