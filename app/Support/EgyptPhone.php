<?php

namespace App\Support;

/**
 * Normalizes Egyptian mobile numbers to a single canonical form (+20…) for storage and lookup.
 * Mobile clients often send numbers as JSON integers or local 01… forms.
 */
final class EgyptPhone
{
    public static function normalize(mixed $input): string
    {
        if ($input === null) {
            return '';
        }

        if (is_int($input) || is_float($input)) {
            $input = (string) (int) $input;
        }

        $s = trim((string) $input);
        if ($s === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $s) ?? '';

        if ($digits === '') {
            return $s;
        }

        if (str_starts_with($digits, '20') && strlen($digits) >= 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            return '+20'.substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '+20'.$digits;
        }

        return $s;
    }
}
