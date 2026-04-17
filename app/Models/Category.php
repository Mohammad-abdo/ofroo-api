<?php

namespace App\Models;

use App\Support\ApiMediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Category extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'order_index',
        'parent_id',
        'image',
        'is_active',
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
            $image = (string) $this->attributes['image'];
            $abs = ApiMediaUrl::publicAbsolute($image);

            return $abs !== '' ? $abs : self::DEFAULT_IMAGE_URL;
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
     * Merchants assigned to this category (each merchant has one category).
     */
    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class, 'category_id');
    }

    /**
     * Coupons on offers that belong to this category (canonical admin path).
     */
    public function couponsViaOffers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Coupon::class,
            Offer::class,
            'category_id',
            'offer_id',
            'id',
            'id'
        )->whereNotNull('coupons.offer_id');
    }

    /**
     * Legacy: coupons.category_id when present on the coupons table.
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }
}
