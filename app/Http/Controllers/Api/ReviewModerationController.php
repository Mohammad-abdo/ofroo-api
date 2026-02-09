<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewModerationController extends Controller
{
    /**
     * List reviews with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::with(['user', 'merchant', 'order'])
            ->orderBy('created_at', 'desc');

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        if ($request->has('moderation_action')) {
            $query->where('moderation_action', $request->moderation_action);
        }

        $reviews = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Delete or hide review
     */
    public function moderate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:delete,hide',
            'reason' => 'required|string|min:10',
        ]);

        $admin = $request->user();
        $review = Review::findOrFail($id);

        $oldAction = $review->moderation_action;

        $review->update([
            'moderation_action' => $request->action === 'delete' ? 'deleted' : 'hidden',
            'moderation_reason' => $request->reason,
            'moderation_at' => now(),
            'moderated_by_admin_id' => $admin->id,
        ]);

        // Log activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'review_moderated',
            Review::class,
            $review->id,
            "Review {$id} {$request->action}ed. Reason: {$request->reason}",
            ['moderation_action' => $oldAction],
            ['moderation_action' => $review->moderation_action],
            ['reason' => $request->reason, 'merchant_id' => $review->merchant_id, 'user_id' => $review->user_id]
        );

        // Send notifications
        // TODO: Dispatch notifications to merchant and user

        return response()->json([
            'message' => "Review {$request->action}d successfully",
            'data' => $review,
        ]);
    }

    /**
     * Restore review
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $admin = $request->user();
        $review = Review::findOrFail($id);

        $review->update([
            'moderation_action' => 'none',
            'moderation_reason' => null,
            'moderation_at' => null,
            'moderated_by_admin_id' => null,
        ]);

        // Log activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'review_restored',
            Review::class,
            $review->id,
            "Review {$id} restored",
            ['moderation_action' => $review->getOriginal('moderation_action')],
            ['moderation_action' => 'none']
        );

        return response()->json([
            'message' => 'Review restored successfully',
            'data' => $review,
        ]);
    }
}
