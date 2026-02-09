<?php

namespace App\Services;

use App\Models\User;
use App\Models\Merchant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\Notification as LaravelNotification;

class NotificationService
{
    /**
     * Send in-app notification
     */
    public function sendNotification($notifiable, string $type, array $data): void
    {
        $notifiable->notifications()->create([
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * Send FCM push notification
     */
    public function sendFcmNotification(User $user, string $title, string $body, array $data = []): void
    {
        // TODO: Implement FCM push notification
        // This requires FCM server key and user device tokens
        // Use firebase/php-jwt or similar package
    }

    /**
     * Send notification to merchant
     */
    public function notifyMerchant(Merchant $merchant, string $type, array $data): void
    {
        $this->sendNotification($merchant->user, $type, $data);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notifiable, int $notificationId): void
    {
        $notifiable->notifications()
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);
    }
}


