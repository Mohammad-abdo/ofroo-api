<?php

namespace Tests\Unit;

use App\Models\Merchant;
use App\Services\CommissionRateResolver;
use App\Services\FeatureFlagService;
use PHPUnit\Framework\TestCase;

class CommissionRateResolverTest extends TestCase
{
    public function test_waived_merchant_returns_zero(): void
    {
        $m = new Merchant([
            'commission_mode' => CommissionRateResolver::MODE_WAIVED,
            'commission_custom_percent' => null,
        ]);

        $this->assertSame(0.0, CommissionRateResolver::effectiveDecimalRate($m));
    }

    public function test_custom_percent_as_fraction(): void
    {
        $m = new Merchant([
            'commission_mode' => CommissionRateResolver::MODE_CUSTOM,
            'commission_custom_percent' => 7.5,
        ]);

        $this->assertEqualsWithDelta(0.075, CommissionRateResolver::effectiveDecimalRate($m), 0.0001);
    }

    public function test_platform_normalizes_high_setting_value(): void
    {
        $this->assertEqualsWithDelta(0.06, FeatureFlagService::normalizeStoredRate(6), 0.0001);
        $this->assertEqualsWithDelta(0.06, FeatureFlagService::normalizeStoredRate(0.06), 0.0001);
    }
}
