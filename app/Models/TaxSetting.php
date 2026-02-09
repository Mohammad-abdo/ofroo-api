<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    protected $fillable = [
        'country_code',
        'tax_name',
        'tax_name_ar',
        'tax_name_en',
        'tax_rate',
        'is_active',
        'exempt_categories',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'exempt_categories' => 'array',
        ];
    }
}
