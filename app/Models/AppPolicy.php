<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppPolicy extends Model
{
    protected $table = 'app_policies';

    protected $fillable = [
        'title_ar',
        'title_en',
        'description_ar',
        'description_en',
        'order_index',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order_index' => 'integer',
        ];
    }
}
