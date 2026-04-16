<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivationReport extends Model
{
    protected $fillable = [
        'coupon_id',
        'coupon_entitlement_id',
        'coupon_entitlement_share_id',
        'merchant_id',
        'user_id',
        'activated_by_user_id',
        'order_id',
        'activation_method',
        'device_id',
        'ip_address',
        'location',
        'latitude',
        'longitude',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function couponEntitlement(): BelongsTo
    {
        return $this->belongsTo(CouponEntitlement::class);
    }

    public function couponEntitlementShare(): BelongsTo
    {
        return $this->belongsTo(CouponEntitlementShare::class);
    }
}
