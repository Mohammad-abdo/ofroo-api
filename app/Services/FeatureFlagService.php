<?php

namespace App\Services;

use App\Models\Setting;

class FeatureFlagService
{
    /**
     * Platform default commission as decimal (e.g. 0.06 for 6%).
     * Accepts DB values stored as fraction (0.06) or legacy whole percent (6).
     */
    public static function getCommissionRate(): float
    {
        $raw = Setting::getValue('commission_rate', 0.10);

        return self::normalizeStoredRate($raw);
    }

    public static function normalizeStoredRate(mixed $raw): float
    {
        if (! is_numeric($raw)) {
            return 0.10;
        }
        $f = (float) $raw;
        if ($f > 1.0) {
            return min(1.0, max(0.0, $f / 100));
        }

        return min(1.0, max(0.0, $f));
    }

    /**
     * Check if GPS feature is enabled
     */
    public static function isGpsEnabled(): bool
    {
        return (bool) Setting::getValue('feature_gps', true);
    }

    /**
     * Check if electronic payments are enabled
     */
    public static function isElectronicPaymentsEnabled(): bool
    {
        return (bool) Setting::getValue('feature_electronic_payments', true);
    }
}
