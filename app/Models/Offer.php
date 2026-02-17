<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'category_id',
        'mall_id',
        'title',
        'description',
        'price',
        'discount',
        'offer_images',
        'start_date',
        'end_date',
        'location',
        'status', // active, expired, disabled
        'terms_conditions_ar',
        'terms_conditions_en',
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
     * Scope a query to only include active offers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    /**
     * Total number of coupon uses still available (sum of usage_limit - times_used for active coupons).
     * Used when adding offer to cart to ensure we don't exceed available uses.
     */
    public function getAvailableCouponsCountAttribute(): int
    {
        if (\Schema::hasColumn('coupons', 'usage_limit') && \Schema::hasColumn('coupons', 'times_used')) {
            if (! $this->relationLoaded('coupons')) {
                $this->load('coupons');
            }
            $total = 0;
            foreach ($this->coupons as $coupon) {
                if (($coupon->status ?? '') !== 'active') {
                    continue;
                }
                $limit = (int) ($coupon->usage_limit ?? 1);
                $used = (int) ($coupon->times_used ?? 0);
                $total += max(0, $limit - $used);
            }
            return $total;
        }

        if (\Schema::hasColumn('offers', 'coupons_remaining')) {
            return (int) $this->coupons_remaining;
        }

        return 0;
    }

    /**
     * Consume (increment times_used) on this offer's coupons by the given quantity.
     * Used at checkout when order is placed.
     */
    public function consumeCoupons(int $quantity): void
    {
        if ($quantity <= 0) {
            return;
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

        // Backward compatibility: also decrement offer.coupons_remaining if column exists
        if (\Schema::hasColumn('offers', 'coupons_remaining')) {
            $this->decrement('coupons_remaining', $quantity);
        }
    }
}
