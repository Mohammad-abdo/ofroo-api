<?php

namespace App\Services;

use App\Models\TaxSetting;
use App\Models\Offer;

class TaxService
{
    /**
     * Calculate tax for amount
     */
    public function calculateTax(float $amount, string $countryCode = 'EG'): array
    {
        $taxSetting = TaxSetting::where('country_code', $countryCode)
            ->where('is_active', true)
            ->first();

        if (!$taxSetting) {
            return [
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total_with_tax' => $amount,
            ];
        }

        $taxAmount = $amount * ($taxSetting->tax_rate / 100);

        return [
            'tax_rate' => $taxSetting->tax_rate,
            'tax_amount' => $taxAmount,
            'total_with_tax' => $amount + $taxAmount,
            'tax_name' => $taxSetting->tax_name,
        ];
    }

    /**
     * Check if category is tax exempt
     */
    public function isTaxExempt(int $categoryId, string $countryCode = 'EG'): bool
    {
        $taxSetting = TaxSetting::where('country_code', $countryCode)
            ->where('is_active', true)
            ->first();

        if (!$taxSetting || !$taxSetting->exempt_categories) {
            return false;
        }

        return in_array($categoryId, $taxSetting->exempt_categories);
    }
}


