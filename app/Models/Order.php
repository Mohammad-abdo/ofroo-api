<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'merchant_id',
        'total_amount',
        'payment_method',
        'payment_status',
        'notes',
        // Reservation + activation lifecycle (added 2026-05; mobile API responses are unchanged).
        'status',
        'reservation_expires_at',
        'activated_at',
        'wallet_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'reservation_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'wallet_processed_at' => 'datetime',
        ];
    }

    /**
     * True when the reservation window has elapsed and the order is still pending.
     * Used by the orders:expire-reservations scheduled command.
     */
    public function isReservationExpired(): bool
    {
        if ((string) ($this->status ?? '') !== 'pending') {
            return false;
        }
        if (! $this->reservation_expires_at) {
            return false;
        }

        return $this->reservation_expires_at->isPast();
    }

    /**
     * Mark this order as activated (first successful QR scan).
     * Caller is responsible for the surrounding transaction + locking.
     */
    public function markActivated(): void
    {
        $this->forceFill([
            'status' => 'activated',
            'activated_at' => $this->activated_at ?: now(),
        ])->save();
    }

    /**
     * Mark this order as expired (reservation window elapsed).
     * Caller is responsible for the surrounding transaction + locking.
     */
    public function markExpired(): void
    {
        $this->forceFill([
            'status' => 'expired',
        ])->save();
    }

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the merchant that owns the order.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the order items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the coupons for the order.
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Purchased coupon balances (wallet lines) for this order.
     */
    public function couponEntitlements(): HasMany
    {
        return $this->hasMany(CouponEntitlement::class);
    }

    /**
     * Get the payments for the order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
