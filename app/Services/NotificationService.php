<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Offer;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\ApiMediaUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class NotificationService
{
    /**
     * Send in-app notification.
     */
    public function sendNotification($notifiable, string $type, array $data): void
    {
        // Laravel's `notifications` table uses a string UUID primary key — inserts fail without `id`.
        $notifiable->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * Send an FCM push notification to a user's registered devices.
     *
     * The payload is built so that the mobile app always receives, at minimum:
     *  - `offer_id` (when an offer context is provided in $data)
     *  - `title`   (notification title)
     *  - `image`   (full absolute URL suitable for loading straight into
     *              Flutter's Image.network / iOS notification attachments)
     *
     * We keep the HTTP call best-effort (wrapped in try/catch + logged)
     * so push failures never break the calling request (e.g. creating an offer).
     */
    public function sendFcmNotification(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = UserDevice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            return;
        }

        $serverKey = (string) config('services.fcm.server_key', env('FCM_SERVER_KEY', ''));
        if ($serverKey === '') {
            Log::info('FCM server key not configured; skipping push send', [
                'user_id' => $user->id,
                'title' => $title,
            ]);

            return;
        }

        $payload = $this->buildFcmPayload($tokens, $title, $body, $data);

        try {
            Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);
        } catch (Throwable $e) {
            Log::warning('FCM push failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Push a notification about a specific offer. Guarantees that the payload
     * carries `offer_id`, `title`, and a fully-qualified `image` URL so the
     * mobile app can render a rich notification and deep-link to the offer.
     *
     * @param  int|iterable<int>  $userIds
     */
    public function sendOfferPushNotification(Offer $offer, int|iterable $userIds, ?string $customTitle = null, ?string $customBody = null): void
    {
        $ids = is_iterable($userIds) ? collect($userIds) : collect([$userIds]);
        $ids = $ids->map(fn ($v) => (int) $v)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $images = ApiMediaUrl::absoluteList($offer->offer_images ?? []);
        $image = $images[0] ?? '';
        $title = $customTitle !== null && $customTitle !== ''
            ? $customTitle
            : (string) ($offer->title ?? $offer->title_en ?? '');
        $body = $customBody !== null && $customBody !== ''
            ? $customBody
            : (string) ($offer->description ?? $offer->description_en ?? '');

        $data = [
            'offer_id' => (string) $offer->id,
            'title' => $title,
            'image' => $image,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'route' => '/offers/' . $offer->id,
            'type' => 'offer',
        ];

        User::query()->whereIn('id', $ids->all())->get()
            ->each(function (User $user) use ($title, $body, $data) {
                $this->sendFcmNotification($user, $title, $body, $data);
                $this->sendNotification($user, 'offer', $data);
            });
    }

    /**
     * Build an FCM HTTP-v1 legacy payload supporting multiple device tokens.
     * `data` values must all be strings per FCM spec, so we cast everything.
     *
     * @return array<string, mixed>
     */
    protected function buildFcmPayload(array $tokens, string $title, string $body, array $data): array
    {
        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        $image = isset($stringData['image']) && $stringData['image'] !== ''
            ? $stringData['image']
            : null;

        $notification = [
            'title' => $title,
            'body' => $body,
        ];
        if ($image !== null) {
            $notification['image'] = $image;
        }

        return [
            'registration_ids' => array_values($tokens),
            'notification' => $notification,
            'data' => $stringData,
            'android' => [
                'notification' => array_filter([
                    'image' => $image,
                    'click_action' => $stringData['click_action'] ?? null,
                ]),
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'mutable-content' => 1,
                    ],
                ],
                'fcm_options' => array_filter([
                    'image' => $image,
                ]),
            ],
        ];
    }

    /**
     * Send notification to merchant.
     */
    public function notifyMerchant(Merchant $merchant, string $type, array $data): void
    {
        if ($merchant->user) {
            $this->sendNotification($merchant->user, $type, $data);
        }
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead($notifiable, string|int $notificationId): void
    {
        $notifiable->notifications()
            ->where('id', (string) $notificationId)
            ->update(['read_at' => now()]);
    }
}
