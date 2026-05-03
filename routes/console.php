<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule tasks
Schedule::command('coupons:expire')->daily();
Schedule::command('coupon-shares:release-expired')->hourly();
// Reservation lifecycle: release inventory and mark orders expired when the
// reservation window has elapsed. Runs frequently so customer-visible state
// becomes consistent within a few minutes of the deadline passing.
Schedule::command('orders:expire-reservations')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('backup:database')->daily()->at('02:00');
