<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Coupon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferService
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Create a new offer with its coupons and branches.
     */
    public function createOffer(array $data): Offer
    {
        return DB::transaction(function () use ($data) {
            $offerData = collect($data)->except(['branches', 'coupons', 'coupon_image_files'])->toArray();

            $offer = Offer::create($offerData);

            $branches = $data['branches'] ?? [];
            $offer->branches()->sync(is_array($branches) ? $branches : []);

            $couponImageFiles = $data['coupon_image_files'] ?? [];
            $couponsList = $data['coupons'] ?? [];
            foreach ($couponsList as $index => $couponData) {
                if (!is_array($couponData)) {
                    continue;
                }
                $this->createCouponForOffer($offer, $couponData, $couponImageFiles[$index] ?? null);
            }

            return $offer->load(['branches', 'coupons']);
        });
    }

    /**
     * Update an existing offer.
     */
    public function updateOffer(Offer $offer, array $data): Offer
    {
        return DB::transaction(function () use ($offer, $data) {
            $offerData = collect($data)->except(['branches', 'coupons', 'coupon_image_files'])->toArray();

            $offer->update($offerData);

            // Always sync branches (use key_exists so empty array is still synced)
            $offer->branches()->sync($data['branches'] ?? []);

            $couponImageFiles = $data['coupon_image_files'] ?? [];
            $couponsList = $data['coupons'] ?? [];
            $offer->coupons()->delete();
            foreach ($couponsList as $index => $couponData) {
                if (!is_array($couponData)) {
                    continue;
                }
                $this->createCouponForOffer($offer, $couponData, $couponImageFiles[$index] ?? null);
            }

            return $offer->load(['branches', 'coupons']);
        });
    }

    /**
     * Create one coupon for an offer: set expires_at from offer, barcode if empty, image from file.
     * Do NOT filter out 0 or '' so price/discount/title are always saved.
     * Public so Admin can create coupons for offers.
     */
    public function createCouponForOffer(Offer $offer, array $couponData, ?UploadedFile $imageFile = null): Coupon
    {
        $allowed = ['title', 'description', 'price', 'discount', 'discount_type', 'barcode', 'image', 'status', 'usage_limit'];
        $payload = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $couponData)) {
                $payload[$key] = $couponData[$key];
            }
        }

        $payload['offer_id'] = $offer->id;
        $payload['expires_at'] = $offer->end_date;
        $payload['status'] = $payload['status'] ?? 'active';
        $payload['usage_limit'] = isset($payload['usage_limit']) ? max(1, (int) $payload['usage_limit']) : 1;
        $payload['times_used'] = 0;
        $payload['title'] = trim((string) ($payload['title'] ?? ''));
        $payload['description'] = isset($payload['description']) ? trim((string) $payload['description']) : null;
        $payload['price'] = isset($payload['price']) ? (float) $payload['price'] : 0;
        $payload['discount'] = isset($payload['discount']) ? (float) $payload['discount'] : 0;

        $dt = $payload['discount_type'] ?? 'percentage';
        $isPercentage = in_array($dt, ['percentage', 'percent'], true);
        $discount = (float) $payload['discount'];
        if ($isPercentage && ($discount < 0 || $discount > 100)) {
            throw ValidationException::withMessages(['coupons' => ['Coupon discount (percentage) must be between 0 and 100.']]);
        }
        if (!$isPercentage && $discount < 0) {
            throw ValidationException::withMessages(['coupons' => ['Coupon discount (fixed) must be ≥ 0.']]);
        }
        // DB column may be enum('percent','amount'); map for insert
        $payload['discount_type'] = in_array($dt, ['fixed', 'amount'], true) ? 'amount' : 'percent';

        if (empty($payload['barcode']) || !trim((string) $payload['barcode'])) {
            $payload['barcode'] = $this->couponService->generateUniqueBarcode();
        } else {
            $payload['barcode'] = trim((string) $payload['barcode']);
        }

        // coupon_code is required in DB (legacy); use barcode if not provided
        if (!isset($payload['coupon_code']) || trim((string) ($payload['coupon_code'] ?? '')) === '') {
            $payload['coupon_code'] = $payload['barcode'];
        } else {
            $payload['coupon_code'] = trim((string) $payload['coupon_code']);
        }

        if ($imageFile && $imageFile->isValid()) {
            $path = $imageFile->store('coupons', 'public');
            $payload['image'] = asset('storage/' . $path);
        } elseif (!empty($payload['image']) && is_string($payload['image'])) {
            // Keep existing URL
        } else {
            $payload['image'] = null;
        }

        // DB column is enum('percent','amount') — ensure we never send 'percentage' or 'fixed'
        $payload['discount_type'] = in_array($payload['discount_type'] ?? '', ['percent', 'amount'], true)
            ? $payload['discount_type']
            : 'percent';

        return $offer->coupons()->create($payload);
    }

    /**
     * Toggle favorite status for a user.
     */
    public function toggleFavorite(Offer $offer, int $userId): bool
    {
        $exists = $offer->favoritedBy()->where('user_id', $userId)->exists();
        
        if ($exists) {
            $offer->favoritedBy()->detach($userId);
            return false;
        } else {
            $offer->favoritedBy()->attach($userId);
            return true;
        }
    }

    /**
     * Expire offers that have reached their end date.
     */
    public function expireOffers(): int
    {
        $expiredCount = Offer::where('status', 'active')
            ->where('end_date', '<', now())
            ->update(['status' => 'expired']);

        if ($expiredCount > 0) {
            // Also expire related coupons
            Coupon::whereIn('offer_id', function ($query) {
                $query->select('id')->from('offers')->where('status', 'expired');
            })->update(['status' => 'expired']);
        }

        return $expiredCount;
    }
}
