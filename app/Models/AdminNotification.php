<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotification extends Model
{
    protected $fillable = [
        'title',
        'title_ar',
        'title_en',
        'message',
        'message_ar',
        'message_en',
        'type',
        'target_audience',
        'target_user_ids',
        'target_merchant_ids',
        'action_url',
        'action_text',
        'image_url',
        'is_sent',
        'scheduled_at',
        'sent_at',
        'read_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_user_ids' => 'array',
            'target_merchant_ids' => 'array',
            'is_sent' => 'boolean',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get the admin who created the notification.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

