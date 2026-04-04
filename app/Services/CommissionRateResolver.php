<?php

namespace App\Services;

use App\Models\Merchant;

/**
 * Resolves the platform commission rate (0..1) for a merchant order.
 * Modes: platform (default setting), custom (%), waived (0%).
 */
class CommissionRateResolver
{
    public const MODE_PLATFORM = 'platform';

    public const MODE_CUSTOM = 'custom';

    public const MODE_WAIVED = 'waived';

    public static function effectiveDecimalRate(?Merchant $merchant): float
    {
        if (! $merchant) {
            return FeatureFlagService::getCommissionRate();
        }

        $mode = $merchant->commission_mode ?? self::MODE_PLATFORM;

        if ($mode === self::MODE_WAIVED) {
            return 0.0;
        }

        if ($mode === self::MODE_CUSTOM && $merchant->commission_custom_percent !== null) {
            $p = (float) $merchant->commission_custom_percent;

            return min(1.0, max(0.0, $p / 100));
        }

        return FeatureFlagService::getCommissionRate();
    }

    /**
     * Human-readable percent (e.g. 10.5) for APIs and admin UI.
     */
    public static function effectivePercentDisplay(?Merchant $merchant): float
    {
        return round(self::effectiveDecimalRate($merchant) * 100, 2);
    }
}
