<?php

namespace App\Console\Commands;

use App\Services\CouponService;
use Illuminate\Console\Command;

class ReleaseExpiredCouponSharesCommand extends Command
{
    protected $signature = 'coupon-shares:release-expired';

    protected $description = 'Expire pending friend-share tokens past expires_at and release reserved slots';

    public function handle(CouponService $couponService): int
    {
        $n = $couponService->releaseExpiredShares();
        $this->info("Released {$n} expired share(s).");

        return self::SUCCESS;
    }
}
