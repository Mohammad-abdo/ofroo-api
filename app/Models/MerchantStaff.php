<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantStaff extends Model
{
    protected $fillable = [
        'merchant_id',
        'user_id',
        'role',
        'role_ar',
        'role_en',
        'permissions',
        'can_create_offers',
        'can_edit_offers',
        'can_activate_coupons',
        'can_view_reports',
        'can_manage_staff',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'can_create_offers' => 'boolean',
            'can_edit_offers' => 'boolean',
            'can_activate_coupons' => 'boolean',
            'can_view_reports' => 'boolean',
            'can_manage_staff' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
