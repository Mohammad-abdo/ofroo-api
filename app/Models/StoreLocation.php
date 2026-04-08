<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreLocation extends Model
{
    protected $fillable = [
        'merchant_id',
        'lat',
        'lng',
        'address',
        'address_ar',
        'address_en',
        'google_place_id',
        'opening_hours',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'opening_hours' => 'array',
        ];
    }

    /**
     * Get the merchant that owns the store location.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the offers for the store location.
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'location_id');
    }
}
