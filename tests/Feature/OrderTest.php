<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Role;
use App\Models\StoreLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $userRole = Role::create([
            'name' => 'user',
            'name_ar' => 'مستخدم',
            'name_en' => 'User',
        ]);

        // Create user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+96512345678',
            'password' => Hash::make('password123'),
            'role_id' => $userRole->id,
        ]);

        // Create merchant
        $merchantRole = Role::create([
            'name' => 'merchant',
            'name_ar' => 'تاجر',
            'name_en' => 'Merchant',
        ]);

        $merchantUser = User::create([
            'name' => 'Merchant User',
            'email' => 'merchant@example.com',
            'phone' => '+96512345679',
            'password' => Hash::make('password123'),
            'role_id' => $merchantRole->id,
        ]);

        $this->merchant = Merchant::create([
            'user_id' => $merchantUser->id,
            'company_name' => 'Test Merchant',
            'approved' => true,
        ]);

        // Create category
        $this->category = Category::create([
            'name_ar' => 'مطاعم',
            'name_en' => 'Restaurants',
        ]);

        // Create store location
        $this->location = StoreLocation::create([
            'merchant_id' => $this->merchant->id,
            'lat' => 29.3759,
            'lng' => 47.9774,
            'address' => 'Test Address',
        ]);

        // Create offer
        $this->offer = Offer::create([
            'merchant_id' => $this->merchant->id,
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
            'title_ar' => 'عرض تجريبي',
            'title_en' => 'Test Offer',
            'price' => 25.00,
            'original_price' => 50.00,
            'discount_percent' => 50,
            'total_coupons' => 100,
            'coupons_remaining' => 100,
            'status' => 'active',
        ]);
    }

    public function test_user_can_checkout_and_create_order(): void
    {
        // Create cart
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
                'order',
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'payment_method' => 'cash',
        ]);

        // Check that coupons were created
        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertGreaterThan(0, Coupon::where('order_id', $order->id)->count());
    }
}

