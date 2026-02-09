<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OfferPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Offer $offer): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isMerchant();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Offer $offer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isMerchant() && $user->merchant->id === $offer->merchant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Offer $offer): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isMerchant() && $user->merchant->id === $offer->merchant_id;
    }

    /**
     * Determine whether the user can enable/disable offers.
     */
    public function toggleStatus(User $user, Offer $offer): bool
    {
        return $user->isAdmin() || ($user->isMerchant() && $user->merchant->id === $offer->merchant_id);
    }
}
