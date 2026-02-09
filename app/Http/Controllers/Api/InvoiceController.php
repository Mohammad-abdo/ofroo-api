<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantInvoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Get merchant invoices
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = Merchant::where('user_id', $user->id)->firstOrFail();

        $invoices = $this->invoiceService->getMerchantInvoices($merchant, [
            'year' => $request->get('year'),
            'status' => $request->get('status'),
            'per_page' => $request->get('per_page', 15),
        ]);

        return response()->json([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Get invoice details
     */
    public function show(string $id): JsonResponse
    {
        $invoice = MerchantInvoice::with('merchant')->findOrFail($id);

        return response()->json([
            'data' => $invoice,
        ]);
    }

    /**
     * Download invoice PDF
     */
    public function downloadPdf(string $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $invoice = MerchantInvoice::findOrFail($id);

        if (!$invoice->pdf_path) {
            abort(404, 'Invoice PDF not found');
        }

        return response()->download(
            storage_path('app/public/' . $invoice->pdf_path),
            $invoice->invoice_number . '.pdf'
        );
    }

    /**
     * Generate monthly invoice (Admin/Merchant)
     */
    public function generateMonthly(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => 'required|exists:merchants,id',
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $merchant = Merchant::findOrFail($request->merchant_id);
        $invoice = $this->invoiceService->generateMonthlyInvoice(
            $merchant,
            $request->year,
            $request->month
        );

        return response()->json([
            'message' => 'Invoice generated successfully',
            'data' => $invoice,
        ], 201);
    }

    /**
     * Generate invoice for order
     */
    public function generateOrderInvoice(Request $request, string $orderId): JsonResponse
    {
        $order = \App\Models\Order::findOrFail($orderId);
        $invoiceService = app(\App\Services\InvoiceGenerationService::class);
        $invoice = $invoiceService->generateOrderInvoice($order);

        return response()->json([
            'message' => 'Invoice generated successfully',
            'data' => $invoice,
        ], 201);
    }

    /**
     * Re-issue invoice
     */
    public function reissue(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $admin = $request->user();
        $invoice = MerchantInvoice::findOrFail($id);
        $invoiceService = app(\App\Services\InvoiceGenerationService::class);
        $invoice = $invoiceService->reissueInvoice($invoice, $admin, $request->reason);

        return response()->json([
            'message' => 'Invoice re-issued successfully',
            'data' => $invoice,
        ]);
    }

    /**
     * Cancel invoice
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $admin = $request->user();
        $invoice = MerchantInvoice::findOrFail($id);
        $invoiceService = app(\App\Services\InvoiceGenerationService::class);
        $invoiceService->cancelInvoice($invoice, $admin, $request->reason);

        return response()->json([
            'message' => 'Invoice cancelled successfully',
        ]);
    }
}
