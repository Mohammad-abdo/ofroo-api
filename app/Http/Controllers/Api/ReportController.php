<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /** @var list<string> */
    private const INSIGHT_EXPORT_TYPES = ['merchant_insight', 'offer_insight', 'category_insight', 'mall_insight'];

    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Deep insight preview (JSON) for one merchant, offer, category, or mall.
     */
    public function entityInsightReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity' => 'required|in:merchant,offer,category,mall',
            'entity_id' => 'required|integer|min:1',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);
        $report = $this->reportService->generateEntityInsightReport($data);

        return response()->json([
            'data' => isset($report['data']) && $report['data'] instanceof Collection
                ? $report['data']->values()
                : ($report['data'] ?? []),
            'summary' => $report['summary'] ?? [],
            'chart_series' => $report['chart_series'] ?? [],
            'chart_series_2' => $report['chart_series_2'] ?? [],
            'entity_meta' => $report['entity_meta'] ?? [],
        ]);
    }

    /**
     * Generate users report
     */
    public function usersReport(Request $request): JsonResponse
    {
        $report = $this->reportService->generateUsersReport(
            $this->reportService->normalizeReportFilters($request->all())
        );

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
        $report = $this->reportService->generateMerchantsReport(
            $this->reportService->normalizeReportFilters($request->all())
        );

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
        $report = $this->reportService->generateOrdersReport(
            $this->reportService->normalizeReportFilters($request->all())
        );

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
        $report = $this->reportService->generateProductsReport(
            $this->reportService->normalizeReportFilters($request->all())
        );

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
        $report = $this->reportService->generatePaymentsReport(
            $this->reportService->normalizeReportFilters($request->all())
        );

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
        $report = $this->reportService->generateFinancialReport(
            $this->reportService->normalizeReportFilters($request->all())
        );

        return response()->json([
            'data' => $report['data'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Geo points for admin reports map (users with last GPS, merchant branches with lat/lng).
     */
    public function geoDistribution(Request $request): JsonResponse
    {
        $data = $this->reportService->generateGeoDistribution(
            $this->reportService->normalizeReportFilters($request->all())
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Export report to PDF
     */
    public function exportPdf(Request $request, string $type): BinaryFileResponse
    {
        // Validate type
        $allowedTypes = [
            'users', 'merchants', 'orders', 'products', 'payments', 'financial', 'sales', 'commission',
            'merchant_insight', 'offer_insight', 'category_insight', 'mall_insight',
        ];
        if (! in_array($type, $allowedTypes)) {
            abort(422, 'Invalid report type');
        }

        if (in_array($type, self::INSIGHT_EXPORT_TYPES, true)) {
            $request->validate(['entity_id' => 'required|integer|min:1']);
        }

        $filters = $this->reportService->normalizeReportFilters($request->all());
        $user = $request->user();
        $filters['language'] = ($user ? $user->language : null) ?? $request->input('language', 'ar');
        if (isset($filters['status']) && $type === 'merchants') {
            $filters['approved'] = $filters['status'] === 'approved';
        }
        if (isset($filters['status']) && in_array($type, ['orders', 'sales'])) {
            $filters['payment_status'] = $filters['status'];
        }
        if (isset($filters['merchant_id']) && ! in_array($type, self::INSIGHT_EXPORT_TYPES, true)) {
            $filters['merchant'] = $filters['merchant_id'];
        }

        if (in_array($type, self::INSIGHT_EXPORT_TYPES, true)) {
            $entity = match ($type) {
                'merchant_insight' => 'merchant',
                'offer_insight' => 'offer',
                'category_insight' => 'category',
                'mall_insight' => 'mall',
                default => null,
            };
            $filters['entity'] = $entity;
            $filters['entity_id'] = (int) $request->input('entity_id');
            $method = 'generateEntityInsightReport';
        } else {
            $methodMap = [
                'users' => 'generateUsersReport',
                'merchants' => 'generateMerchantsReport',
                'orders' => 'generateOrdersReport',
                'products' => 'generateProductsReport',
                'payments' => 'generatePaymentsReport',
                'financial' => 'generateFinancialReport',
                'sales' => 'generateOrdersReport',
                'commission' => 'generateFinancialReport',
            ];
            $method = $methodMap[$type] ?? 'generateOrdersReport';
        }

        if (! method_exists($this->reportService, $method)) {
            abort(500, "Report method {$method} does not exist");
        }

        $report = $this->reportService->$method($filters);

        if (in_array($type, self::INSIGHT_EXPORT_TYPES, true)) {
            if (! empty($report['summary']['not_found'])) {
                abort(404, 'Record not found for this insight report');
            }
            if (! empty($report['summary']['error'])) {
                abort(422, (string) $report['summary']['error']);
            }
        }

        try {
            $relativePath = $this->reportService->exportToPdf($type, $report, $filters);
            $filePath = Storage::disk('public')->path($relativePath);

            if (! is_readable($filePath)) {
                abort(500, 'Report file not found');
            }

            $filename = basename($filePath);

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            abort(500, 'Failed to generate PDF: '.$e->getMessage());
        }
    }

    /**
     * Export report to Excel
     */
    public function exportExcel(Request $request, string $type): BinaryFileResponse
    {
        $allowedTypes = [
            'users', 'merchants', 'orders', 'products', 'payments', 'financial', 'sales', 'commission',
            'merchant_insight', 'offer_insight', 'category_insight', 'mall_insight',
        ];
        if (! in_array($type, $allowedTypes)) {
            abort(422, 'Invalid report type');
        }

        if (in_array($type, self::INSIGHT_EXPORT_TYPES, true)) {
            $request->validate(['entity_id' => 'required|integer|min:1']);
        }

        $filters = $this->reportService->normalizeReportFilters($request->all());
        $filters['language'] = $request->input('language', 'ar');
        if (isset($filters['status']) && $type === 'merchants') {
            $filters['approved'] = $filters['status'] === 'approved';
        }
        if (isset($filters['status']) && in_array($type, ['orders', 'sales'])) {
            $filters['payment_status'] = $filters['status'];
        }
        if (isset($filters['merchant_id']) && ! in_array($type, self::INSIGHT_EXPORT_TYPES, true)) {
            $filters['merchant'] = $filters['merchant_id'];
        }

        if (in_array($type, self::INSIGHT_EXPORT_TYPES, true)) {
            $entity = match ($type) {
                'merchant_insight' => 'merchant',
                'offer_insight' => 'offer',
                'category_insight' => 'category',
                'mall_insight' => 'mall',
                default => null,
            };
            $filters['entity'] = $entity;
            $filters['entity_id'] = (int) $request->input('entity_id');
            $method = 'generateEntityInsightReport';
        } else {
            $methodMap = [
                'users' => 'generateUsersReport',
                'merchants' => 'generateMerchantsReport',
                'orders' => 'generateOrdersReport',
                'products' => 'generateProductsReport',
                'payments' => 'generatePaymentsReport',
                'financial' => 'generateFinancialReport',
                'sales' => 'generateOrdersReport',
                'commission' => 'generateFinancialReport',
            ];
            $method = $methodMap[$type] ?? 'generateOrdersReport';
        }

        if (! method_exists($this->reportService, $method)) {
            abort(500, "Report method {$method} does not exist");
        }

        $report = $this->reportService->$method($filters);

        if (in_array($type, self::INSIGHT_EXPORT_TYPES, true)) {
            if (! empty($report['summary']['not_found'])) {
                abort(404, 'Record not found for this insight report');
            }
            if (! empty($report['summary']['error'])) {
                abort(422, (string) $report['summary']['error']);
            }
        }

        try {
            $relativePath = $this->reportService->exportToExcel($type, $report, $filters);
            $filePath = Storage::disk('public')->path($relativePath);

            if (! is_readable($filePath)) {
                abort(500, 'Report file not found');
            }

            $filename = basename($filePath);

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            abort(500, 'Failed to generate Excel: '.$e->getMessage());
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
