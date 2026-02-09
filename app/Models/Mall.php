<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mall extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'name_en',
        'description',
        'description_ar',
        'description_en',
        'address',
        'address_ar',
        'address_en',
        'city',
        'country',
        'latitude',
        'longitude',
        'phone',
        'email',
        'website',
        'image_url',
        'images',
        'opening_hours',
        'is_active',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'images' => 'array',
            'opening_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the branches in this mall.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get the offers in this mall.
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}

