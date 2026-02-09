<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminWallet extends Model
{
    protected $fillable = [
        'balance',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id')
            ->where('wallet_type', 'admin');
    }

    /**
     * Get or create admin wallet
     */
    public static function getOrCreate(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            ['balance' => 0, 'currency' => 'KWD']
        );
    }
}
