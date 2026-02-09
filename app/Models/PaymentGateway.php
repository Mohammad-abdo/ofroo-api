<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'display_name_ar',
        'display_name_en',
        'is_active',
        'credentials',
        'settings',
        'fee_percentage',
        'fee_fixed',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credentials' => 'array',
            'settings' => 'array',
            'fee_percentage' => 'decimal:2',
            'fee_fixed' => 'decimal:2',
        ];
    }
}
