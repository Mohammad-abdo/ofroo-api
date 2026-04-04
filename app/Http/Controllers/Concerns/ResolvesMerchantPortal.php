<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Merchant;
use Illuminate\Http\Request;

trait ResolvesMerchantPortal
{
    /**
     * Merchant account for the current user (store owner or active staff).
     */
    protected function resolveMerchant(Request $request): Merchant
    {
        $merchant = $request->user()->merchantForPortal();
        if (! $merchant) {
            abort(403, 'Merchant access required.');
        }

        return $merchant;
    }

    /**
     * Mutations (profile, offers, etc.) — only the merchant role, not coupon employees.
     */
    protected function assertMerchantOwner(Request $request): void
    {
        if (! $request->user()->isMerchant()) {
            abort(403, 'Only the store owner can perform this action.');
        }
    }
}
