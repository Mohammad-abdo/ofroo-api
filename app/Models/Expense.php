<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'merchant_id',
        'expense_type',
        'expense_type_ar',
        'expense_type_en',
        'category',
        'category_ar',
        'category_en',
        'amount',
        'description',
        'description_ar',
        'description_en',
        'expense_date',
        'receipt_url',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
