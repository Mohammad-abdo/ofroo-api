<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Merchant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class AdminNotificationController extends Controller
{
    /**
     * Get all notifications (Admin)
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $query = AdminNotification::with('creator');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('target_audience')) {
            $query->where('target_audience', $request->target_audience);
        }

        if ($request->has('is_sent')) {
            $query->where('is_sent', $request->boolean('is_sent'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(1, min(200, $perPage));

        if (! $request->boolean('include_inbox')) {
            $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'data' => $notifications->getCollection(),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ]);
        }

        $take = max(1, min(100, $perPage));
        $broadcast = (clone $query)->orderBy('created_at', 'desc')->limit($take)->get();

        $inboxQuery = $request->user()->notifications()->orderBy('created_at', 'desc')->limit($take);
        if ($request->has('type')) {
            $inboxQuery->where('type', $request->type);
        }
        $inboxRows = $inboxQuery->get();

        $rows = collect();
        foreach ($broadcast as $m) {
            $row = $m->toArray();
            $row['notification_source'] = 'broadcast';
            $rows->push($row);
        }
        foreach ($inboxRows as $n) {
            $data = is_string($n->data) ? json_decode($n->data, true) : $n->data;
            $data = is_array($data) ? $data : [];
            $rows->push([
                'id' => $n->id,
                'notification_source' => 'inbox',
                'title' => $data['title'] ?? $data['title_en'] ?? '',
                'title_ar' => $data['title_ar'] ?? $data['title'] ?? '',
                'title_en' => $data['title_en'] ?? $data['title'] ?? '',
                'message' => $data['message'] ?? $data['message_en'] ?? '',
                'message_ar' => $data['message_ar'] ?? $data['message'] ?? '',
                'message_en' => $data['message_en'] ?? $data['message'] ?? '',
                'type' => $data['type'] ?? $n->type ?? 'info',
                'read_at' => $n->read_at ? $n->read_at->toIso8601String() : null,
                'created_at' => $n->created_at ? $n->created_at->toIso8601String() : null,
                'sent_at' => $n->created_at ? $n->created_at->toIso8601String() : null,
                'is_sent' => true,
                'creator' => null,
            ]);
        }

        $sorted = $rows->sortByDesc(function ($row) {
            $t = $row['created_at'] ?? null;
            if ($t instanceof \DateTimeInterface) {
                return $t->getTimestamp();
            }

            return strtotime((string) $t) ?: 0;
        })->take($take)->values();

        return response()->json([
            'data' => $sorted,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $take,
                'total' => $sorted->count(),
            ],
        ]);
    }

    /**
     * Get single notification (Admin)
     */
    public function getNotification(string $id): JsonResponse
    {
        $notification = AdminNotification::with('creator')
            ->findOrFail($id);

        return response()->json([
            'data' => $notification,
        ]);
    }

    /**
     * Create notification (Admin)
     */
    public function createNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'message' => 'required|string',
            'message_ar' => 'nullable|string',
            'message_en' => 'nullable|string',
            'type' => 'nullable|in:info,success,warning,error,promotion,system',
            'target_audience' => 'required|in:all,users,merchants,admins,specific',
            'target_user_ids' => 'nullable|array',
            'target_merchant_ids' => 'nullable|array',
            'action_url' => 'nullable|string|max:500',
            'action_text' => 'nullable|string|max:100',
            'image_url' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $notification = AdminNotification::create([
            'title' => $request->title,
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'message' => $request->message,
            'message_ar' => $request->message_ar,
            'message_en' => $request->message_en,
            'type' => $request->type ?? 'info',
            'target_audience' => $request->target_audience,
            'target_user_ids' => $request->target_user_ids,
            'target_merchant_ids' => $request->target_merchant_ids,
            'action_url' => $request->action_url,
            'action_text' => $request->action_text,
            'image_url' => $request->image_url,
            'scheduled_at' => $request->scheduled_at,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Notification created successfully',
            'data' => $notification->load('creator'),
        ], 201);
    }

    /**
     * Update notification (Admin)
     */
    public function updateNotification(Request $request, string $id): JsonResponse
    {
        $notification = AdminNotification::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'title_ar' => 'sometimes|string|max:255',
            'title_en' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'message_ar' => 'sometimes|string',
            'message_en' => 'sometimes|string',
            'type' => 'sometimes|in:info,success,warning,error,promotion,system',
            'target_audience' => 'sometimes|in:all,users,merchants,admins,specific',
            'target_user_ids' => 'sometimes|array',
            'target_merchant_ids' => 'sometimes|array',
            'action_url' => 'sometimes|string|max:500',
            'action_text' => 'sometimes|string|max:100',
            'image_url' => 'sometimes|string|max:500',
            'scheduled_at' => 'sometimes|date|after:now',
        ]);

        if ($notification->is_sent) {
            return response()->json([
                'message' => 'Cannot update notification that has already been sent',
            ], 422);
        }

        $notification->update($request->all());

        return response()->json([
            'message' => 'Notification updated successfully',
            'data' => $notification->fresh()->load('creator'),
        ]);
    }

    /**
     * Mark notification as read (Admin)
     */
    public function markNotificationAsRead(Request $request, string $id): JsonResponse
    {
        $broadcast = AdminNotification::find($id);
        if ($broadcast) {
            if (! $broadcast->read_at) {
                $broadcast->update(['read_at' => now()]);
            }

            return response()->json([
                'message' => 'Notification marked as read',
                'data' => $broadcast->fresh(),
            ]);
        }

        $inbox = $request->user()->notifications()->where('id', $id)->first();
        if ($inbox) {
            $inbox->markAsRead();

            return response()->json([
                'message' => 'Notification marked as read',
            ]);
        }

        return response()->json([
            'message' => 'Notification not found',
        ], 404);
    }

    /**
     * Mark all notifications as read (Admin)
     */
    public function markAllNotificationsAsRead(Request $request): JsonResponse
    {
        $updated = AdminNotification::whereNull('read_at')
            ->update(['read_at' => now()]);

        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read',
            'data' => ['updated_count' => $updated],
        ]);
    }

    /**
     * Delete notification (Admin): broadcast row (admin_notifications) or current user's database notification (inbox).
     */
    public function deleteNotification(Request $request, string $id): JsonResponse
    {
        $broadcast = AdminNotification::find($id);
        if ($broadcast) {
            $broadcast->delete();

            return response()->json([
                'message' => 'Notification deleted successfully',
            ]);
        }

        $inbox = $request->user()->notifications()->where('id', $id)->first();
        if ($inbox) {
            $inbox->delete();

            return response()->json([
                'message' => 'Notification deleted successfully',
            ]);
        }

        return response()->json([
            'message' => 'Notification not found',
        ], 404);
    }

    /**
     * Send notification (Admin)
     */
    public function sendNotification(string $id): JsonResponse
    {
        $notification = AdminNotification::findOrFail($id);

        if ($notification->is_sent) {
            return response()->json([
                'message' => 'Notification has already been sent',
            ], 422);
        }

        if ($notification->scheduled_at && $notification->scheduled_at->isFuture()) {
            return response()->json([
                'message' => 'This notification is scheduled for a future time and cannot be sent manually yet.',
                'message_ar' => 'هذا الإشعار مجدول لوقت لاحق ولا يمكن إرساله يدوياً الآن.',
                'message_en' => 'This notification is scheduled for a future time and cannot be sent manually yet.',
                'data' => ['scheduled_at' => $notification->scheduled_at->toIso8601String()],
            ], 422);
        }

        $recipientIds = $this->adminBroadcastRecipientUserIds($notification);
        if ($recipientIds->isEmpty()) {
            return response()->json([
                'message' => 'No recipients matched this notification audience.',
                'message_ar' => 'لا يوجد مستخدمون مطابقون لجمهور هذا الإشعار.',
                'message_en' => 'No recipients matched this notification audience.',
                'data' => [
                    'target_audience' => $notification->target_audience,
                    'target_user_ids' => $notification->target_user_ids ?? [],
                    'target_merchant_ids' => $notification->target_merchant_ids ?? [],
                ],
            ], 422);
        }

        /** @var NotificationService $notificationService */
        $notificationService = app(NotificationService::class);

        $inAppPayload = [
            'title' => (string) ($notification->title ?? ''),
            'title_ar' => (string) ($notification->title_ar ?? ''),
            'title_en' => (string) ($notification->title_en ?? ''),
            'message' => (string) ($notification->message ?? ''),
            'message_ar' => (string) ($notification->message_ar ?? ''),
            'message_en' => (string) ($notification->message_en ?? ''),
            'type' => (string) ($notification->type ?? 'info'),
            'admin_notification_id' => (string) $notification->id,
            'action_url' => (string) ($notification->action_url ?? ''),
            'action_text' => (string) ($notification->action_text ?? ''),
            'image_url' => (string) ($notification->image_url ?? ''),
        ];

        $recipientCount = 0;
        User::query()
            ->whereIn('id', $recipientIds->all())
            ->where(function ($q) {
                $q->whereNull('is_blocked')->orWhere('is_blocked', false);
            })
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($notificationService, $notification, $inAppPayload, &$recipientCount) {
                foreach ($users as $user) {
                    $notificationService->sendNotification($user, 'admin_broadcast', $inAppPayload);

                    $lang = strtolower((string) ($user->language ?? 'ar'));
                    $title = $lang === 'en'
                        ? (string) (($notification->title_en ?: $notification->title) ?? '')
                        : (string) (($notification->title_ar ?: $notification->title) ?? '');
                    $body = $lang === 'en'
                        ? (string) (($notification->message_en ?: $notification->message) ?? '')
                        : (string) (($notification->message_ar ?: $notification->message) ?? '');

                    if (($user->push_notifications ?? true) === true) {
                        $notificationService->sendFcmNotification($user, $title, $body, array_merge($inAppPayload, [
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'route' => '/notifications',
                        ]));
                    }
                    $recipientCount++;
                }
            });

        $notification->update([
            'is_sent' => true,
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notification sent successfully',
            'message_ar' => 'تم إرسال الإشعار بنجاح',
            'message_en' => 'Notification sent successfully',
            'data' => [
                'notification' => $notification->fresh(),
                'recipient_count' => $recipientCount,
            ],
        ]);
    }

    /**
     * Resolve which user IDs should receive an admin broadcast.
     *
     * @return Collection<int, int>
     */
    private function adminBroadcastRecipientUserIds(AdminNotification $notification): Collection
    {
        $audience = (string) ($notification->target_audience ?? 'all');

        $unblocked = fn (Builder $q) => $q->where(function ($w) {
            $w->whereNull('is_blocked')->orWhere('is_blocked', false);
        });

        switch ($audience) {
            case 'users':
                return User::query()
                    ->tap($unblocked)
                    ->whereHas('role', fn ($r) => $r->where('name', 'user'))
                    ->pluck('id')->unique()->values();

            case 'merchants':
                return User::query()
                    ->tap($unblocked)
                    ->whereHas('role', fn ($r) => $r->where('name', 'merchant'))
                    ->pluck('id')->unique()->values();

            case 'admins':
                return User::query()
                    ->tap($unblocked)
                    ->whereHas('role', fn ($r) => $r->whereIn('name', [
                        'admin', 'employee', 'data_entry', 'accountant',
                    ]))
                    ->pluck('id')->unique()->values();

            case 'specific':
                $ids = collect($notification->target_user_ids ?? [])
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();

                $merchantIds = collect($notification->target_merchant_ids ?? [])
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();

                if ($merchantIds->isNotEmpty()) {
                    $ownerUserIds = Merchant::query()
                        ->whereIn('id', $merchantIds->all())
                        ->whereNotNull('user_id')
                        ->pluck('user_id');
                    $ids = $ids->merge($ownerUserIds)->unique()->values();
                }

                return $ids;

            case 'all':
            default:
                return User::query()
                    ->tap($unblocked)
                    ->whereHas('role', fn ($r) => $r->whereIn('name', ['user', 'merchant']))
                    ->pluck('id')
                    ->unique()
                    ->values();
        }
    }
}
