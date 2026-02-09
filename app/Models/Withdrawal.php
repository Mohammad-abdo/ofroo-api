<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'merchant_id',
        'amount',
        'method',
        'withdrawal_method',
        'account_details',
        'bank_account_details',
        'status',
        'requested_at',
        'processed_by_admin_id',
        'processed_at',
        'approved_by',
        'approved_at',
        'completed_at',
        'rejection_reason',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'account_details' => 'array',
            'bank_account_details' => 'array',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_admin_id');
    }
}
