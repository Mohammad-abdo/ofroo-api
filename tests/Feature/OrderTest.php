<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponEntitlement;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $userRole = Role::create([
            'name' => 'user',
            'name_ar' => 'مستخدم',
            'name_en' => 'User',
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+201012345678',
            'password' => Hash::make('password123'),
            'role_id' => $userRole->id,
        ]);

        $merchantRole = Role::create([
            'name' => 'merchant',
            'name_ar' => 'تاجر',
            'name_en' => 'Merchant',
        ]);

        $merchantUser = User::create([
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'phone' => '+201012345679',
            'password' => Hash::make('password123'),
            'role_id' => $merchantRole->id,
        ]);

        $this->merchant = Merchant::create([
            'user_id' => $merchantUser->id,
            'company_name' => 'Test Merchant',
            'approved' => true,
        ]);

        $this->category = Category::create([
            'name_ar' => 'مطاعم',
            'name_en' => 'Restaurants',
        ]);

        $this->branch = Branch::create([
            'merchant_id' => $this->merchant->id,
            'lat' => 29.3759,
            'lng' => 47.9774,
            'address' => 'Test Address',
        ]);

        $this->offer = Offer::create([
            'merchant_id' => $this->merchant->id,
            'category_id' => $this->category->id,
            'title' => 'عرض تجريبي',
            'title_en' => 'Test Offer',
            'price' => 25.00,
            'discount' => 0,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'status' => 'active',
        ]);

        DB::table('offers')->where('id', $this->offer->id)->update([
            'total_coupons' => 100,
            'coupons_remaining' => 100,
        ]);
        $this->offer->refresh();

        $this->offer->branches()->sync([$this->branch->id]);

        Coupon::create([
            'offer_id' => $this->offer->id,
            'coupon_setting_id' => 1,
            'title' => 'Checkout template',
            'price' => 25.00,
            'discount' => 0,
            'discount_type' => 'percentage',
            'barcode' => 'BC-'.Str::upper(Str::random(10)),
            'coupon_code' => 'CC-'.Str::upper(Str::random(10)),
            'status' => 'active',
            'usage_limit' => 1000,
            'times_used' => 0,
            'expires_at' => now()->addMonths(2),
        ]);
    }

    public function test_user_can_checkout_and_create_order(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'offer_id' => $this->offer->id,
            'quantity' => 2,
            'price_at_add' => 25.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/checkout', [
                'payment_method' => 'cash',
                'cart_id' => $cart->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'order',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'payment_method' => 'cash',
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);

        $cartResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/mobile/cart');
        $cartResponse->assertOk()
            ->assertJsonPath('data.items', []);

        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertGreaterThan(0, CouponEntitlement::where('order_id', $order->id)->count());
    }
}
