<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulatoryCheck extends Model
{
    protected $fillable = [
        'merchant_id',
        'check_type',
        'result',
        'details',
        'notes',
        'checked_at',
        'checked_by_admin_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_admin_id');
    }
}
