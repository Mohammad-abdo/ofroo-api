<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'order_index',
        'parent_id',
        'image',
    ];

    protected $appends = ['image_url'];

    /**
     * Placeholder image when category has no image (usable in API).
     */
    public const DEFAULT_IMAGE_URL = 'https://cdn-icons-png.flaticon.com/256/3179/3179068.png';

    /**
     * Full URL for category image (storage path or placeholder).
     */
    public function getImageUrlAttribute(): string
    {
        try {
            if (empty($this->attributes['image'] ?? null)) {
                return self::DEFAULT_IMAGE_URL;
            }
            $image = $this->attributes['image'];
            if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
                return $image;
            }
            $base = rtrim(config('app.url', 'http://localhost'), '/');
            return $base . '/storage/' . ltrim($image, '/');
        } catch (\Throwable $e) {
            return self::DEFAULT_IMAGE_URL;
        }
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get the offers for the category.
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
    
    /**
     * Get the coupons for the category.
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }
}
