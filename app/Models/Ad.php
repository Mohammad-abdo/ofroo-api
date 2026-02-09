<?php

namespace App\Models;

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
        'position',
        'ad_type',
        'merchant_id',
        'category_id',
        'is_active',
        'order_index',
        'start_date',
        'end_date',
        'clicks_count',
        'views_count',
        'cost_per_click',
        'total_budget',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_active' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'cost_per_click' => 'decimal:2',
            'total_budget' => 'decimal:2',
        ];
    }

    /**
     * Get the merchant that owns the ad.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
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
     * Get estimated reach based on views and position
     */
    public function getEstimatedReachAttribute(): int
    {
        $multiplier = match($this->position) {
            'header' => 1.5,
            'sidebar' => 1.2,
            'inline' => 1.0,
            default => 1.0
        };
        
        return intval($this->views_count * $multiplier);
    }

    /**
     * Get ad status based on dates
     */
    public function getStatusAttribute(): string
    {
        $now = now();
        
        if (!$this->is_active) {
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

