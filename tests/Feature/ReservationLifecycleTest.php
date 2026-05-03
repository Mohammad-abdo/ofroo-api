<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Coupon;
use App\Models\CouponEntitlement;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\QrActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage of the reservation + QR-activation refactor.
 *
 * The HTTP endpoints (/api/orders/checkout, /api/orders/checkout/coupons,
 * /api/qr/scan) are covered separately by their existing tests. This file
 * targets the internal lifecycle directly to keep assertions tight on the
 * three contractual rules:
 *
 *   1. coupons_remaining stays the primary mobile-facing source of truth.
 *   2. reserved_quantity / used_quantity are accounted for accurately.
 *   3. Wallet credit + commission run exactly once per order.
 */
class ReservationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Merchant $merchant;

    private User $merchantUser;

    private Offer $offer;

    private Coupon $couponTemplate;

    private QrActivationService $qrService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Customer',
            'email' => 'customer@example.test',
            'phone' => '+20100000001',
            'password' => Hash::make('password'),
        ]);

        $this->merchantUser = User::create([
            'name' => 'Merchant Owner',
            'email' => 'owner@example.test',
            'phone' => '+20100000002',
            'password' => Hash::make('password'),
        ]);

        $this->merchant = Merchant::create([
            'user_id' => $this->merchantUser->id,
            'company_name' => 'Test Merchant',
            'approved' => true,
        ]);

        $this->offer = Offer::create([
            'merchant_id' => $this->merchant->id,
            'title' => 'Test Offer',
            'title_en' => 'Test Offer',
            'price' => 50.00,
            'discount' => 10,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'status' => 'active',
        ]);
        // total_coupons / coupons_remaining live on the offers table but
        // aren't in the model fillable, so set them with a direct update.
        \DB::table('offers')->where('id', $this->offer->id)->update([
            'total_coupons' => 10,
            'coupons_remaining' => 10,
        ]);
        $this->offer->refresh();

        $this->couponTemplate = Coupon::create([
            'offer_id' => $this->offer->id,
            'title' => 'Test Coupon',
            'price' => 50.00,
            'discount' => 10,
            'discount_type' => 'percentage',
            'barcode' => 'BC-'.Str::upper(Str::random(8)),
            'coupon_code' => 'CC-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'usage_limit' => 100,
            'times_used' => 0,
            'expires_at' => now()->addMonth(),
        ]);

        $this->qrService = app(QrActivationService::class);
    }

    public function test_consume_coupons_throws_logic_exception_by_default(): void
    {
        $this->expectException(\LogicException::class);
        $this->offer->consumeCoupons(1);
    }

    public function test_offer_reserve_helper_decrements_coupons_remaining_and_increments_reserved_quantity(): void
    {
        $this->offer->reserve(3);

        $this->offer->refresh();
        $this->assertSame(7, (int) $this->offer->coupons_remaining);
        $this->assertSame(3, (int) $this->offer->reserved_quantity);
        $this->assertSame(0, (int) $this->offer->used_quantity);
    }

    public function test_offer_consume_reserved_does_not_touch_coupons_remaining(): void
    {
        $this->offer->reserve(2);
        $beforeRemaining = (int) $this->offer->fresh()->coupons_remaining;

        $this->offer->consumeReserved(2);

        $fresh = $this->offer->fresh();
        $this->assertSame($beforeRemaining, (int) $fresh->coupons_remaining, 'coupons_remaining MUST stay unchanged at QR activation');
        $this->assertSame(0, (int) $fresh->reserved_quantity);
        $this->assertSame(2, (int) $fresh->used_quantity);
    }

    public function test_offer_release_reservation_restores_coupons_remaining(): void
    {
        $this->offer->reserve(4);
        $this->assertSame(6, (int) $this->offer->fresh()->coupons_remaining);

        $this->offer->releaseReservation(4);

        $fresh = $this->offer->fresh();
        $this->assertSame(10, (int) $fresh->coupons_remaining);
        $this->assertSame(0, (int) $fresh->reserved_quantity);
        $this->assertSame(0, (int) $fresh->used_quantity);
    }

    public function test_qr_scan_finalizes_pending_order_idempotently(): void
    {
        $order = $this->seedPendingOrder(quantity: 2, paymentMethod: 'cash', paymentStatus: 'pending');
        $entitlement = $this->seedEntitlement($order, status: 'pending');

        $beforeRemaining = (int) $this->offer->fresh()->coupons_remaining;

        $first = $this->qrService->activateCoupon(
            (string) $entitlement->redeem_token,
            $this->merchant,
            $this->merchantUser,
            ['activation_method' => 'qr_scan']
        );

        $this->assertTrue($first['success'] ?? false, 'first QR scan must succeed');

        $order->refresh();
        $offer = $this->offer->fresh();

        $this->assertSame('activated', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->wallet_processed_at);
        $this->assertNotNull($order->activated_at);

        // Inventory contract: coupons_remaining MUST NOT change at scan.
        $this->assertSame($beforeRemaining, (int) $offer->coupons_remaining, 'coupons_remaining must stay frozen at QR scan');
        $this->assertSame(0, (int) $offer->reserved_quantity);
        $this->assertSame(2, (int) $offer->used_quantity);

        // Wallet + commission ran once.
        $this->assertSame(1, Commission::where('order_id', $order->id)->count());
        $merchantWallet = MerchantWallet::where('merchant_id', $this->merchant->id)->first();
        $this->assertNotNull($merchantWallet);
        $this->assertGreaterThan(0, (float) $merchantWallet->balance);

        // Second scan attempt must NOT re-credit.
        $balanceAfterFirst = (float) $merchantWallet->balance;

        $second = $this->qrService->activateCoupon(
            (string) $entitlement->redeem_token,
            $this->merchant,
            $this->merchantUser,
            ['activation_method' => 'qr_scan']
        );

        // Second scan may either succeed (consuming another use) or fail
        // (entitlement exhausted) — the key invariant is no double-credit.
        $this->assertSame(1, Commission::where('order_id', $order->id)->count(), 'wallet/commission must NOT be processed twice');
        $this->assertSame($balanceAfterFirst, (float) $merchantWallet->fresh()->balance);

        // Inventory must not move on subsequent scans either.
        $offer = $this->offer->fresh();
        $this->assertSame(0, (int) $offer->reserved_quantity);
        $this->assertSame(2, (int) $offer->used_quantity);
        $this->assertSame($beforeRemaining, (int) $offer->coupons_remaining);
    }

    public function test_qr_scan_does_not_double_credit_already_paid_card_order(): void
    {
        // Card-prepaid order: payment_status='paid' and wallet was already
        // processed at checkout (simulated here by setting wallet_processed_at).
        $order = $this->seedPendingOrder(quantity: 1, paymentMethod: 'card', paymentStatus: 'paid');
        $order->forceFill(['wallet_processed_at' => now()->subMinute()])->save();
        $entitlement = $this->seedEntitlement($order, status: 'active');

        // No commission row yet (the test simulates the flag without invoking
        // the wallet service); subsequent scan should still not run wallet
        // because wallet_processed_at is already set.
        $beforeWalletCount = Commission::count();

        $result = $this->qrService->activateCoupon(
            (string) $entitlement->redeem_token,
            $this->merchant,
            $this->merchantUser,
            ['activation_method' => 'qr_scan']
        );

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame($beforeWalletCount, Commission::count(), 'wallet/commission must not run when wallet_processed_at is already set');

        $order->refresh();
        $this->assertSame('activated', $order->status);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_expire_command_releases_reservation_and_restores_coupons_remaining(): void
    {
        $order = $this->seedPendingOrder(quantity: 3, paymentMethod: 'cash', paymentStatus: 'pending');
        // Force the reservation window into the past.
        $order->forceFill(['reservation_expires_at' => now()->subMinute()])->save();
        $entitlement = $this->seedEntitlement($order, status: 'pending');

        $beforeRemaining = (int) $this->offer->fresh()->coupons_remaining; // 7 (10 - 3 reserved)
        $this->assertSame(7, $beforeRemaining);
        $this->assertSame(3, (int) $this->offer->fresh()->reserved_quantity);

        $this->artisan('orders:expire-reservations')->assertExitCode(0);

        $order->refresh();
        $offer = $this->offer->fresh();
        $entitlement->refresh();

        $this->assertSame('expired', $order->status);
        $this->assertSame(10, (int) $offer->coupons_remaining, 'coupons_remaining must be fully restored on expiry');
        $this->assertSame(0, (int) $offer->reserved_quantity);
        $this->assertSame(0, (int) $offer->used_quantity);
        $this->assertSame('cancelled', $entitlement->status);

        // Wallet must NOT have been touched on expiry.
        $this->assertSame(0, Commission::where('order_id', $order->id)->count());
    }

    public function test_expire_command_skips_orders_still_inside_reservation_window(): void
    {
        $order = $this->seedPendingOrder(quantity: 1, paymentMethod: 'cash', paymentStatus: 'pending');
        // Window still in the future.
        $order->forceFill(['reservation_expires_at' => now()->addHour()])->save();

        $this->artisan('orders:expire-reservations')->assertExitCode(0);

        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertSame(9, (int) $this->offer->fresh()->coupons_remaining);
        $this->assertSame(1, (int) $this->offer->fresh()->reserved_quantity);
    }

    /**
     * Helper: create a pending order with one OrderItem, reserving inventory
     * the same way OrderController::checkout does.
     */
    private function seedPendingOrder(int $quantity, string $paymentMethod, string $paymentStatus): Order
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'merchant_id' => $this->merchant->id,
            'total_amount' => $this->offer->price * $quantity,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'status' => 'pending',
            'reservation_expires_at' => now()->addDay(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'offer_id' => $this->offer->id,
            'quantity' => $quantity,
            'unit_price' => $this->offer->price,
            'total_price' => $this->offer->price * $quantity,
        ]);

        $this->offer->reserve($quantity);

        return $order->fresh();
    }

    private function seedEntitlement(Order $order, string $status): CouponEntitlement
    {
        $orderItem = $order->items()->first();

        return CouponEntitlement::create([
            'user_id' => $this->customer->id,
            'coupon_id' => $this->couponTemplate->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem?->id,
            'usage_limit' => $orderItem ? (int) $orderItem->quantity : 1,
            'times_used' => 0,
            'reserved_shares_count' => 0,
            'status' => $status,
            'redeem_token' => 'W-'.Str::upper(Str::random(20)),
        ]);
    }
}
