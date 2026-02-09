<?php

namespace App\Services;

use App\Models\Setting;

class FeatureFlagService
{
    /**
     * Get commission rate
     */
    public static function getCommissionRate(): float
    {
        return (float) Setting::getValue('commission_rate', 0.10); // Default 10%
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
