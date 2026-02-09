<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialReportsCache extends Model
{
    protected $fillable = [
        'name',
        'params_hash',
        'generated_at',
        'file_path',
        'file_format',
        'file_size',
        'params',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'params' => 'array',
        ];
    }
}
