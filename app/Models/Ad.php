<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ad extends Model
{
    protected $fillable = [
        'title',
        'title_ar',
        'title_en',
        'description',
        'description_ar',
        'description_en',
        'image_url',
        'video_url',
        'images',
        'link_url',
        'ad_type',
        'merchant_id',
        'offer_id',
        'category_id',
        'is_active',
        'order_index',
        'start_date',
        'end_date',
        'clicks_count',
        'views_count',
    ];

    protected $hidden = [
        // Not used in OFROO anymore; kept in DB for backward compatibility.
        'total_budget',
        'cost_per_click',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_active' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    /**
     * Expose absolute URLs when the DB stores relative storage paths (e.g. seeded ads, legacy rows).
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value === null || $value === '') {
                    return $value;
                }
                if (preg_match('#^https?://#i', $value)) {
                    return $value;
                }

                return asset('storage/'.ltrim($value, '/'));
            }
        );
    }

    /**
     * Same as image_url for uploaded or relative video paths.
     */
    protected function videoUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value === null || $value === '') {
                    return $value;
                }
                if (preg_match('#^https?://#i', $value)) {
                    return $value;
                }

                return asset('storage/'.ltrim($value, '/'));
            }
        );
    }

    /**
     * Get the merchant that owns the ad.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Get the category for the ad.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Calculate click-through rate (CTR)
     */
    public function getClickRateAttribute(): float
    {
        if ($this->views_count == 0) {
            return 0.0;
        }

        return round(($this->clicks_count / $this->views_count) * 100, 2);
    }

    /**
     * Get ad status based on dates
     */
    public function getStatusAttribute(): string
    {
        $now = now();

        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->start_date && $now->lt($this->start_date)) {
            return 'scheduled';
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return 'expired';
        }

        return 'active';
    }
}
