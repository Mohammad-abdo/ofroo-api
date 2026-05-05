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
        $query = Review::with([
            'user',
            'merchant',
            'offer:id,title,title_ar,title_en,merchant_id',
            'order.items.offer',
        ])
            ->orderBy('created_at', 'desc');

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        if ($request->filled('moderation_action')) {
            $query->where('moderation_action', $request->moderation_action);
        }

        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('notes', 'like', $term)
                    ->orWhere('notes_ar', 'like', $term)
                    ->orWhere('notes_en', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if (in_array($status, ['public', 'approved'], true)) {
                $query->where('moderation_action', 'none')->where('visible_to_public', true);
            } elseif ($status === 'hidden' || $status === 'rejected') {
                $query->where('moderation_action', 'hidden');
            } elseif ($status === 'deleted') {
                $query->where('moderation_action', 'deleted');
            } elseif ($status === 'pending') {
                $query->where('moderation_action', 'none')->where('visible_to_public', false);
            }
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
     * Admin: update review text and rating (moderation state unchanged unless visible_to_public sent).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $admin = $request->user();
        $review = Review::findOrFail($id);

        $validated = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'notes' => 'sometimes|nullable|string|max:10000',
            'notes_ar' => 'sometimes|nullable|string|max:10000',
            'notes_en' => 'sometimes|nullable|string|max:10000',
            'visible_to_public' => 'sometimes|boolean',
        ]);

        if ($validated === []) {
            return response()->json([
                'message' => 'No valid fields to update',
            ], 422);
        }

        $before = $review->only(array_keys($validated));
        $review->update($validated);

        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'review_updated_by_admin',
            Review::class,
            $review->id,
            "Review {$id} updated by admin",
            $before,
            $review->only(array_keys($validated)),
            ['merchant_id' => $review->merchant_id, 'user_id' => $review->user_id]
        );

        $review->load(['user', 'merchant', 'offer:id,title,title_ar,title_en,merchant_id', 'order.items.offer']);

        return response()->json([
            'message' => 'Review updated successfully',
            'data' => $review,
        ]);
    }

    /**
     * Delete or hide review
     */
    public function moderate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:delete,hide',
            'reason' => 'required|string|min:3|max:2000',
        ]);

        $admin = $request->user();
        $review = Review::findOrFail($id);

        $oldAction = $review->moderation_action;

        $review->update([
            'moderation_action' => $request->action === 'delete' ? 'deleted' : 'hidden',
            'moderation_reason' => $request->reason,
            'moderation_at' => now(),
            'moderated_by_admin_id' => $admin->id,
            'visible_to_public' => false,
        ]);

        // Log activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'review_moderated',
            Review::class,
            $review->id,
            "Review {$id} {$request->action}d. Reason: {$request->reason}",
            ['moderation_action' => $oldAction],
            ['moderation_action' => $review->moderation_action],
            ['reason' => $request->reason, 'merchant_id' => $review->merchant_id, 'user_id' => $review->user_id]
        );

        // Send notifications
        // TODO: Dispatch notifications to merchant and user

        $verb = $request->action === 'delete' ? 'deleted' : 'hidden';

        return response()->json([
            'message' => "Review {$verb} successfully",
            'data' => $review->fresh(['user', 'merchant', 'offer:id,title,title_ar,title_en,merchant_id', 'order.items.offer']),
        ]);
    }

    /**
     * Restore review
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $admin = $request->user();
        $review = Review::findOrFail($id);

        $previousModeration = $review->moderation_action;

        $review->update([
            'moderation_action' => 'none',
            'moderation_reason' => null,
            'moderation_at' => null,
            'moderated_by_admin_id' => null,
            'visible_to_public' => true,
        ]);

        // Log activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'review_restored',
            Review::class,
            $review->id,
            "Review {$id} restored",
            ['moderation_action' => $previousModeration],
            ['moderation_action' => 'none']
        );

        return response()->json([
            'message' => 'Review restored successfully',
            'data' => $review->fresh(['user', 'merchant', 'offer:id,title,title_ar,title_en,merchant_id', 'order.items.offer']),
        ]);
    }
}
