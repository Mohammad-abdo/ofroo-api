<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\Role;
use App\Services\OfferService;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OfferService $offerService;
    protected Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $merchantRole = Role::create(['name' => 'merchant']);
        $user = User::create([
            'name' => 'Test Merchant',
            'email' => 'merchant@test.com',
            'password' => bcrypt('password'),
            'role_id' => $merchantRole->id,
        ]);
        $this->merchant = Merchant::create([
            'user_id' => $user->id,
            'company_name' => 'Test Company',
            'approved' => true,
        ]);
        $this->offerService = app(OfferService::class);
    }

    public function test_can_create_offer(): void
    {
        $data = [
            'merchant_id' => $this->merchant->id,
            'category_id' => null,
            'title' => 'Test Offer',
            'title_en' => 'Test Offer EN',
            'description' => 'Test Description',
            'price' => 100,
            'discount' => 10,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'status' => 'pending',
        ];

        $offer = $this->offerService->createOffer($data);

        $this->assertInstanceOf(Offer::class, $offer);
        $this->assertEquals('Test Offer', $offer->title);
        $this->assertEquals($this->merchant->id, $offer->merchant_id);
        $this->assertEquals(100, $offer->price);
        $this->assertEquals(10, $offer->discount);
        $this->assertEquals('pending', $offer->status);
    }

    public function test_can_update_offer(): void
    {
        $offer = Offer::create([
            'merchant_id' => $this->merchant->id,
            'title' => 'Original Title',
            'price' => 100,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'price' => 150,
        ];

        $updatedOffer = $this->offerService->updateOffer($offer, $updateData);

        $this->assertEquals('Updated Title', $updatedOffer->title);
        $this->assertEquals(150, $updatedOffer->price);
        $this->assertEquals('Original Title', $offer->fresh()->title);
    }

    public function test_offer_prices_are_numeric(): void
    {
        $data = [
            'merchant_id' => $this->merchant->id,
            'title' => 'Test Offer',
            'price' => '100',
            'discount' => '10.50',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ];

        $offer = $this->offerService->createOffer($data);

        $this->assertIsFloat($offer->price);
        $this->assertIsFloat($offer->discount);
        $this->assertEquals(100.0, $offer->price);
        $this->assertEquals(10.50, $offer->discount);
    }

    public function test_offer_status_defaults_to_pending(): void
    {
        $data = [
            'merchant_id' => $this->merchant->id,
            'title' => 'Test Offer',
            'price' => 100,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ];

        $offer = $this->offerService->createOffer($data);

        $this->assertEquals('pending', $offer->status);
    }

    public function test_can_delete_offer(): void
    {
        $offer = Offer::create([
            'merchant_id' => $this->merchant->id,
            'title' => 'To Delete',
            'price' => 100,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);

        $offerId = $offer->id;

        $this->offerService->deleteOffer($offer);

        $this->assertNull(Offer::find($offerId));
    }
}
