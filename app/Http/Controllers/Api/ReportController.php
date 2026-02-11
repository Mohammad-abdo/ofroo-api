<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Generate users report
     */
    public function usersReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateUsersReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Generate merchants report
     */
    public function merchantsReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateMerchantsReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Generate orders report
     */
    public function ordersReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateOrdersReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Generate products report
     */
    public function productsReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateProductsReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Generate payments report
     */
    public function paymentsReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generatePaymentsReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Generate financial report
     */
    public function financialReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateFinancialReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Export report to PDF
     */
    public function exportPdf(Request $request, string $type): StreamedResponse
    {
        // Validate type
        $allowedTypes = ['users', 'merchants', 'orders', 'products', 'payments', 'financial', 'sales', 'commission'];
        if (!in_array($type, $allowedTypes)) {
            abort(422, 'Invalid report type');
        }

        $filters = $request->all();
        $user = $request->user();
        $filters['language'] = ($user ? $user->language : null) ?? 'ar';

        // Map type to method name
        $methodMap = [
            'users' => 'generateUsersReport',
            'merchants' => 'generateMerchantsReport',
            'orders' => 'generateOrdersReport',
            'products' => 'generateProductsReport',
            'payments' => 'generatePaymentsReport',
            'financial' => 'generateFinancialReport',
            'sales' => 'generateOrdersReport', // Sales uses orders report
            'commission' => 'generateFinancialReport', // Commission uses financial report
        ];

        $method = $methodMap[$type] ?? 'generateOrdersReport';
        
        if (!method_exists($this->reportService, $method)) {
            abort(500, "Report method {$method} does not exist");
        }

        $report = $this->reportService->$method($filters);

        try {
        $pdfPath = $this->reportService->exportToPdf($type, $report, $filters);
            $filePath = storage_path('app/public/' . str_replace('/storage/', '', $pdfPath));
            
            if (!file_exists($filePath)) {
                abort(500, 'Report file not found');
            }

            return response()->download($filePath);
        } catch (\Exception $e) {
            abort(500, 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Export report to Excel
     */
    public function exportExcel(Request $request, string $type): StreamedResponse
    {
        // Validate type
        $allowedTypes = ['users', 'merchants', 'orders', 'products', 'payments', 'financial', 'sales', 'commission'];
        if (!in_array($type, $allowedTypes)) {
            abort(422, 'Invalid report type');
        }

        $filters = $request->all();

        // Map type to method name
        $methodMap = [
            'users' => 'generateUsersReport',
            'merchants' => 'generateMerchantsReport',
            'orders' => 'generateOrdersReport',
            'products' => 'generateProductsReport',
            'payments' => 'generatePaymentsReport',
            'financial' => 'generateFinancialReport',
            'sales' => 'generateOrdersReport', // Sales uses orders report
            'commission' => 'generateFinancialReport', // Commission uses financial report
        ];

        $method = $methodMap[$type] ?? 'generateOrdersReport';
        
        if (!method_exists($this->reportService, $method)) {
            abort(500, "Report method {$method} does not exist");
        }

        $report = $this->reportService->$method($filters);

        try {
        $excelPath = $this->reportService->exportToExcel($type, $report, $filters);
            $filePath = storage_path('app/public/' . str_replace('/storage/', '', $excelPath));
            
            if (!file_exists($filePath)) {
                abort(500, 'Report file not found');
            }

            return response()->download($filePath);
        } catch (\Exception $e) {
            abort(500, 'Failed to generate Excel: ' . $e->getMessage());
        }
    }

    /**
     * Generate coupon activations report
     */
    public function activationsReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateActivationsReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Generate GPS engagement report
     */
    public function gpsEngagementReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateGpsEngagementReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Generate conversion funnel report
     */
    public function conversionFunnelReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateConversionFunnelReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Generate failed payments report
     */
    public function failedPaymentsReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateFailedPaymentsReport($request->all());

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }
}
