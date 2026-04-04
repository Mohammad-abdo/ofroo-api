<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Models\Offer;
use Carbon\Carbon;
use Tests\TestCase;

class OfferCouponExpiryTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_offer_expired_by_end_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));
        $o = new Offer([
            'status' => 'active',
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-05-01'),
        ]);
        $this->assertTrue($o->isExpired());
        $this->assertSame('expired', $o->effectiveStatus());
    }

    public function test_coupon_expired_by_status(): void
    {
        $c = new Coupon([
            'status' => 'expired',
            'expires_at' => Carbon::parse('2030-01-01'),
        ]);
        $this->assertTrue($c->isExpired());
    }
}
