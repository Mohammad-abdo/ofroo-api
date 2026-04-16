<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponEntitlementShare extends Model
{
    protected $fillable = [
        'parent_entitlement_id',
        'token',
        'status',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function parentEntitlement(): BelongsTo
    {
        return $this->belongsTo(CouponEntitlement::class, 'parent_entitlement_id');
    }
}
