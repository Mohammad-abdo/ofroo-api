<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'offer_id',
        'category_id',
        'coupon_setting_id',
        'image',
        'title',
        'title_ar',
        'title_en',
        'description',
        'description_ar',
        'description_en',
        'price',
        'discount',
        'discount_type', // percentage | fixed
        'barcode',
        'coupon_code', // legacy column (unique code for redemption)
        'starts_at',
        'expires_at',
        'status', // active, used, expired
        'usage_limit', // max uses; 0 = unlimited
        'times_used',  // how many times it has been used
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the offer that owns the coupon.
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Optional direct category (also implied by offer.category_id when offer is set).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Global coupon policy row in effect when this coupon was created.
     */
    public function appCouponSetting(): BelongsTo
    {
        return $this->belongsTo(AppCouponSetting::class, 'coupon_setting_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(CouponEntitlement::class);
    }

    /**
     * Scope a query to only include active coupons.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    /**
     * انتهاء الكوبون: status = expired أو تجاوز expires_at.
     */
    public function isExpired(): bool
    {
        if (strtolower((string) ($this->status ?? '')) === 'expired') {
            return true;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    public function isNotYetStarted(): bool
    {
        return (bool) ($this->starts_at && $this->starts_at->isFuture());
    }

    public function effectiveStatus(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }
        if ($this->isNotYetStarted()) {
            return 'not_started';
        }

        $s = (string) ($this->status ?? 'active');

        return $s !== '' ? $s : 'active';
    }

    /**
     * Price after applying discount.
     * percent/percentage: price * (1 - discount/100), fixed: price - discount.
     */
    public function getPriceAfterDiscountAttribute(): float
    {
        $price = (float) ($this->price ?? 0);
        $discount = (float) ($this->discount ?? 0);
        $type = strtolower((string) ($this->discount_type ?? 'percent'));

        if (in_array($type, ['fixed', 'amount'], true)) {
            return round(max(0, $price - $discount), 2);
        }

        return round($price * (1 - $discount / 100), 2);
    }
}
