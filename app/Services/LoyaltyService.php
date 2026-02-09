<?php

namespace App\Services;

use App\Models\User;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Get or create loyalty account
     */
    public function getOrCreateLoyaltyAccount(User $user): LoyaltyPoint
    {
        return LoyaltyPoint::firstOrCreate(
            ['user_id' => $user->id],
            [
                'total_points' => 0,
                'tier' => 'bronze',
                'points_used' => 0,
                'points_expired' => 0,
            ]
        );
    }

    /**
     * Calculate tier based on total points
     */
    public function calculateTier(int $totalPoints): string
    {
        if ($totalPoints >= 10000) {
            return 'platinum';
        } elseif ($totalPoints >= 5000) {
            return 'gold';
        } elseif ($totalPoints >= 1000) {
            return 'silver';
        }
        return 'bronze';
    }

    /**
     * Award points for order
     */
    public function awardPointsForOrder(Order $order): void
    {
        $user = $order->user;
        $loyaltyAccount = $this->getOrCreateLoyaltyAccount($user);

        // Calculate points (1 point per 1 KWD spent)
        $points = (int) floor($order->total_amount);
        $expiresAt = now()->addYear(); // Points expire after 1 year

        DB::beginTransaction();
        try {
            // Add points
            $loyaltyAccount->increment('total_points', $points);

            // Create transaction
            LoyaltyTransaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'transaction_type' => 'earned',
                'points' => $points,
                'description' => "Points earned from Order #{$order->id}",
                'expires_at' => $expiresAt,
            ]);

            // Update tier if needed
            $newTier = $this->calculateTier($loyaltyAccount->total_points);
            if ($newTier !== $loyaltyAccount->tier) {
                $loyaltyAccount->update(['tier' => $newTier]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Redeem points
     */
    public function redeemPoints(User $user, int $points, string $description = null): bool
    {
        $loyaltyAccount = $this->getOrCreateLoyaltyAccount($user);

        if ($loyaltyAccount->total_points < $points) {
            return false;
        }

        DB::beginTransaction();
        try {
            $loyaltyAccount->decrement('total_points', $points);
            $loyaltyAccount->increment('points_used', $points);

            LoyaltyTransaction::create([
                'user_id' => $user->id,
                'transaction_type' => 'redeemed',
                'points' => -$points,
                'description' => $description ?? "Points redeemed",
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Get tier benefits
     */
    public function getTierBenefits(string $tier): array
    {
        return match($tier) {
            'platinum' => [
                'discount_percent' => 15,
                'free_shipping' => true,
                'priority_support' => true,
            ],
            'gold' => [
                'discount_percent' => 10,
                'free_shipping' => true,
                'priority_support' => false,
            ],
            'silver' => [
                'discount_percent' => 5,
                'free_shipping' => false,
                'priority_support' => false,
            ],
            default => [
                'discount_percent' => 0,
                'free_shipping' => false,
                'priority_support' => false,
            ],
        };
    }
}


