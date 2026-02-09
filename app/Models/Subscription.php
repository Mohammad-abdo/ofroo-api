<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'subscribable_id',
        'subscribable_type',
        'package_name',
        'package_name_ar',
        'package_name_en',
        'starts_at',
        'ends_at',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    /**
     * Get the parent subscribable model (merchant or user).
     */
    public function subscribable()
    {
        return $this->morphTo();
    }
}
