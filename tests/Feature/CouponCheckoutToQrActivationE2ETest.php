<?php

namespace Tests\Feature;

use App\Models\ActivationReport;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\CouponEntitlement;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * دورة كاملة: حجز أكثر من كوبون عبر checkout بالكوبونات → إشعار للمستخدم →
 * مسح QR لكل entitlement → تفعيل الطلب → رصيد تاجر (محفظة) + عمولة أدمن (محفظة + سجل commission).
 *
 * ملاحظة: مسار الطلب الحالي يرسل إشعار In-App للمستخدم فقط عند إنشاء الطلب؛
 * لا يوجد إرسال تلقائي لصندوق إشعارات التاجر أو لوحة admin_notifications عند التفعيل.
 */
class CouponCheckoutToQrActivationE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $merchantUser;

    private Merchant $merchant;

    private Offer $offer;

    private Coupon $couponA;

    private Coupon $couponB;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $userRole = Role::create([
            'name' => 'user',
            'name_ar' => 'مستخدم',
            'name_en' => 'User',
        ]);
        $merchantRole = Role::create([
            'name' => 'merchant',
            'name_ar' => 'تاجر',
            'name_en' => 'Merchant',
        ]);

        $this->customer = User::create([
            'name' => 'Buyer',
            'email' => 'buyer@example.test',
            'phone' => '+20100000011',
            'password' => bcrypt('secret'),
            'role_id' => $userRole->id,
        ]);

        $this->merchantUser = User::create([
            'name' => 'Shop Owner',
            'email' => 'owner@example.test',
            'phone' => '+20100000012',
            'password' => bcrypt('secret'),
            'role_id' => $merchantRole->id,
        ]);

        $this->merchant = Merchant::create([
            'user_id' => $this->merchantUser->id,
            'company_name' => 'E2E Merchant',
            'approved' => true,
        ]);

        $category = Category::create([
            'name_ar' => 'تصنيف',
            'name_en' => 'Category',
        ]);

        $branch = Branch::create([
            'merchant_id' => $this->merchant->id,
            'lat' => 30.0,
            'lng' => 31.0,
            'address' => 'Branch',
        ]);

        $this->offer = Offer::create([
            'merchant_id' => $this->merchant->id,
            'category_id' => $category->id,
            'title' => 'عرض E2E',
            'title_en' => 'E2E Offer',
            'price' => 40.00,
            'discount' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'status' => 'active',
        ]);
        $this->offer->branches()->sync([$branch->id]);

        DB::table('offers')->where('id', $this->offer->id)->update([
            'total_coupons' => 20,
            'coupons_remaining' => 20,
            'reserved_quantity' => 0,
            'used_quantity' => 0,
        ]);
        $this->offer->refresh();

        $mkCoupon = fn (string $suffix) => Coupon::create([
            'offer_id' => $this->offer->id,
            'coupon_setting_id' => 1,
            'title' => 'Coupon '.$suffix,
            'price' => 40.00,
            'discount' => 0,
            'discount_type' => 'percentage',
            'barcode' => 'BC-'.$suffix.Str::upper(Str::random(6)),
            'coupon_code' => 'CC-'.$suffix.Str::upper(Str::random(6)),
            'status' => 'active',
            'usage_limit' => 100,
            'times_used' => 0,
            'expires_at' => now()->addMonth(),
        ]);

        $this->couponA = $mkCoupon('A');
        $this->couponB = $mkCoupon('B');
    }

    public function test_mobile_checkout_two_coupons_then_two_scans_wallet_and_commission_once(): void
    {
        $notifBefore = $this->customer->notifications()->count();

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/mobile/orders/checkout/coupons', [
                'user_id' => $this->customer->id,
                'coupon_ids' => [$this->couponA->id, $this->couponB->id],
                'payment_method' => 'cash',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.order.merchant_id', $this->merchant->id);

        $orderId = (int) $response->json('data.order.id');
        $this->assertGreaterThan(0, $orderId);

        $order = Order::with(['items', 'couponEntitlements'])->findOrFail($orderId);
        $this->assertCount(2, $order->items);
        $this->assertCount(2, $order->couponEntitlements);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);

        foreach ($order->couponEntitlements as $ent) {
            $this->assertSame('pending', $ent->status);
            $this->assertNotEmpty($ent->redeem_token);
        }

        $this->offer->refresh();
        $this->assertSame(18, (int) $this->offer->coupons_remaining);
        $this->assertSame(2, (int) $this->offer->reserved_quantity);
        $this->assertSame(0, (int) $this->offer->used_quantity);

        // إشعار داخل التطبيق للمستخدم (سجل notifications)
        $this->assertSame($notifBefore + 1, $this->customer->fresh()->notifications()->count());
        $last = $this->customer->fresh()->notifications()->latest()->first();
        $this->assertSame('order', $last->type);
        $this->assertSame($orderId, (int) ($last->data['order_id'] ?? 0));

        // لا يوجد في الكود الحالي إشعار In-App للتاجر عند إنشاء الطلب بهذا المسار
        $this->assertSame(0, $this->merchantUser->fresh()->notifications()->count());

        $tokens = $order->couponEntitlements->sortBy('id')->values();
        $tokenA = (string) $tokens[0]->redeem_token;
        $tokenB = (string) $tokens[1]->redeem_token;

        $scan1 = $this->actingAs($this->merchantUser, 'sanctum')
            ->postJson('/api/merchant/qr/scan', ['qr_code' => $tokenA]);
        $scan1->assertOk()
            ->assertJsonPath('data.redeem_type', 'wallet');

        $order->refresh();
        $this->assertSame('activated', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->wallet_processed_at);

        $this->offer->refresh();
        $this->assertSame(18, (int) $this->offer->coupons_remaining, 'coupons_remaining unchanged after scan');
        $this->assertSame(0, (int) $this->offer->reserved_quantity);
        $this->assertSame(2, (int) $this->offer->used_quantity);

        $this->assertSame(1, Commission::where('order_id', $order->id)->count());
        $this->assertSame(1, WalletTransaction::where('wallet_type', 'merchant')->where('related_id', $order->id)->count());
        $this->assertGreaterThanOrEqual(1, WalletTransaction::where('wallet_type', 'admin')
            ->where('transaction_type', 'commission')
            ->where('related_id', $order->id)
            ->count());

        $mw = MerchantWallet::where('merchant_id', $this->merchant->id)->first();
        $this->assertNotNull($mw);
        $this->assertGreaterThan(0, (float) $mw->balance);

        $this->assertSame(1, ActivationReport::where('order_id', $order->id)->count());

        // المسح الثاني لبند الكوبون الثاني
        $scan2 = $this->actingAs($this->merchantUser, 'sanctum')
            ->postJson('/api/merchant/qr/scan', ['qr_code' => $tokenB]);
        $scan2->assertOk();

        $this->assertSame(1, Commission::where('order_id', $order->id)->count(), 'no double commission');
        $this->assertSame(2, ActivationReport::where('order_id', $order->id)->count());

        foreach ($order->fresh()->couponEntitlements as $ent) {
            $this->assertSame('exhausted', $ent->status);
        }
    }

    public function test_merchant_qr_scan_accepts_json_wrapped_token(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/mobile/orders/checkout/coupons', [
                'user_id' => $this->customer->id,
                'coupon_ids' => [$this->couponA->id],
                'payment_method' => 'cash',
            ])->assertCreated();

        $order = Order::where('user_id', $this->customer->id)->latest()->first();
        $raw = (string) $order->couponEntitlements()->first()->redeem_token;
        $wrapped = json_encode(['type' => 'coupon_entitlement', 'token' => $raw], JSON_UNESCAPED_UNICODE);
        $this->assertIsString($wrapped);

        $scan = $this->actingAs($this->merchantUser, 'sanctum')
            ->postJson('/api/merchant/qr/scan', ['qr_code' => $wrapped]);

        $scan->assertOk();
        $this->assertSame('activated', $order->fresh()->status);
    }
}
