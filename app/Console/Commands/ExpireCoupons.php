<?php

namespace App\Console\Commands;

use App\Services\OfferService;
use Illuminate\Console\Command;

class ExpireCoupons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coupons:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire coupons and offers that have passed their end date';

    /**
     * Execute the console command.
     */
    public function handle(OfferService $offerService): int
    {
        $this->info('Starting offer and coupon expiration process...');

        $expiredCount = $offerService->expireOffers();

        $this->info("Expired {$expiredCount} offers and their related coupons.");

        $this->info('Expiration process completed!');

        return Command::SUCCESS;
    }
}
