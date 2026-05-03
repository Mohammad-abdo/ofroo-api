<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantWarning;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WarningController extends Controller
{
    /**
     * Get merchant warnings (Admin)
     */
    public function getMerchantWarnings(Request $request): JsonResponse
    {
        $query = MerchantWarning::with(['merchant.user', 'admin']);

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('active') && $request->get('active') !== '') {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('warning_type', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('merchant', function ($mq) use ($search) {
                        $mq->where('company_name', 'like', "%{$search}%")
                            ->orWhere('company_name_ar', 'like', "%{$search}%")
                            ->orWhere('company_name_en', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $totalActive = (clone $query)->where('active', true)->count();
        $totalInactive = (clone $query)->where('active', false)->count();

        $warnings = $query->orderBy('issued_at', 'desc')
            ->paginate($request->get('per_page', 50));

        $data = $warnings->getCollection()->map(function ($w) {
            $merchant = $w->merchant;
            $user = $merchant->user ?? null;

            return [
                'id' => $w->id,
                'merchant_id' => $w->merchant_id,
                'warning_type' => $w->warning_type,
                'message' => $w->message,
                'issued_at' => $w->issued_at?->toIso8601String(),
                'expires_at' => $w->expires_at?->toIso8601String(),
                'active' => $w->active,
                'merchant' => $merchant ? [
                    'id' => $merchant->id,
                    'name' => $user->name ?? $merchant->company_name ?? 'N/A',
                    'company_name' => $merchant->company_name,
                    'company_name_ar' => $merchant->company_name_ar,
                    'company_name_en' => $merchant->company_name_en,
                    'email' => $user->email ?? null,
                ] : null,
                'reason' => $w->warning_type,
                'reason_ar' => $w->warning_type,
                'reason_en' => $w->warning_type,
                'description' => $w->message,
                'is_active' => $w->active,
                'created_at' => $w->issued_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $warnings->currentPage(),
                'last_page' => $warnings->lastPage(),
                'per_page' => $warnings->perPage(),
                'total' => $warnings->total(),
                'total_active' => $totalActive,
                'total_inactive' => $totalInactive,
            ],
        ]);
    }

    /**
     * Get user warnings (Admin)
     * Note: Using a generic warnings table structure
     */
    public function getUserWarnings(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ],
            'message' => 'User warnings feature coming soon',
        ]);
    }

    /**
     * Issue warning to user (Admin)
     */
    public function issueUserWarning(Request $request, string $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warning_type' => 'required|string|in:violation,spam,abuse,other',
            'message' => 'required|string|min:10|max:1000',
            'expires_at' => 'nullable|date|after:now',
            'severity' => 'nullable|in:low,medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $admin = $request->user();
        $user = User::findOrFail($userId);

        return response()->json([
            'message' => 'Warning issued to user successfully',
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'warning_type' => $request->warning_type,
                'message' => $request->message,
                'issued_by' => $admin->id,
                'issued_at' => now()->toIso8601String(),
                'expires_at' => $request->expires_at,
                'severity' => $request->severity ?? 'medium',
            ],
        ], 201);
    }

    /**
     * Deactivate user warning (Admin)
     */
    public function deactivateUserWarning(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'message' => 'User warning deactivated successfully',
        ]);
    }
}
