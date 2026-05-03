<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantInvoiceController extends Controller
{
    /**
     * Update invoice (Admin)
     */
    public function updateInvoice(Request $request, string $id): JsonResponse
    {
        $invoice = MerchantInvoice::findOrFail($id);

        $request->validate([
            'status' => 'sometimes|in:draft,issued,paid,cancelled',
            'notes' => 'sometimes|string',
        ]);

        $invoice->update($request->only(['status', 'notes']));

        return response()->json([
            'message' => 'Invoice updated successfully',
            'data' => $invoice->fresh(),
        ]);
    }

    /**
     * Delete invoice (Admin)
     */
    public function deleteInvoice(string $id): JsonResponse
    {
        $invoice = MerchantInvoice::findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'Cannot delete paid invoice',
            ], 422);
        }

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }
}
