<?php

namespace App\Services;

use App\Models\FinancialReportsCache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FinancialReportsCacheService
{
    /**
     * Generate cache key hash from parameters
     */
    public function generateParamsHash(array $params): string
    {
        // Sort params to ensure consistent hash
        ksort($params);
        return md5(json_encode($params));
    }

    /**
     * Get cached report if exists and not expired
     */
    public function getCachedReport(string $name, array $params, int $expiryHours = 24): ?FinancialReportsCache
    {
        $paramsHash = $this->generateParamsHash($params);

        $cached = FinancialReportsCache::where('name', $name)
            ->where('params_hash', $paramsHash)
            ->where('generated_at', '>=', now()->subHours($expiryHours))
            ->first();

        if ($cached && Storage::disk('local')->exists($cached->file_path)) {
            return $cached;
        }

        return null;
    }

    /**
     * Store report in cache
     */
    public function cacheReport(
        string $name,
        array $params,
        string $filePath,
        string $fileFormat = 'pdf',
        ?int $fileSize = null
    ): FinancialReportsCache {
        $paramsHash = $this->generateParamsHash($params);

        // Delete old cache with same params
        FinancialReportsCache::where('name', $name)
            ->where('params_hash', $paramsHash)
            ->delete();

        return FinancialReportsCache::create([
            'name' => $name,
            'params_hash' => $paramsHash,
            'generated_at' => now(),
            'file_path' => $filePath,
            'file_format' => $fileFormat,
            'file_size' => $fileSize ?? Storage::disk('local')->size($filePath),
            'params' => $params,
        ]);
    }

    /**
     * Delete expired cache entries
     */
    public function deleteExpiredCache(int $expiryHours = 168): int // Default 7 days
    {
        $expired = FinancialReportsCache::where('generated_at', '<', now()->subHours($expiryHours))->get();
        $count = 0;

        foreach ($expired as $cache) {
            if (Storage::disk('local')->exists($cache->file_path)) {
                Storage::disk('local')->delete($cache->file_path);
            }
            $cache->delete();
            $count++;
        }

        return $count;
    }

    /**
     * Delete cache by name
     */
    public function deleteCacheByName(string $name): int
    {
        $caches = FinancialReportsCache::where('name', $name)->get();
        $count = 0;

        foreach ($caches as $cache) {
            if (Storage::disk('local')->exists($cache->file_path)) {
                Storage::disk('local')->delete($cache->file_path);
            }
            $cache->delete();
            $count++;
        }

        return $count;
    }

    /**
     * Delete specific cached report
     */
    public function deleteCachedReport(string $id): bool
    {
        $cache = FinancialReportsCache::findOrFail($id);

        if (Storage::disk('local')->exists($cache->file_path)) {
            Storage::disk('local')->delete($cache->file_path);
        }

        return $cache->delete();
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $total = FinancialReportsCache::count();
        $totalSize = FinancialReportsCache::sum('file_size');
        $oldest = FinancialReportsCache::orderBy('generated_at', 'asc')->first();
        $newest = FinancialReportsCache::orderBy('generated_at', 'desc')->first();

        return [
            'total_cached_reports' => $total,
            'total_cache_size_bytes' => $totalSize,
            'total_cache_size_mb' => round($totalSize / 1024 / 1024, 2),
            'oldest_cache' => $oldest ? $oldest->generated_at->toDateTimeString() : null,
            'newest_cache' => $newest ? $newest->generated_at->toDateTimeString() : null,
            'by_format' => FinancialReportsCache::selectRaw('file_format, COUNT(*) as count')
                ->groupBy('file_format')
                ->pluck('count', 'file_format')
                ->toArray(),
        ];
    }

    /**
     * Clean up old cache files
     */
    public function cleanup(int $expiryHours = 168): array
    {
        $deletedCount = $this->deleteExpiredCache($expiryHours);
        $orphanedFiles = $this->findOrphanedFiles();

        return [
            'deleted_cache_entries' => $deletedCount,
            'orphaned_files_found' => count($orphanedFiles),
            'orphaned_files' => $orphanedFiles,
        ];
    }

    /**
     * Find orphaned cache files (files without database entries)
     */
    protected function findOrphanedFiles(): array
    {
        $cacheDir = 'reports/cache';
        if (!Storage::disk('local')->exists($cacheDir)) {
            return [];
        }
        
        $files = Storage::disk('local')->files($cacheDir);
        $orphaned = [];

        foreach ($files as $file) {
            $exists = FinancialReportsCache::where('file_path', $file)->exists();
            if (!$exists) {
                $orphaned[] = $file;
            }
        }

        return $orphaned;
    }

    /**
     * Generate and cache a report
     */
    public function generateAndCache(
        string $reportType,
        string $format,
        array $params = [],
        int $cacheDuration = 24
    ): FinancialReportsCache {
        // Check if cached report exists and is still valid
        $cached = $this->getCachedReport($reportType, $params, $cacheDuration);
        if ($cached) {
            return $cached;
        }

        // Generate report using ReportService
        $reportService = app(\App\Services\ReportService::class);
        
        // Map report types to method names
        $methodMap = [
            'activations' => 'generateActivationsReport',
            'gps_engagement' => 'generateGpsEngagementReport',
            'conversion_funnel' => 'generateConversionFunnelReport',
            'failed_payments' => 'generateFailedPaymentsReport',
        ];
        
        $method = $methodMap[$reportType] ?? 'generate' . ucfirst($reportType) . 'Report';
        
        if (!method_exists($reportService, $method)) {
            throw new \Exception("Report type '{$reportType}' is not supported");
        }

        $reportData = $reportService->$method($params);

        // Generate file based on format
        $filename = "report_{$reportType}_" . date('Y-m-d_H-i-s') . '.' . $format;
        $filePath = 'reports/cache/' . $filename;
        $fullPath = storage_path('app/' . $filePath);

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        switch ($format) {
            case 'pdf':
                $pdfPath = $reportService->exportToPdf($reportType, $reportData, $params);
                $pdfContent = Storage::disk('public')->get(str_replace('/storage/', '', $pdfPath));
                Storage::disk('local')->put($filePath, $pdfContent);
                break;

            case 'excel':
            case 'xlsx':
                $excelPath = $reportService->exportToExcel($reportType, $reportData, $params);
                $excelContent = Storage::disk('public')->get(str_replace('/storage/', '', $excelPath));
                Storage::disk('local')->put($filePath, $excelContent);
                break;

            case 'csv':
                $csvContent = $this->generateCsv($reportData, $reportType);
                Storage::disk('local')->put($filePath, $csvContent);
                break;

            default:
                throw new \Exception("Format '{$format}' is not supported");
        }

        $fileSize = Storage::disk('local')->size($filePath);

        // Cache the report
        return $this->cacheReport(
            $reportType,
            $params,
            $filePath,
            $format,
            $fileSize
        );
    }

    /**
     * Generate CSV content from report data
     */
    protected function generateCsv(array $reportData, string $reportType): string
    {
        $data = $reportData['data'] ?? [];
        $summary = $reportData['summary'] ?? [];

        $csv = fopen('php://temp', 'r+');

        // Write headers based on report type
        if (!empty($data)) {
            $firstRow = $data[0];
            if (is_object($firstRow)) {
                $firstRow = $firstRow->toArray();
            }
            if (is_array($firstRow)) {
                fputcsv($csv, array_keys($firstRow));
            }
        }

        // Write data rows
        foreach ($data as $row) {
            if (is_object($row)) {
                $row = $row->toArray();
            }
            if (is_array($row)) {
                fputcsv($csv, $row);
            }
        }

        // Write summary
        if (!empty($summary)) {
            fputcsv($csv, []); // Empty row
            fputcsv($csv, ['Summary']);
            foreach ($summary as $key => $value) {
                if (is_array($value)) {
                    fputcsv($csv, [$key, json_encode($value)]);
                } else {
                    fputcsv($csv, [$key, $value]);
                }
            }
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }
}