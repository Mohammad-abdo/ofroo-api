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
        'description',
        'price',
        'discount',
        'discount_type', // percentage | fixed
        'barcode',
        'coupon_code', // legacy column (unique code for redemption)
        'expires_at',
        'status', // active, used, expired
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
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
}
