<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorAuth extends Model
{
    protected $fillable = [
        'user_id',
        'is_enabled',
        'secret_key',
        'recovery_codes',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'recovery_codes' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
