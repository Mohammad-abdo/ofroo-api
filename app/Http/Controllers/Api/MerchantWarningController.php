<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantWarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantWarningController extends Controller
{
    /**
     * Get merchant warnings
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $warnings = MerchantWarning::where('merchant_id', $merchant->id)
            ->where('active', true)
            ->with('admin')
            ->orderBy('issued_at', 'desc')
            ->get();

        return response()->json([
            'data' => $warnings,
        ]);
    }

    /**
     * Admin: Issue warning
     */
    public function issue(Request $request, string $merchantId): JsonResponse
    {
        $request->validate([
            'warning_type' => 'required|string',
            'message' => 'required|string|min:10',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $admin = $request->user();
        $merchant = Merchant::findOrFail($merchantId);

        $warning = MerchantWarning::create([
            'merchant_id' => $merchant->id,
            'admin_id' => $admin->id,
            'warning_type' => $request->warning_type,
            'message' => $request->message,
            'issued_at' => now(),
            'expires_at' => $request->expires_at ? now()->parse($request->expires_at) : null,
            'active' => true,
        ]);

        // Log activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'warning_issued',
            MerchantWarning::class,
            $warning->id,
            "Warning issued to merchant {$merchant->id}. Type: {$request->warning_type}",
            null,
            ['warning_id' => $warning->id],
            ['warning_type' => $request->warning_type, 'expires_at' => $request->expires_at]
        );

        // Send notification
        // TODO: Dispatch notification

        return response()->json([
            'message' => 'Warning issued successfully',
            'data' => $warning,
        ], 201);
    }

    /**
     * Admin: Deactivate warning
     */
    public function deactivate(Request $request, string $id): JsonResponse
    {
        $warning = MerchantWarning::findOrFail($id);
        $warning->update(['active' => false]);

        // Log activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $request->user()->id,
            'warning_deactivated',
            MerchantWarning::class,
            $warning->id,
            "Warning deactivated for merchant {$warning->merchant_id}",
            ['active' => true],
            ['active' => false]
        );

        return response()->json([
            'message' => 'Warning deactivated successfully',
        ]);
    }
}
