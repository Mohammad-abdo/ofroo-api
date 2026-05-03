<?php

namespace App\Console\Commands;

use App\Models\CouponEntitlement;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Release stale reservations created by checkout / checkoutCoupons.
 *
 * For every order in `status=pending` whose `reservation_expires_at` is in the
 * past, this command:
 *   - Restores inventory: coupons_remaining += qty, reserved_quantity -= qty
 *     (used_quantity is NEVER touched here.)
 *   - Cancels any pending CouponEntitlement rows tied to the order.
 *   - Marks the order as `status=expired` (payment_status is left untouched
 *     so the legacy mobile API contract is preserved exactly).
 *
 * Wallet/commission are NOT reversed here — by design, the reservation flow
 * never credits them at checkout, so there's nothing to roll back. Card
 * prepaid orders that would have already credited at checkout are out of
 * scope of the cash-based reservation expiry path.
 */
class ExpireOrderReservations extends Command
{
    protected $signature = 'orders:expire-reservations {--dry-run : List candidates without applying changes}';

    protected $description = 'Release inventory and mark orders as expired when their reservation window has elapsed.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $totalCandidates = 0;
        $expiredCount = 0;

        Order::query()
            ->where('status', 'pending')
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<', $now)
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($candidates) use (&$totalCandidates, &$expiredCount, $dryRun) {
                foreach ($candidates as $row) {
                    $totalCandidates++;

                    if ($dryRun) {
                        $this->line("[dry-run] would expire order #{$row->id}");

                        continue;
                    }

                    try {
                        $changed = $this->expireOne((int) $row->id);
                        if ($changed) {
                            $expiredCount++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('orders:expire-reservations failed for one order', [
                            'order_id' => $row->id,
                            'error' => $e->getMessage(),
                        ]);
                        $this->error("Order #{$row->id} failed: {$e->getMessage()}");
                    }
                }
            });

        $this->info(sprintf(
            '%s %d candidate(s); %d expired.',
            $dryRun ? 'Inspected' : 'Processed',
            $totalCandidates,
            $expiredCount
        ));

        return self::SUCCESS;
    }

    /**
     * Process a single order. Re-reads with row-level lock so we can't race
     * with a concurrent QR scan that's mid-activation.
     */
    protected function expireOne(int $orderId): bool
    {
        return (bool) DB::transaction(function () use ($orderId) {
            $order = Order::with('items.offer')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order || $order->status !== 'pending') {
                return false;
            }
            if (! $order->reservation_expires_at || ! $order->reservation_expires_at->isPast()) {
                return false;
            }

            foreach ($order->items as $item) {
                if ($item->offer) {
                    $item->offer->releaseReservation((int) $item->quantity);
                }
            }

            CouponEntitlement::where('order_id', $order->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $order->markExpired();

            return true;
        });
    }
}
