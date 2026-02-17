<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'offer_id',
        'coupon_id',
        'quantity',
        'price_at_add',
    ];

    protected function casts(): array
    {
        return [
            'price_at_add' => 'decimal:2',
        ];
    }

    /**
     * Get the cart that owns the cart item.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the offer that owns the cart item.
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Get the specific coupon (when user adds a coupon to cart).
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
