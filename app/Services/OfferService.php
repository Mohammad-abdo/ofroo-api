<?php

namespace App\Services;

use App\Models\AppCouponSetting;
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
        AppCouponSetting::assertOfferCanAddCoupon($offer);

        $allowed = [
            'title', 'title_ar', 'title_en', 'description', 'description_ar', 'description_en',
            'price', 'discount', 'discount_type', 'barcode', 'image', 'status', 'usage_limit',
            'expires_at', 'starts_at',
        ];
        $payload = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $couponData)) {
                $payload[$key] = $couponData[$key];
            }
        }

        $payload['offer_id'] = $offer->id;
        if (array_key_exists('expires_at', $couponData) && $couponData['expires_at'] !== null && $couponData['expires_at'] !== '') {
            $payload['expires_at'] = is_string($couponData['expires_at'])
                ? date('Y-m-d H:i:s', strtotime($couponData['expires_at']))
                : $couponData['expires_at'];
        } elseif ($offer->end_date) {
            $payload['expires_at'] = $offer->end_date;
        } else {
            $days = max(1, (int) AppCouponSetting::current()->coupon_expiry_days);
            $payload['expires_at'] = now()->addDays($days);
        }
        if (! empty($payload['starts_at'])) {
            $payload['starts_at'] = is_string($payload['starts_at'])
                ? date('Y-m-d H:i:s', strtotime($payload['starts_at']))
                : $payload['starts_at'];
        } else {
            $payload['starts_at'] = null;
        }
        $payload['status'] = $payload['status'] ?? 'active';
        if (array_key_exists('usage_limit', $couponData)) {
            $ul = $couponData['usage_limit'];
            if ($ul === 'unlimited' || (string) $ul === '0' || (is_numeric($ul) && (int) $ul === 0)) {
                $payload['usage_limit'] = 0;
            } elseif ($ul === '' || $ul === null || $ul === false) {
                $payload['usage_limit'] = 1;
            } else {
                $payload['usage_limit'] = max(1, (int) $ul);
            }
        } else {
            $payload['usage_limit'] = 1;
        }
        $payload['times_used'] = 0;
        $titleAr = trim((string) ($payload['title_ar'] ?? ''));
        $titleEn = trim((string) ($payload['title_en'] ?? ''));
        $payload['title_ar'] = $titleAr !== '' ? $titleAr : null;
        $payload['title_en'] = $titleEn !== '' ? $titleEn : null;
        $payload['title'] = trim((string) ($payload['title'] ?? ''));
        if ($payload['title'] === '' && ($titleAr !== '' || $titleEn !== '')) {
            $payload['title'] = $titleAr !== '' ? $titleAr : $titleEn;
        }
        $descAr = isset($payload['description_ar']) ? trim((string) $payload['description_ar']) : '';
        $descEn = isset($payload['description_en']) ? trim((string) $payload['description_en']) : '';
        $payload['description_ar'] = $descAr !== '' ? $descAr : null;
        $payload['description_en'] = $descEn !== '' ? $descEn : null;
        $payload['description'] = isset($payload['description']) ? trim((string) $payload['description']) : null;
        if (($payload['description'] === null || $payload['description'] === '') && ($descAr !== '' || $descEn !== '')) {
            $payload['description'] = $descAr !== '' ? $descAr : $descEn;
        }
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

        $payload['coupon_setting_id'] = AppCouponSetting::current()->id;

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
