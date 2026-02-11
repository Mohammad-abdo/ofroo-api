<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'order_index',
    ];

    /**
     * Get the cities for the governorate.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
