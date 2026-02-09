<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'device_type',
        'device_name',
        'os_version',
        'app_version',
        'fcm_token',
        'ip_address',
        'last_active_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
