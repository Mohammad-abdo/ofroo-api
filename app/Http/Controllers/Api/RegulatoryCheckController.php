<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\RegulatoryCheck;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegulatoryCheckController extends Controller
{
    /**
     * Admin: List all regulatory checks
     */
    public function index(Request $request): JsonResponse
    {
        $query = RegulatoryCheck::with(['merchant'])
            ->orderBy('checked_at', 'desc');

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('check_type')) {
            $query->where('check_type', $request->check_type);
        }

        if ($request->has('result')) {
            $query->where('result', $request->result);
        }

        if ($request->has('from')) {
            $query->where('checked_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('checked_at', '<=', $request->to);
        }

        $checks = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'data' => $checks->items(),
            'meta' => [
                'current_page' => $checks->currentPage(),
                'last_page' => $checks->lastPage(),
                'per_page' => $checks->perPage(),
                'total' => $checks->total(),
            ],
        ]);
    }

    /**
     * Admin: Create regulatory check
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'check_type' => 'required|in:compliance,kyc,financial,operational,legal',
            'result' => 'required|in:passed,failed,pending,requires_review',
            'details' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $admin = $request->user();
        $merchant = Merchant::findOrFail($request->merchant_id);

        $check = RegulatoryCheck::create([
            'merchant_id' => $merchant->id,
            'check_type' => $request->check_type,
            'result' => $request->result,
            'details' => $request->details ?? [],
            'notes' => $request->notes,
            'checked_at' => now(),
            'checked_by_admin_id' => $admin->id,
        ]);

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'regulatory_check_created',
            RegulatoryCheck::class,
            $check->id,
            "Regulatory check ({$request->check_type}) created for merchant {$merchant->id}. Result: {$request->result}",
            null,
            ['check_type' => $request->check_type, 'result' => $request->result],
            ['merchant_id' => $merchant->id]
        );

        return response()->json([
            'message' => 'Regulatory check created successfully',
            'data' => $check->load('merchant'),
        ], 201);
    }

    /**
     * Admin: Get specific regulatory check
     */
    public function show(string $id): JsonResponse
    {
        $check = RegulatoryCheck::with(['merchant'])->findOrFail($id);

        return response()->json([
            'data' => $check,
        ]);
    }

    /**
     * Admin: Update regulatory check
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'result' => 'sometimes|in:passed,failed,pending,requires_review',
            'details' => 'sometimes|array',
            'notes' => 'sometimes|string',
        ]);

        $admin = $request->user();
        $check = RegulatoryCheck::findOrFail($id);
        $oldResult = $check->result;

        $check->update($request->only(['result', 'details', 'notes']));

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'regulatory_check_updated',
            RegulatoryCheck::class,
            $check->id,
            "Regulatory check updated. Result changed from {$oldResult} to {$check->result}",
            ['result' => $oldResult],
            ['result' => $check->result],
            ['merchant_id' => $check->merchant_id]
        );

        return response()->json([
            'message' => 'Regulatory check updated successfully',
            'data' => $check->fresh()->load('merchant'),
        ]);
    }

    /**
     * Admin: Delete regulatory check
     */
    public function destroy(string $id): JsonResponse
    {
        $admin = request()->user();
        $check = RegulatoryCheck::findOrFail($id);
        $merchantId = $check->merchant_id;

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'regulatory_check_deleted',
            RegulatoryCheck::class,
            $check->id,
            "Regulatory check deleted for merchant {$merchantId}",
            ['check_type' => $check->check_type, 'result' => $check->result],
            null,
            ['merchant_id' => $merchantId]
        );

        $check->delete();

        return response()->json([
            'message' => 'Regulatory check deleted successfully',
        ]);
    }

    /**
     * Admin: Get merchant regulatory checks
     */
    public function getMerchantChecks(string $merchantId): JsonResponse
    {
        $merchant = Merchant::findOrFail($merchantId);
        $checks = RegulatoryCheck::where('merchant_id', $merchant->id)
            ->orderBy('checked_at', 'desc')
            ->get();

        return response()->json([
            'data' => $checks,
            'merchant' => [
                'id' => $merchant->id,
                'company_name' => $merchant->company_name,
            ],
        ]);
    }

    /**
     * Admin: Run automated compliance check
     */
    public function runAutomatedCheck(Request $request, string $merchantId): JsonResponse
    {
        $request->validate([
            'check_type' => 'required|in:compliance,kyc,financial,operational,legal',
        ]);

        $admin = $request->user();
        $merchant = Merchant::findOrFail($merchantId);

        // Run automated checks based on type
        $result = 'passed';
        $details = [];
        $notes = '';

        switch ($request->check_type) {
            case 'kyc':
                $verification = $merchant->verifications()->latest()->first();
                if (!$verification || $verification->status !== 'verified') {
                    $result = 'failed';
                    $notes = 'KYC verification incomplete or not verified';
                }
                break;

            case 'financial':
                $wallet = $merchant->wallet;
                if ($wallet && $wallet->is_frozen) {
                    $result = 'failed';
                    $notes = 'Merchant wallet is frozen';
                }
                // Check for suspicious transactions
                $suspiciousTransactions = $merchant->financialTransactions()
                    ->where('amount', '>', 10000)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();
                if ($suspiciousTransactions > 10) {
                    $result = 'requires_review';
                    $notes = "High number of large transactions detected: {$suspiciousTransactions}";
                }
                break;

            case 'operational':
                $activeOffers = $merchant->offers()->where('status', 'active')->count();
                if ($activeOffers === 0) {
                    $result = 'requires_review';
                    $notes = 'No active offers found';
                }
                break;

            case 'compliance':
                $activeWarnings = $merchant->warnings()->where('active', true)->count();
                if ($activeWarnings > 0) {
                    $result = 'failed';
                    $notes = "Merchant has {$activeWarnings} active warning(s)";
                }
                break;
        }

        $check = RegulatoryCheck::create([
            'merchant_id' => $merchant->id,
            'check_type' => $request->check_type,
            'result' => $result,
            'details' => $details,
            'notes' => $notes,
            'checked_at' => now(),
            'checked_by_admin_id' => $admin->id,
        ]);

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'automated_regulatory_check',
            RegulatoryCheck::class,
            $check->id,
            "Automated {$request->check_type} check run for merchant {$merchant->id}. Result: {$result}",
            null,
            ['check_type' => $request->check_type, 'result' => $result],
            ['merchant_id' => $merchant->id]
        );

        return response()->json([
            'message' => 'Automated check completed',
            'data' => $check->load('merchant'),
        ], 201);
    }
}