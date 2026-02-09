<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantInvoice;
use App\Models\Order;
use App\Models\User;
use App\Services\PdfService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceGenerationService
{
    protected PdfService $pdfService;
    protected TaxService $taxService;

    public function __construct(PdfService $pdfService, TaxService $taxService)
    {
        $this->pdfService = $pdfService;
        $this->taxService = $taxService;
    }

    /**
     * Generate invoice for order
     */
    public function generateOrderInvoice(Order $order, ?User $customer = null): MerchantInvoice
    {
        $merchant = $order->merchant;
        $customer = $customer ?? $order->user;

        // Calculate tax
        $taxInfo = $this->taxService->calculateTax($order->total_amount);
        $taxAmount = $taxInfo['tax_amount'] ?? 0;

        // Generate invoice number
        $invoiceNumber = $this->generateInvoiceNumber($merchant, 'order');

        DB::beginTransaction();
        try {
            $invoice = MerchantInvoice::create([
                'invoice_number' => $invoiceNumber,
                'merchant_id' => $merchant->id,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'invoice_date' => now(),
                'period_start' => now(),
                'period_end' => now(),
                'total_sales' => $order->total_amount,
                'commission_rate' => \App\Services\FeatureFlagService::getCommissionRate() * 100,
                'commission_amount' => $order->total_amount * \App\Services\FeatureFlagService::getCommissionRate(),
                'net_amount' => $order->total_amount - ($order->total_amount * \App\Services\FeatureFlagService::getCommissionRate()),
                'tax_amount' => $taxAmount,
                'invoice_type' => 'order',
                'status' => 'issued',
                'due_date' => now()->addDays(30),
            ]);

            // Generate PDF
            $pdfPath = $this->generateInvoicePdf($invoice);
            $invoice->update(['pdf_path' => $pdfPath]);

            DB::commit();

            // Send email
            // TODO: Dispatch email job

            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate monthly consolidated invoice
     */
    public function generateMonthlyInvoice(Merchant $merchant, int $year, int $month): MerchantInvoice
    {
        $periodStart = now()->setYear($year)->setMonth($month)->startOfMonth();
        $periodEnd = now()->setYear($year)->setMonth($month)->endOfMonth();

        $orders = Order::where('merchant_id', $merchant->id)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get();

        $totalSales = $orders->sum('total_amount');
        $commissionRate = \App\Services\FeatureFlagService::getCommissionRate();
        $commissionAmount = $totalSales * $commissionRate;
        $netAmount = $totalSales - $commissionAmount;

        // Calculate tax
        $taxInfo = $this->taxService->calculateTax($totalSales);
        $taxAmount = $taxInfo['tax_amount'] ?? 0;

        $invoiceNumber = $this->generateInvoiceNumber($merchant, 'monthly', $year, $month);

        DB::beginTransaction();
        try {
            $invoice = MerchantInvoice::create([
                'invoice_number' => $invoiceNumber,
                'merchant_id' => $merchant->id,
                'invoice_date' => now(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_sales' => $totalSales,
                'commission_rate' => $commissionRate * 100,
                'commission_amount' => $commissionAmount,
                'total_activations' => \App\Models\Coupon::whereHas('order', function ($q) use ($merchant, $periodStart, $periodEnd) {
                    $q->where('merchant_id', $merchant->id)
                        ->whereBetween('created_at', [$periodStart, $periodEnd]);
                })->where('status', 'activated')->count(),
                'net_amount' => $netAmount,
                'tax_amount' => $taxAmount,
                'invoice_type' => 'monthly',
                'status' => 'issued',
                'due_date' => $periodEnd->copy()->addDays(30),
            ]);

            // Generate PDF
            $pdfPath = $this->generateInvoicePdf($invoice);
            $invoice->update(['pdf_path' => $pdfPath]);

            DB::commit();

            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate invoice number
     */
    protected function generateInvoiceNumber(Merchant $merchant, string $type, ?int $year = null, ?int $month = null): string
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        $seq = MerchantInvoice::where('merchant_id', $merchant->id)
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->count() + 1;
        $seqStr = str_pad($seq, 4, '0', STR_PAD_LEFT);

        return "OFR-{$year}{$monthStr}-{$merchant->id}-{$seqStr}";
    }

    /**
     * Generate invoice PDF
     */
    protected function generateInvoicePdf(MerchantInvoice $invoice): string
    {
        // Use PdfService to generate invoice PDF
        // This is a placeholder - implement actual PDF generation with bilingual template
        $filename = 'invoices/' . $invoice->invoice_number . '.pdf';
        // TODO: Generate PDF using PdfService with invoice template
        return $filename;
    }

    /**
     * Re-issue invoice
     */
    public function reissueInvoice(MerchantInvoice $invoice, ?User $admin = null, string $reason = null): MerchantInvoice
    {
        // Generate new PDF
        $pdfPath = $this->generateInvoicePdf($invoice);
        $invoice->update(['pdf_path' => $pdfPath]);

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin?->id,
            'invoice_reissued',
            MerchantInvoice::class,
            $invoice->id,
            "Invoice {$invoice->invoice_number} re-issued. Reason: {$reason}",
            null,
            ['pdf_path' => $pdfPath],
            ['reason' => $reason]
        );

        return $invoice;
    }

    /**
     * Cancel invoice
     */
    public function cancelInvoice(MerchantInvoice $invoice, ?User $admin = null, string $reason = null): void
    {
        $invoice->update(['status' => 'cancelled']);

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin?->id,
            'invoice_cancelled',
            MerchantInvoice::class,
            $invoice->id,
            "Invoice {$invoice->invoice_number} cancelled. Reason: {$reason}",
            ['status' => 'issued'],
            ['status' => 'cancelled'],
            ['reason' => $reason]
        );
    }
}


