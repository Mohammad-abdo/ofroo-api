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

    /**
     * Shape for API / SPA (user profile, login).
     */
    public static function toApiArray(?self $staff): ?array
    {
        if (! $staff) {
            return null;
        }

        return [
            'id' => $staff->id,
            'merchant_id' => $staff->merchant_id,
            'role' => $staff->role,
            'role_ar' => $staff->role_ar,
            'role_en' => $staff->role_en,
            'permissions' => $staff->permissions,
            'can_create_offers' => (bool) $staff->can_create_offers,
            'can_edit_offers' => (bool) $staff->can_edit_offers,
            'can_activate_coupons' => (bool) $staff->can_activate_coupons,
            'can_view_reports' => (bool) $staff->can_view_reports,
            'can_manage_staff' => (bool) $staff->can_manage_staff,
            'is_active' => (bool) $staff->is_active,
        ];
    }
}
