<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class AppCouponSetting extends Model
{
    protected $table = 'app_coupon_settings';

    protected $fillable = [
        'max_coupons_per_merchant',
        'coupon_expiry_days',
        'auto_cancel_enabled',
        'days_before_cancel',
        'grace_period_hours',
        'notify_merchant',
        'notify_user',
        'auto_refund',
    ];

    protected function casts(): array
    {
        return [
            'auto_cancel_enabled' => 'boolean',
            'notify_merchant' => 'boolean',
            'notify_user' => 'boolean',
            'auto_refund' => 'boolean',
        ];
    }

    /**
     * Global coupon policy row (single row, id = 1 after migration).
     */
    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'max_coupons_per_merchant' => 50,
            'coupon_expiry_days' => 30,
            'auto_cancel_enabled' => false,
            'days_before_cancel' => 7,
            'grace_period_hours' => 24,
            'notify_merchant' => false,
            'notify_user' => false,
            'auto_refund' => false,
        ]);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'coupon_setting_id');
    }

    /**
     * Enforce max coupons per merchant (counts coupons on this merchant's offers).
     */
    public static function assertOfferCanAddCoupon(Offer $offer): void
    {
        $policy = static::current();
        $max = (int) $policy->max_coupons_per_merchant;
        if ($max < 1) {
            return;
        }

        $merchantId = $offer->merchant_id;
        if (! $merchantId) {
            return;
        }

        $count = Coupon::query()
            ->whereHas('offer', fn ($q) => $q->where('merchant_id', $merchantId))
            ->count();

        if ($count >= $max) {
            throw ValidationException::withMessages([
                'coupons' => ["This merchant has reached the maximum number of coupons ({$max})."],
            ]);
        }
    }
}
