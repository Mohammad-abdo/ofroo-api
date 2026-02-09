<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'merchant_id',
        'order_id',
        'rating',
        'notes',
        'notes_ar',
        'notes_en',
        'visible_to_public',
        'moderated_by_admin_id',
        'moderation_action',
        'moderation_reason',
        'moderation_at',
    ];

    protected function casts(): array
    {
        return [
            'visible_to_public' => 'boolean',
            'moderation_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the review.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the merchant that owns the review.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the order that owns the review.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_admin_id');
    }
}
