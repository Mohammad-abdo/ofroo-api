<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialReportsCache;
use App\Services\FinancialReportsCacheService;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportsCacheController extends Controller
{
    protected FinancialReportsCacheService $cacheService;

    public function __construct(FinancialReportsCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Admin: List all cached reports
     */
    public function index(Request $request): JsonResponse
    {
        $query = FinancialReportsCache::orderBy('generated_at', 'desc');

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('file_format')) {
            $query->where('file_format', $request->file_format);
        }

        if ($request->has('from')) {
            $query->where('generated_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('generated_at', '<=', $request->to);
        }

        $reports = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Admin: Get cached report
     */
    public function show(string $id): JsonResponse
    {
        $report = FinancialReportsCache::findOrFail($id);

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * Admin: Generate and cache report
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'report_type' => 'required|in:users,merchants,orders,payments,financial,subscriptions,courses,certificates,activations,gps_engagement,conversion_funnel,failed_payments',
            'format' => 'required|in:pdf,excel,csv',
            'params' => 'nullable|array',
            'cache_duration' => 'nullable|integer|min:1|max:720', // hours, max 30 days
        ]);

        $admin = $request->user();
        $params = $request->params ?? [];
        $cacheDuration = $request->cache_duration ?? 24; // default 24 hours

        try {
            $format = $request->input('format');
            $reportType = $request->input('report_type');

            $cachedReport = $this->cacheService->generateAndCache(
                $reportType,
                $format,
                $params,
                $cacheDuration
            );

            // Log activity
            $activityLogService = app(ActivityLogService::class);
            $activityLogService->log(
                $admin->id,
                'financial_report_generated',
                FinancialReportsCache::class,
                $cachedReport->id,
                "Financial report generated: {$reportType} ({$format})",
                null,
                ['report_type' => $reportType, 'format' => $format],
                ['params' => $params]
            );

            return response()->json([
                'message' => 'Report generated and cached successfully',
                'data' => $cachedReport,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Report generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin: Download cached report
     */
    public function download(string $id)
    {
        $report = FinancialReportsCache::findOrFail($id);

        if (!Storage::disk('local')->exists($report->file_path)) {
            abort(404, 'Report file not found');
        }

        // Log access
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            request()->user()->id,
            'financial_report_downloaded',
            FinancialReportsCache::class,
            $report->id,
            "Downloaded cached report: {$report->name}",
            null,
            ['file_format' => $report->file_format],
            ['file_size' => $report->file_size]
        );

        $filename = $report->name . '.' . $report->file_format;
        $filePath = storage_path('app/' . $report->file_path);

        return response()->download($filePath, $filename);
    }

    /**
     * Admin: Delete cached report
     */
    public function destroy(string $id): JsonResponse
    {
        $admin = request()->user();
        $report = FinancialReportsCache::findOrFail($id);

        // Delete file if exists
        if (Storage::disk('local')->exists($report->file_path)) {
            Storage::disk('local')->delete($report->file_path);
        }

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'financial_report_deleted',
            FinancialReportsCache::class,
            $report->id,
            "Deleted cached report: {$report->name}",
            ['name' => $report->name, 'file_format' => $report->file_format],
            null,
            ['file_size' => $report->file_size]
        );

        $report->delete();

        return response()->json([
            'message' => 'Cached report deleted successfully',
        ]);
    }

    /**
     * Admin: Clear expired reports
     */
    public function clearExpired(): JsonResponse
    {
        $admin = request()->user();
        $expiredReports = FinancialReportsCache::where('generated_at', '<', now()->subDays(30))->get();

        $deletedCount = 0;
        foreach ($expiredReports as $report) {
            if (Storage::disk('local')->exists($report->file_path)) {
                Storage::disk('local')->delete($report->file_path);
            }
            $report->delete();
            $deletedCount++;
        }

        // Log activity
        $activityLogService = app(ActivityLogService::class);
        $activityLogService->log(
            $admin->id,
            'financial_reports_cleared',
            FinancialReportsCache::class,
            null,
            "Cleared {$deletedCount} expired cached reports",
            null,
            ['deleted_count' => $deletedCount]
        );

        return response()->json([
            'message' => "Cleared {$deletedCount} expired reports",
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * Admin: Get cache statistics
     */
    public function statistics(): JsonResponse
    {
        $totalReports = FinancialReportsCache::count();
        $totalSize = FinancialReportsCache::sum('file_size');
        $reportsByFormat = FinancialReportsCache::selectRaw('file_format, COUNT(*) as count')
            ->groupBy('file_format')
            ->get()
            ->pluck('count', 'file_format');

        $recentReports = FinancialReportsCache::where('generated_at', '>=', now()->subDays(7))
            ->count();

        return response()->json([
            'data' => [
                'total_reports' => $totalReports,
                'total_size_bytes' => $totalSize,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2),
                'reports_by_format' => $reportsByFormat,
                'recent_reports_7_days' => $recentReports,
            ],
        ]);
    }
}