<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    protected $fillable = [
        'offer_id',
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
     * Scope a query to only include active coupons.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
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
