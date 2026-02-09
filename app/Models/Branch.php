<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    protected $fillable = [
        'merchant_id',
        'name',
        'name_ar',
        'name_en',
        'phone',
        'is_active',
        'lat',
        'lng',
        'address',
        'address_ar',
        'address_en',
        'google_place_id',
        'opening_hours',
        'mall_id',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'opening_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the merchant that owns the branch.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the mall that the branch belongs to.
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    /**
     * Get the offers for the branch.
     * Pivot table: offer_branch (must match Offer::branches()).
     */
    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class, 'offer_branch');
    }
}
