<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantVerification extends Model
{
    protected $fillable = [
        'merchant_id',
        'business_registration_doc_path',
        'id_card_path',
        'tax_registration_doc_path',
        'proof_of_address_path',
        'additional_docs',
        'status',
        'reviewed_by_admin_id',
        'reviewed_at',
        'rejection_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'additional_docs' => 'array',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }
}
