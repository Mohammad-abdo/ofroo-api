<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantWallet extends Model
{
    protected $fillable = [
        'merchant_id',
        'balance',
        'reserved_balance',
        'currency',
        'is_frozen',
        'pending_balance',
        'total_earned',
        'total_withdrawn',
        'total_commission_paid',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'reserved_balance' => 'decimal:2',
            'is_frozen' => 'boolean',
            'pending_balance' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
            'total_commission_paid' => 'decimal:2',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id')
            ->where('wallet_type', 'merchant');
    }

    /**
     * Get available balance (balance - reserved)
     */
    public function getAvailableBalanceAttribute(): float
    {
        return max(0, $this->balance - $this->reserved_balance);
    }
}
