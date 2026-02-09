<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class MerchantPin extends Model
{
    protected $fillable = [
        'merchant_id',
        'pin_hash',
        'biometric_enabled',
        'failed_attempts',
        'locked_until',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'biometric_enabled' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Set PIN
     */
    public function setPin(string $pin): void
    {
        $this->pin_hash = Hash::make($pin);
        $this->save();
    }

    /**
     * Verify PIN
     */
    public function verifyPin(string $pin): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        if (Hash::check($pin, $this->pin_hash)) {
            $this->failed_attempts = 0;
            $this->last_login_at = now();
            $this->save();
            return true;
        }

        $this->increment('failed_attempts');
        if ($this->failed_attempts >= 5) {
            $this->locked_until = now()->addMinutes(30);
            $this->save();
        }

        return false;
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }
}
