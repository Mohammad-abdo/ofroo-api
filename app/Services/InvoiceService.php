<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantInvoice;
use App\Models\Order;
use App\Models\Coupon;
use App\Services\FinancialService;
use App\Services\PdfService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    protected FinancialService $financialService;
    protected PdfService $pdfService;

    public function __construct(FinancialService $financialService, PdfService $pdfService)
    {
        $this->financialService = $financialService;
        $this->pdfService = $pdfService;
    }

    /**
     * Generate monthly invoice for merchant (Use InvoiceGenerationService)
     */
    public function generateMonthlyInvoice(Merchant $merchant, int $year, int $month): MerchantInvoice
    {
        $invoiceGenerationService = app(InvoiceGenerationService::class);
        return $invoiceGenerationService->generateMonthlyInvoice($merchant, $year, $month);
    }

    /**
     * Generate invoice number
     */
    protected function generateInvoiceNumber(Merchant $merchant, int $year, int $month): string
    {
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        return 'INV-' . $merchant->id . '-' . $year . $monthStr . '-' . Str::random(6);
    }

    /**
     * Generate invoice PDF
     */
    protected function generateInvoicePdf(MerchantInvoice $invoice): string
    {
        // Use PdfService to generate invoice PDF
        // This is a placeholder - implement actual PDF generation
        $filename = 'invoices/' . $invoice->invoice_number . '.pdf';
        // TODO: Generate PDF using PdfService
        return $filename;
    }

    /**
     * Get merchant invoices
     */
    public function getMerchantInvoices(Merchant $merchant, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = MerchantInvoice::where('merchant_id', $merchant->id);

        if (isset($filters['year'])) {
            $query->whereYear('invoice_date', $filters['year']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('invoice_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }
}

