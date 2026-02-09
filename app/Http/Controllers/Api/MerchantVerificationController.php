<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MerchantVerificationController extends Controller
{
    /**
     * Admin: List all verifications
     */
    public function index(Request $request): JsonResponse
    {
        $query = MerchantVerification::with(['merchant', 'reviewedBy'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $verifications = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'data' => $verifications->items(),
            'meta' => [
                'current_page' => $verifications->currentPage(),
                'last_page' => $verifications->lastPage(),
                'per_page' => $verifications->perPage(),
                'total' => $verifications->total(),
            ],
        ]);
    }

    /**
     * Get merchant verification status
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $verification = MerchantVerification::where('merchant_id', $merchant->id)->first();

        return response()->json([
            'data' => $verification,
        ]);
    }

    /**
     * Upload verification documents
     */
    public function uploadDocuments(Request $request): JsonResponse
    {
        $request->validate([
            'business_registration_doc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'id_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'tax_registration_doc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'proof_of_address' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'additional_docs' => 'nullable|array',
            'additional_docs.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $verification = MerchantVerification::firstOrCreate(
            ['merchant_id' => $merchant->id],
            ['status' => 'pending']
        );

        $paths = [];

        if ($request->hasFile('business_registration_doc')) {
            $path = $request->file('business_registration_doc')->store("verifications/{$merchant->id}", 'private');
            $verification->business_registration_doc_path = $path;
        }

        if ($request->hasFile('id_card')) {
            $path = $request->file('id_card')->store("verifications/{$merchant->id}", 'private');
            $verification->id_card_path = $path;
        }

        if ($request->hasFile('tax_registration_doc')) {
            $path = $request->file('tax_registration_doc')->store("verifications/{$merchant->id}", 'private');
            $verification->tax_registration_doc_path = $path;
        }

        if ($request->hasFile('proof_of_address')) {
            $path = $request->file('proof_of_address')->store("verifications/{$merchant->id}", 'private');
            $verification->proof_of_address_path = $path;
        }

        if ($request->hasFile('additional_docs')) {
            $additionalDocs = [];
            foreach ($request->file('additional_docs') as $file) {
                $path = $file->store("verifications/{$merchant->id}/additional", 'private');
                $additionalDocs[] = $path;
            }
            $verification->additional_docs = $additionalDocs;
        }

        $verification->status = 'pending';
        $verification->save();

        // Log activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $user->id,
            'verification_documents_uploaded',
            MerchantVerification::class,
            $verification->id,
            "Verification documents uploaded for merchant {$merchant->id}",
        );

        return response()->json([
            'message' => 'Documents uploaded successfully. Waiting for admin review.',
            'data' => $verification,
        ]);
    }

    /**
     * Admin: Review verification
     */
    public function review(Request $request, string $merchantId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:verified,rejected',
            'rejection_reason' => 'required_if:status,rejected|string',
        ]);

        $admin = $request->user();
        $merchant = Merchant::findOrFail($merchantId);
        $verification = MerchantVerification::where('merchant_id', $merchant->id)->firstOrFail();

        $verification->update([
            'status' => $request->status,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->status === 'rejected' ? $request->rejection_reason : null,
        ]);

        // Log activity
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'verification_reviewed',
            MerchantVerification::class,
            $verification->id,
            "Verification {$request->status} for merchant {$merchant->id}",
            ['status' => 'pending'],
            ['status' => $request->status],
            ['rejection_reason' => $request->rejection_reason]
        );

        // Send notification
        // TODO: Dispatch notification

        return response()->json([
            'message' => "Verification {$request->status}",
            'data' => $verification,
        ]);
    }

    /**
     * Download verification document (Admin only)
     */
    public function downloadDocument(Request $request, string $merchantId, string $documentType): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $merchant = Merchant::findOrFail($merchantId);
        $verification = MerchantVerification::where('merchant_id', $merchant->id)->firstOrFail();

        $path = match($documentType) {
            'business_registration' => $verification->business_registration_doc_path,
            'id_card' => $verification->id_card_path,
            'tax_registration' => $verification->tax_registration_doc_path,
            'proof_of_address' => $verification->proof_of_address_path,
            default => null,
        };

        if (!$path || !Storage::disk('private')->exists($path)) {
            abort(404, 'Document not found');
        }

        // Log access
        $activityLogService = app(\App\Services\ActivityLogService::class);
        $activityLogService->log(
            $request->user()->id,
            'verification_document_accessed',
            MerchantVerification::class,
            $verification->id,
            "Accessed {$documentType} for merchant {$merchant->id}",
        );

        return Storage::disk('private')->download($path);
    }
}
