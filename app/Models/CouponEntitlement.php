<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponEntitlement extends Model
{
    protected $fillable = [
        'user_id',
        'coupon_id',
        'order_id',
        'order_item_id',
        'usage_limit',
        'times_used',
        'reserved_shares_count',
        'status',
        'redeem_token',
    ];

    protected function casts(): array
    {
        return [
            'usage_limit' => 'integer',
            'times_used' => 'integer',
            'reserved_shares_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(CouponEntitlementShare::class, 'parent_entitlement_id');
    }

    /**
     * Remaining redemptions (wallet uses + unredeemed reserved shares block this pool).
     */
    public function remainingUses(): int
    {
        $limit = (int) $this->usage_limit;
        $used = (int) $this->times_used;
        $reserved = (int) $this->reserved_shares_count;

        return max(0, $limit - $used - $reserved);
    }

    public function isActiveForRedemption(): bool
    {
        return $this->status === 'active' && $this->remainingUses() > 0;
    }
}
