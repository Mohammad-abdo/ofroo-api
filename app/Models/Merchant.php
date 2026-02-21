<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'company_name_ar',
        'company_name_en',
        'description',
        'description_ar',
        'description_en',
        'address',
        'address_ar',
        'address_en',
        'phone',
        'whatsapp_number',
        'whatsapp_link',
        'whatsapp_enabled',
        'approved',
        'status',
        'suspended_at',
        'suspended_until',
        'suspension_reason',
        'suspended_by_admin_id',
        'is_blocked',
        'country',
        'city',
        'mall_id',
        'logo_url',
        'category_id',
        'commercial_registration',
        'tax_number',
    ];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'suspended_at' => 'datetime',
            'suspended_until' => 'datetime',
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the merchant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the mall that the merchant belongs to.
     */
    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    /**
     * Get the category that the merchant belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the branches for the merchant.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get store locations (alias for branches - same relation).
     */
    public function storeLocations(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get the offers for the merchant.
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * Get the orders for the merchant.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the reviews for the merchant.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the wallet for the merchant.
     */
    public function wallet()
    {
        return $this->hasOne(MerchantWallet::class);
    }

    /**
     * Get the financial transactions for the merchant.
     */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /**
     * Get the withdrawals for the merchant.
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * Get the expenses for the merchant.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }


    /**
     * Get merchant staff
     */
    public function staff(): HasMany
    {
        return $this->hasMany(MerchantStaff::class);
    }

    /**
     * Get merchant invoices
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(MerchantInvoice::class);
    }

    /**
     * Get merchant PIN
     */
    public function pin()
    {
        return $this->hasOne(MerchantPin::class);
    }

    /**
     * Get activation reports
     */
    public function activationReports(): HasMany
    {
        return $this->hasMany(ActivationReport::class);
    }

    /**
     * Get merchant verification
     */
    public function verification()
    {
        return $this->hasOne(MerchantVerification::class);
    }

    /**
     * Get merchant warnings
     */
    public function warnings(): HasMany
    {
        return $this->hasMany(MerchantWarning::class);
    }

    /**
     * Get regulatory checks
     */
    public function regulatoryChecks(): HasMany
    {
        return $this->hasMany(RegulatoryCheck::class);
    }

    /**
     * Get suspended by admin
     */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by_admin_id');
    }
}
