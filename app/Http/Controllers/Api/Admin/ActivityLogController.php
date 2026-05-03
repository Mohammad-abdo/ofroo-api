<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Get activity logs
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user')
            ->orderBy('created_at', 'desc');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        if ($request->filled('actor_role')) {
            $query->where('actor_role', $request->actor_role);
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->target_type);
        }

        if ($request->filled('target_id')) {
            $query->where('target_id', $request->target_id);
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->filled('model_id')) {
            $query->where('model_id', $request->model_id);
        }

        $logs = $query->paginate($request->get('per_page', 50));

        $data = $logs->getCollection()->map(function ($log) {
            $user = $log->user;

            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'actor_role' => $log->actor_role,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'metadata' => $log->metadata,
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'created_at' => $log->created_at ? $log->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get single activity log (Admin)
     */
    public function getActivityLog(string $id): JsonResponse
    {
        $log = ActivityLog::with('user')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user' => [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ],
                'created_at' => $log->created_at ? $log->created_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Delete activity log (Admin)
     */
    public function deleteActivityLog(string $id): JsonResponse
    {
        $log = ActivityLog::findOrFail($id);
        $log->delete();

        return response()->json([
            'message' => 'Activity log deleted successfully',
        ]);
    }

    /**
     * Clear activity logs (Admin)
     */
    public function clearActivityLogs(): JsonResponse
    {
        $deleted = ActivityLog::where('created_at', '<', now()->subDays(90))->delete();

        return response()->json([
            'message' => 'Activity logs cleared successfully',
            'deleted_count' => $deleted,
        ]);
    }
}
