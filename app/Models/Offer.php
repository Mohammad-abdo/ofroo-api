<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Offer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'category_id',
        'coupon_id',
        'mall_id',
        'title',
        'title_en',
        'description',
        'description_en',
        'price',
        'discount',
        'offer_images',
        'start_date',
        'end_date',
        'location',
        'status', // active, expired, disabled
        'terms_conditions_ar',
        'terms_conditions_en',
        // Internal-only reservation accounting. coupons_remaining stays the
        // primary, mobile-facing source of truth — these two fields MUST NOT
        // be exposed in mobile API responses.
        'reserved_quantity',
        'used_quantity',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
            'offer_images' => 'array',
            'location' => 'array',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    /**
     * Get the merchant that owns the offer.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the category that owns the offer.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the mall that the offer belongs to.
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    /**
     * Get the branches where the offer is available.
     * Pivot table: offer_branch (migration creates offer_branch, not default branch_offer).
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'offer_branch');
    }

    /**
     * Get the coupons for the offer.
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Get the users who favorited the offer.
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'offer_user');
    }

    /**
     * User reviews tied to this offer (post-checkout ratings).
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'offer_id');
    }

    /**
     * Scope a query to only include active offers.
     * Null start_date / end_date means no bound on that side (SQL would otherwise drop rows).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Offers that should appear in the public mobile app: active window + at least one redeemable coupon slot.
     * Admin and merchant dashboards use unscoped queries instead.
     */
    public function scopeMobilePubliclyAvailable(Builder $query): Builder
    {
        return $query->active()
            ->where(function (Builder $q) {
                $q->whereHas('coupons', function ($cq) {
                    $cq->where('status', 'active')
                        ->where(function ($w) {
                            $w->whereNull('usage_limit')
                                ->orWhereColumn('times_used', '<', 'usage_limit');
                        });
                });
                if (Schema::hasColumn('offers', 'coupons_remaining')) {
                    $q->orWhere('coupons_remaining', '>', 0);
                }
            });
    }

    /**
     * انتهاء العرض: حقل status = expired أو تجاوز end_date.
     */
    public function isExpired(): bool
    {
        if (strtolower((string) ($this->status ?? '')) === 'expired') {
            return true;
        }
        if ($this->end_date && $this->end_date->isPast()) {
            return true;
        }

        return false;
    }

    public function isNotYetStarted(): bool
    {
        return (bool) ($this->start_date && $this->start_date->isFuture());
    }

    /**
     * حالة موحّدة للـ API (التاريخ يتقدّم على status إن كان نشطاً بالاسم فقط).
     */
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
     * Total number of coupon uses still available (sum of usage_limit - times_used for active coupons).
     * Used when adding offer to cart to ensure we don't exceed available uses.
     */
    public function getAvailableCouponsCountAttribute(): int
    {
        $fromCoupons = 0;
        if (\Schema::hasColumn('coupons', 'usage_limit') && \Schema::hasColumn('coupons', 'times_used')) {
            if (! $this->relationLoaded('coupons')) {
                $this->load('coupons');
            }
            foreach ($this->coupons as $coupon) {
                if (($coupon->status ?? '') !== 'active') {
                    continue;
                }
                $limit = (int) ($coupon->usage_limit ?? 1);
                $used = (int) ($coupon->times_used ?? 0);
                $fromCoupons += max(0, $limit - $used);
            }
        }

        if ($fromCoupons > 0) {
            return $fromCoupons;
        }

        if (\Schema::hasColumn('offers', 'coupons_remaining')) {
            return max(0, (int) $this->coupons_remaining);
        }

        return 0;
    }

    /**
     * Reserve inventory at checkout.
     *
     * Inventory contract (mobile compatibility):
     *   - coupons_remaining -= qty   (primary, mobile-facing source of truth)
     *   - reserved_quantity += qty   (internal tracking)
     *   - used_quantity is NOT touched here
     *
     * Atomic: a single SQL UPDATE so concurrent checkouts can't desynchronise
     * the two columns. Caller should still wrap the surrounding business logic
     * in a DB transaction with row-level locks for cross-row safety.
     */
    public function reserve(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $qty = (int) $quantity;

        \DB::table('offers')
            ->where('id', $this->id)
            ->update([
                'coupons_remaining' => \DB::raw("CASE WHEN coupons_remaining > {$qty} THEN coupons_remaining - {$qty} ELSE 0 END"),
                'reserved_quantity' => \DB::raw("reserved_quantity + {$qty}"),
                'updated_at' => now(),
            ]);

        // Refresh in-memory state so subsequent reads on this instance are accurate.
        $this->refresh();
    }

    /**
     * Release a reservation back to the pool (expiry or cancel).
     *
     * Inventory contract:
     *   - coupons_remaining += qty
     *   - reserved_quantity -= qty (clamped at 0 for safety)
     *   - used_quantity is NOT touched
     */
    public function releaseReservation(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $qty = (int) $quantity;

        \DB::table('offers')
            ->where('id', $this->id)
            ->update([
                'coupons_remaining' => \DB::raw("coupons_remaining + {$qty}"),
                'reserved_quantity' => \DB::raw("CASE WHEN reserved_quantity > {$qty} THEN reserved_quantity - {$qty} ELSE 0 END"),
                'updated_at' => now(),
            ]);

        $this->refresh();
    }

    /**
     * Convert a reservation to "used" at QR activation.
     *
     * Inventory contract:
     *   - reserved_quantity -= qty (clamped at 0 for safety)
     *   - used_quantity     += qty
     *   - coupons_remaining is NOT touched (already decremented at checkout
     *     and stays decremented; this is the rule that keeps mobile compatible).
     */
    public function consumeReserved(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $qty = (int) $quantity;

        \DB::table('offers')
            ->where('id', $this->id)
            ->update([
                'reserved_quantity' => \DB::raw("CASE WHEN reserved_quantity > {$qty} THEN reserved_quantity - {$qty} ELSE 0 END"),
                'used_quantity' => \DB::raw("used_quantity + {$qty}"),
                'updated_at' => now(),
            ]);

        $this->refresh();
    }

    /**
     * Consume (increment times_used) on this offer's coupons by the given quantity.
     *
     * @deprecated since 2026-05 reservation refactor — DO NOT call from checkout,
     *             QR activation, or any live financial path. Use {@see reserve()},
     *             {@see consumeReserved()}, and {@see releaseReservation()} instead.
     *
     * Calling this method always throws in production to prevent accidental double
     * inventory mutation. The body is retained below as a reference for manual
     * repair scripts only; set env ALLOW_LEGACY_CONSUME_COUPONS=true (never in prod)
     * to execute it.
     */
    public function consumeCoupons(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $allowLegacy = filter_var(
            env('ALLOW_LEGACY_CONSUME_COUPONS', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $allowLegacy) {
            throw new \LogicException(
                'Offer::consumeCoupons() is deprecated and must not be used in checkout or activation flows. '.
                'Use reserve(), releaseReservation(), and consumeReserved() instead.'
            );
        }

        if (! \Schema::hasColumn('coupons', 'usage_limit') || ! \Schema::hasColumn('coupons', 'times_used')) {
            if (\Schema::hasColumn('offers', 'coupons_remaining')) {
                $this->decrement('coupons_remaining', $quantity);
            }

            return;
        }

        $remaining = $quantity;
        $coupons = $this->coupons()
            ->where('status', 'active')
            ->whereRaw('(times_used < usage_limit OR usage_limit IS NULL)')
            ->orderBy('id')
            ->get();

        foreach ($coupons as $coupon) {
            if ($remaining <= 0) {
                break;
            }
            $limit = (int) ($coupon->usage_limit ?? 1);
            $used = (int) ($coupon->times_used ?? 0);
            $available = max(0, $limit - $used);
            if ($available <= 0) {
                continue;
            }
            $take = min($remaining, $available);
            $coupon->increment('times_used', $take);
            $remaining -= $take;
        }

        if (\Schema::hasColumn('offers', 'coupons_remaining')) {
            $this->decrement('coupons_remaining', $quantity);
        }
    }
}
