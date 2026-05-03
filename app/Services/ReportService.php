<?php

namespace App\Services;

use App\Exports\ReportExport;
use App\Models\ActivationReport;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FinancialTransaction;
use App\Models\Mall;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Support\ReportChartSvg;
use App\Support\ReportTablePresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;

class ReportService
{
    /**
     * Normalize query aliases (category_id → category, etc.) for all report generators.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeReportFilters(array $filters): array
    {
        if (isset($filters['category_id']) && ! isset($filters['category'])) {
            $filters['category'] = $filters['category_id'];
        }
        if (isset($filters['mall_id']) && ! isset($filters['mall'])) {
            $filters['mall'] = $filters['mall_id'];
        }
        if (isset($filters['offer_id']) && ! isset($filters['offer'])) {
            $filters['offer'] = $filters['offer_id'];
        }
        if (isset($filters['merchant_id']) && ! isset($filters['merchant'])) {
            $filters['merchant'] = $filters['merchant_id'];
        }

        return $filters;
    }

    /**
     * Branding for PDF/Excel: logo on disk + data URI for DomPDF, app display name.
     */
    public function reportBranding(): array
    {
        $appName = Setting::getValue('app_name', 'OFROO Admin');
        if (! is_string($appName) || $appName === '') {
            $appName = 'OFROO Admin';
        }

        $logoUrl = Setting::getValue('app_logo');
        $logoFullPath = null;
        $logoDataUri = null;

        if (is_string($logoUrl) && $logoUrl !== '') {
            $pathPart = parse_url($logoUrl, PHP_URL_PATH);
            if (is_string($pathPart) && str_contains($pathPart, 'storage/')) {
                $rel = ltrim(str_replace(['/storage/', 'storage/'], '', $pathPart), '/');
                $full = storage_path('app/public/'.$rel);
                if (is_readable($full)) {
                    $logoFullPath = $full;
                    $mime = @mime_content_type($full) ?: 'image/png';
                    if (str_starts_with($mime, 'image/')) {
                        $logoDataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
                    }
                }
            }
        }

        return [
            'app_name' => $appName,
            'logo_full_path' => $logoFullPath,
            'logo_data_uri' => $logoDataUri,
        ];
    }

    /**
     * Generate Users Report
     */
    public function generateUsersReport(array $filters = []): array
    {
        $filters = $this->normalizeReportFilters($filters);
        $query = User::with('role');

        if (isset($filters['role'])) {
            $query->whereHas('role', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return [
            'data' => $query->get(),
            'summary' => [
                'total' => $query->count(),
                'by_role' => User::with('role')
                    ->get()
                    ->groupBy('role.name')
                    ->map->count(),
            ],
        ];
    }

    /**
     * Generate Merchants Report
     */
    public function generateMerchantsReport(array $filters = []): array
    {
        $filters = $this->normalizeReportFilters($filters);
        $query = Merchant::with(['user', 'branches']);

        if (! empty($filters['merchant_id'])) {
            $query->where('id', $filters['merchant_id']);
        }

        if (isset($filters['approved'])) {
            $query->where('approved', $filters['approved']);
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('company_name_ar', 'like', "%{$search}%")
                    ->orWhere('company_name_en', 'like', "%{$search}%");
            });
        }

        return [
            'data' => $query->get(),
            'summary' => [
                'total' => $query->count(),
                'approved' => Merchant::where('approved', true)->count(),
                'pending' => Merchant::where('approved', false)->count(),
            ],
        ];
    }

    /**
     * Generate Orders Report
     */
    public function generateOrdersReport(array $filters = []): array
    {
        $filters = $this->normalizeReportFilters($filters);
        $query = Order::with(['user', 'merchant', 'items.offer']);

        if (isset($filters['merchant'])) {
            $query->where('merchant_id', $filters['merchant']);
        }

        if (isset($filters['category_id']) || isset($filters['category'])) {
            $cid = $filters['category'] ?? $filters['category_id'];
            $query->whereHas('items.offer', function ($q) use ($cid) {
                $q->where('category_id', $cid);
            });
        }

        if (isset($filters['offer'])) {
            $oid = $filters['offer'];
            $query->whereHas('items', function ($q) use ($oid) {
                $q->where('offer_id', $oid);
            });
        }

        if (isset($filters['mall'])) {
            $mid = $filters['mall'];
            $query->whereHas('items.offer', function ($q) use ($mid) {
                $q->where('mall_id', $mid);
            });
        }

        if (isset($filters['user'])) {
            $query->where('user_id', $filters['user']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (isset($filters['min_amount'])) {
            $query->where('total_amount', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount'])) {
            $query->where('total_amount', '<=', $filters['max_amount']);
        }

        $orders = $query->get();

        return [
            'data' => $orders,
            'summary' => [
                'total_orders' => $orders->count(),
                'total_revenue' => $orders->where('payment_status', 'paid')->sum('total_amount'),
                'by_status' => $orders->groupBy('payment_status')->map->count(),
                'by_method' => $orders->groupBy('payment_method')->map->count(),
            ],
        ];
    }

    /**
     * Generate Products/Offers Report
     */
    public function generateProductsReport(array $filters = []): array
    {
        $filters = $this->normalizeReportFilters($filters);
        $query = Offer::with(['merchant', 'category', 'mall']);

        if (isset($filters['merchant'])) {
            $query->where('merchant_id', $filters['merchant']);
        }

        if (isset($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (isset($filters['mall'])) {
            $query->where('mall_id', $filters['mall']);
        }

        if (isset($filters['offer'])) {
            $query->where('id', $filters['offer']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $offers = $query->get();

        return [
            'data' => $offers,
            'summary' => [
                'total' => $offers->count(),
                'active' => $offers->where('status', 'active')->count(),
                'total_coupons_sold' => $offers->sum('total_coupons') - $offers->sum('coupons_remaining'),
                'total_revenue' => OrderItem::whereIn('offer_id', $offers->pluck('id'))
                    ->whereHas('order', function ($q) {
                        $q->where('payment_status', 'paid');
                    })
                    ->sum('total_price'),
            ],
        ];
    }

    /**
     * Generate Payments Report
     */
    public function generatePaymentsReport(array $filters = []): array
    {
        $filters = $this->normalizeReportFilters($filters);
        $query = Payment::with(['order.merchant', 'order.user', 'order.items.offer']);

        if (isset($filters['merchant_id']) || isset($filters['merchant'])) {
            $mid = $filters['merchant'] ?? $filters['merchant_id'];
            $query->whereHas('order', fn ($q) => $q->where('merchant_id', $mid));
        }

        if (isset($filters['offer'])) {
            $oid = $filters['offer'];
            $query->whereHas('order.items', fn ($q) => $q->where('offer_id', $oid));
        }

        if (isset($filters['category'])) {
            $cid = $filters['category'];
            $query->whereHas('order.items.offer', fn ($q) => $q->where('category_id', $cid));
        }

        if (isset($filters['mall'])) {
            $mid = $filters['mall'];
            $query->whereHas('order.items.offer', fn ($q) => $q->where('mall_id', $mid));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $payments = $query->get();

        return [
            'data' => $payments,
            'summary' => [
                'total' => $payments->count(),
                'total_amount' => $payments->where('status', 'success')->sum('amount'),
                'by_status' => $payments->groupBy('status')->map->count(),
                'by_gateway' => $payments->groupBy('gateway')->map->count(),
            ],
        ];
    }

    /**
     * Generate Financial Transactions Report
     */
    public function generateFinancialReport(array $filters = []): array
    {
        $filters = $this->normalizeReportFilters($filters);
        $query = FinancialTransaction::with('merchant');

        if (isset($filters['merchant'])) {
            $query->where('merchant_id', $filters['merchant']);
        }

        if (isset($filters['type'])) {
            $query->where('transaction_type', $filters['type']);
        }

        if (isset($filters['flow'])) {
            $query->where('transaction_flow', $filters['flow']);
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $transactions = $query->get();

        return [
            'data' => $transactions,
            'summary' => [
                'total' => $transactions->count(),
                'total_incoming' => $transactions->where('transaction_flow', 'incoming')->sum('amount'),
                'total_outgoing' => $transactions->where('transaction_flow', 'outgoing')->sum('amount'),
                'net' => $transactions->where('transaction_flow', 'incoming')->sum('amount')
                    - $transactions->where('transaction_flow', 'outgoing')->sum('amount'),
                'by_type' => $transactions->groupBy('transaction_type')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total' => $group->sum('amount'),
                    ];
                }),
            ],
        ];
    }

    /**
     * Export report to PDF
     */
    public function exportToPdf(string $reportType, array $data, array $filters = []): string
    {
        $filters['language'] = $filters['language'] ?? 'ar';
        $branding = $this->reportBranding();

        $table = ReportTablePresenter::forPdf($reportType, $data, $filters['language']);
        $summaryBlocks = isset($data['summary']) && is_array($data['summary'])
            ? ReportTablePresenter::summaryBlocks($data['summary'], $filters['language'], $reportType)
            : [];
        [$table, $summaryBlocks] = ReportTablePresenter::sanitizeMpdfTablePayload($table, $summaryBlocks);
        $reportTitles = ReportTablePresenter::reportTitles($reportType);
        $chartBlocks = $this->buildPdfChartBlocks($data, $filters['language'] ?? 'ar');
        $entityMeta = is_array($data['entity_meta'] ?? null) ? $data['entity_meta'] : [];
        if (isset($entityMeta['tags']) && is_array($entityMeta['tags'])) {
            foreach ($entityMeta['tags'] as $i => $tag) {
                if (! is_array($tag)) {
                    continue;
                }
                $entityMeta['tags'][$i]['ar'] = ReportTablePresenter::pdfStr($tag['ar'] ?? '');
                $entityMeta['tags'][$i]['en'] = ReportTablePresenter::pdfStr($tag['en'] ?? '');
                $entityMeta['tags'][$i]['value'] = ReportTablePresenter::pdfStr($tag['value'] ?? '');
            }
        }
        foreach (['title_ar', 'title_en', 'subtitle_ar', 'subtitle_en'] as $k) {
            if (array_key_exists($k, $entityMeta)) {
                $entityMeta[$k] = ReportTablePresenter::pdfStr($entityMeta[$k] ?? '');
            }
        }

        $html = View::make('reports.pdf.report', [
            'data' => $data,
            'filters' => $filters,
            'reportType' => $reportType,
            'generated_at' => now(),
            'branding' => $branding,
            'table' => $table,
            'summaryBlocks' => $summaryBlocks,
            'reportTitles' => $reportTitles,
            'chartBlocks' => $chartBlocks,
            'entityMeta' => $entityMeta,
        ])->render();

        $filename = "report_{$reportType}_".date('Y-m-d_H-i-s').'.pdf';
        $path = 'reports/'.$filename;

        Storage::disk('public')->put($path, $this->renderAdminReportPdf($html, $filters['language'] ?? 'ar'));

        return $path;
    }

    /**
     * Admin reports: mPDF (UTF-8, Arabic shaping, auto font) when installed; else DomPDF.
     */
    private function renderAdminReportPdf(string $html, string $lang): string
    {
        if (class_exists(Mpdf::class)) {
            $tempDir = storage_path('app/mpdf-tmp');
            if (! File::isDirectory($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'tempDir' => $tempDir,
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'margin_left' => 8,
                'margin_right' => 8,
                'margin_top' => 10,
                'margin_bottom' => 12,
                'default_font' => 'dejavusans',
            ]);
            $mpdf->SetDirectionality($lang === 'ar' ? 'rtl' : 'ltr');
            try {
                $mpdf->WriteHTML($html);

                return $mpdf->Output('', 'S');
            } catch (\Throwable $e) {
                Log::warning('mPDF WriteHTML failed, using DomPDF fallback', [
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }
        }

        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();
    }

    /**
     * Export report to Excel
     */
    public function exportToExcel(string $reportType, array $data, array $filters = []): string
    {
        $filename = "report_{$reportType}_".date('Y-m-d_H-i-s').'.xlsx';
        $path = 'reports/'.$filename;
        Excel::store(
            new ReportExport($data, $reportType, $filters, $this->reportBranding()),
            $path,
            'public'
        );

        return $path;
    }

    /**
     * Generate Coupon Activations Report
     */
    public function generateActivationsReport(array $filters = []): array
    {
        $query = ActivationReport::with(['coupon', 'merchant', 'user', 'order']);

        if (isset($filters['merchant'])) {
            $query->where('merchant_id', $filters['merchant']);
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $activations = $query->get();

        return [
            'data' => $activations,
            'summary' => [
                'total_activations' => $activations->count(),
                'by_method' => $activations->groupBy('activation_method')->map->count(),
                'by_merchant' => $activations->groupBy('merchant_id')->map->count(),
                'with_gps' => $activations->whereNotNull('latitude')->count(),
            ],
        ];
    }

    /**
     * Generate GPS Engagement Report
     */
    public function generateGpsEngagementReport(array $filters = []): array
    {
        $query = Order::with(['user', 'merchant']);

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $orders = $query->get();

        // Count orders with GPS location
        $ordersWithGps = $orders->filter(function ($order) {
            return $order->user_latitude !== null && $order->user_longitude !== null;
        });

        // Count nearby offers usage
        $nearbyOrders = $orders->filter(function ($order) {
            return $order->is_nearby === true;
        });

        return [
            'data' => [
                'total_orders' => $orders->count(),
                'orders_with_gps' => $ordersWithGps->count(),
                'orders_without_gps' => $orders->count() - $ordersWithGps->count(),
                'nearby_orders' => $nearbyOrders->count(),
            ],
            'summary' => [
                'gps_usage_percentage' => $orders->count() > 0
                    ? round(($ordersWithGps->count() / $orders->count()) * 100, 2)
                    : 0,
                'nearby_usage_percentage' => $orders->count() > 0
                    ? round(($nearbyOrders->count() / $orders->count()) * 100, 2)
                    : 0,
                'gps_enabled_users' => User::whereNotNull('last_latitude')->distinct()->count(),
            ],
        ];
    }

    /**
     * Generate Conversion Funnel Report
     */
    public function generateConversionFunnelReport(array $filters = []): array
    {
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        // Step 1: Users who viewed offers
        $viewedOffers = DB::table('offers')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // Step 2: Users who added to cart
        $addedToCart = DB::table('cart_items')
            ->whereBetween('created_at', [$from, $to])
            ->distinct('user_id')
            ->count('user_id');

        // Step 3: Users who initiated checkout
        $initiatedCheckout = DB::table('orders')
            ->whereBetween('created_at', [$from, $to])
            ->distinct('user_id')
            ->count('user_id');

        // Step 4: Users who completed payment
        $completedPayment = DB::table('orders')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->distinct('user_id')
            ->count('user_id');

        // Step 5: Coupons activated
        $activatedCoupons = ActivationReport::whereBetween('created_at', [$from, $to])
            ->distinct('coupon_id')
            ->count('coupon_id');

        $totalUsers = User::whereBetween('created_at', [$from, $to])->count();

        return [
            'data' => [
                'total_users' => $totalUsers,
                'viewed_offers' => $viewedOffers,
                'added_to_cart' => $addedToCart,
                'initiated_checkout' => $initiatedCheckout,
                'completed_payment' => $completedPayment,
                'activated_coupons' => $activatedCoupons,
            ],
            'summary' => [
                'view_to_cart_rate' => $viewedOffers > 0
                    ? round(($addedToCart / $viewedOffers) * 100, 2)
                    : 0,
                'cart_to_checkout_rate' => $addedToCart > 0
                    ? round(($initiatedCheckout / $addedToCart) * 100, 2)
                    : 0,
                'checkout_to_payment_rate' => $initiatedCheckout > 0
                    ? round(($completedPayment / $initiatedCheckout) * 100, 2)
                    : 0,
                'payment_to_activation_rate' => $completedPayment > 0
                    ? round(($activatedCoupons / $completedPayment) * 100, 2)
                    : 0,
                'overall_conversion_rate' => $viewedOffers > 0
                    ? round(($activatedCoupons / $viewedOffers) * 100, 2)
                    : 0,
            ],
        ];
    }

    /**
     * Generate Failed Payments Report
     */
    public function generateFailedPaymentsReport(array $filters = []): array
    {
        $query = Payment::with(['order.merchant', 'order.user'])
            ->where('status', '!=', 'success');

        if (isset($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }

        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $failedPayments = $query->get();

        return [
            'data' => $failedPayments,
            'summary' => [
                'total_failed' => $failedPayments->count(),
                'total_amount_lost' => $failedPayments->sum('amount'),
                'by_status' => $failedPayments->groupBy('status')->map->count(),
                'by_gateway' => $failedPayments->groupBy('gateway')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_amount' => $group->sum('amount'),
                    ];
                }),
                'by_reason' => $failedPayments->groupBy('failure_reason')->map->count(),
            ],
        ];
    }

    /**
     * Deep analytics for one merchant, offer, category, or mall (PDF + API).
     *
     * @param  array<string, mixed>  $filters  expects entity: merchant|offer|category|mall, entity_id, from?, to?
     * @return array<string, mixed>
     */
    public function generateEntityInsightReport(array $filters): array
    {
        $filters = $this->normalizeReportFilters($filters);
        $entity = $filters['entity'] ?? null;
        $id = isset($filters['entity_id']) ? (int) $filters['entity_id'] : 0;
        if (! $entity || $id < 1) {
            return [
                'data' => collect(),
                'summary' => ['error' => 'entity and entity_id are required'],
                'chart_series' => [],
                'chart_series_2' => [],
                'entity_meta' => [],
            ];
        }

        return match ($entity) {
            'merchant' => $this->buildMerchantInsight($id, $filters),
            'offer' => $this->buildOfferInsight($id, $filters),
            'category' => $this->buildCategoryInsight($id, $filters),
            'mall' => $this->buildMallInsight($id, $filters),
            default => [
                'data' => collect(),
                'summary' => ['error' => 'invalid entity'],
                'chart_series' => [],
                'chart_series_2' => [],
                'entity_meta' => [],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{title: string, svg: string}>
     */
    private function buildPdfChartBlocks(array $data, string $lang): array
    {
        $isRtl = $lang === 'ar';
        $blocks = [];
        $s1 = $data['chart_series'] ?? [];
        if (is_array($s1) && $s1 !== []) {
            $blocks[] = [
                'title' => $isRtl ? 'اتجاه الإيرادات (المدفوع)' : 'Paid revenue trend',
                'svg' => ReportChartSvg::barChart($s1, $isRtl ? 'اتجاه الإيرادات' : 'Revenue', $isRtl),
            ];
        }
        $s2 = $data['chart_series_2'] ?? [];
        if (is_array($s2) && $s2 !== []) {
            $blocks[] = [
                'title' => $isRtl ? 'أبرز البنود' : 'Top breakdown',
                'svg' => ReportChartSvg::horizontalBars($s2, $isRtl ? 'مقارنة' : 'Comparison', $isRtl),
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildMerchantInsight(int $merchantId, array $filters): array
    {
        $merchant = Merchant::with(['user', 'branches'])->find($merchantId);
        if (! $merchant) {
            return ['data' => collect(), 'summary' => ['not_found' => true], 'chart_series' => [], 'chart_series_2' => [], 'entity_meta' => []];
        }

        $q = Order::with(['user', 'merchant', 'items.offer'])->where('merchant_id', $merchantId);
        $this->applyDateRangeToOrderQuery($q, $filters);
        $orders = $q->orderByDesc('created_at')->get();

        $paidRev = (float) $orders->where('payment_status', 'paid')->sum('total_amount');
        $offerIds = Offer::where('merchant_id', $merchantId)->pluck('id');

        $topItems = collect();
        if ($offerIds->isNotEmpty()) {
            $topItems = OrderItem::query()
                ->whereIn('offer_id', $offerIds)
                ->whereHas('order', function ($q2) use ($filters) {
                    $q2->where('payment_status', 'paid');
                    $this->applyDateRangeToOrderQuery($q2, $filters);
                })
                ->selectRaw('offer_id, SUM(quantity) as units, SUM(total_price) as revenue')
                ->groupBy('offer_id')
                ->orderByDesc('revenue')
                ->limit(8)
                ->get();
        }

        $offers = $topItems->isEmpty()
            ? collect()
            : Offer::whereIn('id', $topItems->pluck('offer_id'))->get()->keyBy('id');
        $bar2 = [];
        foreach ($topItems as $row) {
            $o = $offers->get($row->offer_id);
            $label = $o ? (string) ($o->title_ar ?? $o->title_en ?? $o->title ?? '#'.$row->offer_id) : (string) $row->offer_id;
            $bar2[] = ['label' => mb_substr($label, 0, 32), 'value' => (float) $row->revenue];
        }

        $name = (string) ($merchant->company_name ?? $merchant->company_name_ar ?? $merchant->company_name_en ?? '—');

        return [
            'data' => $orders,
            'summary' => [
                'merchant' => $name,
                'branches' => $merchant->branches?->count() ?? 0,
                'offers_listed' => $offerIds->count(),
                'orders_in_period' => $orders->count(),
                'paid_revenue' => round($paidRev, 2),
                'paid_orders' => $orders->where('payment_status', 'paid')->count(),
            ],
            'chart_series' => $this->dailyPaidRevenueSeries($orders),
            'chart_series_2' => $bar2,
            'entity_meta' => [
                'title_ar' => 'تقرير تاجر: '.$name,
                'title_en' => 'Merchant insight: '.$name,
                'subtitle_ar' => 'ملخص أداء الطلبات والعروض ضمن الفترة المحددة',
                'subtitle_en' => 'Orders & offers performance for the selected period',
                'tags' => [
                    ['ar' => 'معرّف التاجر', 'en' => 'Merchant ID', 'value' => (string) $merchantId],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildOfferInsight(int $offerId, array $filters): array
    {
        $offer = Offer::with(['merchant', 'category', 'mall'])->find($offerId);
        if (! $offer) {
            return ['data' => collect(), 'summary' => ['not_found' => true], 'chart_series' => [], 'chart_series_2' => [], 'entity_meta' => []];
        }

        $q = Order::with(['user', 'merchant', 'items'])->whereHas('items', fn ($q2) => $q2->where('offer_id', $offerId));
        $this->applyDateRangeToOrderQuery($q, $filters);
        $orders = $q->orderByDesc('created_at')->get();

        $unitsSold = (int) OrderItem::query()
            ->where('offer_id', $offerId)
            ->whereHas('order', function ($q2) use ($filters) {
                $this->applyDateRangeToOrderQuery($q2, $filters);
            })
            ->sum('quantity');
        $lineRevenue = (float) OrderItem::query()
            ->where('offer_id', $offerId)
            ->whereHas('order', function ($q2) use ($filters) {
                $this->applyDateRangeToOrderQuery($q2, $filters);
                $q2->where('payment_status', 'paid');
            })
            ->sum('total_price');

        $title = (string) ($offer->title_ar ?? $offer->title_en ?? $offer->title ?? '—');
        $mallName = optional($offer->mall)->name_ar ?? optional($offer->mall)->name_en ?? optional($offer->mall)->name;

        $cover = $this->firstOfferImageDataUri($offer);

        return [
            'data' => $orders,
            'summary' => [
                'offer' => $title,
                'merchant' => optional($offer->merchant)->company_name ?? '—',
                'category' => ($offer->category?->name_ar ?? $offer->category?->name_en) ?? '—',
                'mall' => $mallName ?? '—',
                'status' => (string) ($offer->status ?? '—'),
                'price' => (float) ($offer->price ?? 0),
                'units_sold' => $unitsSold,
                'line_revenue_paid' => round($lineRevenue, 2),
                'orders_touching_offer' => $orders->count(),
            ],
            'chart_series' => $this->dailyPaidRevenueSeries($orders),
            'chart_series_2' => [],
            'entity_meta' => [
                'title_ar' => 'تقرير عرض: '.$title,
                'title_en' => 'Offer insight: '.$title,
                'subtitle_ar' => 'طلبات تحتوي هذا العرض خلال الفترة',
                'subtitle_en' => 'Orders that include this offer in the period',
                'image_data_uri' => $cover,
                'tags' => [
                    ['ar' => 'معرّف العرض', 'en' => 'Offer ID', 'value' => (string) $offerId],
                    ['ar' => 'التاجر', 'en' => 'Merchant', 'value' => (string) (optional($offer->merchant)->company_name ?? '—')],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildCategoryInsight(int $categoryId, array $filters): array
    {
        $category = Category::find($categoryId);
        if (! $category) {
            return ['data' => collect(), 'summary' => ['not_found' => true], 'chart_series' => [], 'chart_series_2' => [], 'entity_meta' => []];
        }

        $offerIds = Offer::where('category_id', $categoryId)->pluck('id');
        $q = Order::with(['user', 'merchant', 'items.offer']);
        if ($offerIds->isNotEmpty()) {
            $q->whereHas('items', fn ($q2) => $q2->whereIn('offer_id', $offerIds));
        } else {
            $q->whereRaw('0 = 1');
        }
        $this->applyDateRangeToOrderQuery($q, $filters);
        $orders = $q->orderByDesc('created_at')->get();

        $topItems = collect();
        if ($offerIds->isNotEmpty()) {
            $topItems = OrderItem::query()
                ->whereIn('offer_id', $offerIds)
                ->whereHas('order', function ($q2) use ($filters) {
                    $q2->where('payment_status', 'paid');
                    $this->applyDateRangeToOrderQuery($q2, $filters);
                })
                ->selectRaw('offer_id, SUM(quantity) as units, SUM(total_price) as revenue')
                ->groupBy('offer_id')
                ->orderByDesc('revenue')
                ->limit(8)
                ->get();
        }

        $offers = $topItems->isEmpty()
            ? collect()
            : Offer::whereIn('id', $topItems->pluck('offer_id'))->get()->keyBy('id');
        $bar2 = [];
        foreach ($topItems as $row) {
            $o = $offers->get($row->offer_id);
            $label = $o ? (string) ($o->title_ar ?? $o->title_en ?? $o->title ?? '#'.$row->offer_id) : (string) $row->offer_id;
            $bar2[] = ['label' => mb_substr($label, 0, 32), 'value' => (float) $row->revenue];
        }

        $catName = (string) ($category->name_ar ?? $category->name_en ?? '—');

        return [
            'data' => $orders,
            'summary' => [
                'category' => $catName,
                'offers_in_category' => $offerIds->count(),
                'orders_in_period' => $orders->count(),
                'paid_revenue' => round((float) $orders->where('payment_status', 'paid')->sum('total_amount'), 2),
            ],
            'chart_series' => $this->dailyPaidRevenueSeries($orders),
            'chart_series_2' => $bar2,
            'entity_meta' => [
                'title_ar' => 'تقرير فئة: '.$catName,
                'title_en' => 'Category insight: '.$catName,
                'subtitle_ar' => 'الطلبات المرتبطة بعروض هذه الفئة',
                'subtitle_en' => 'Orders linked to offers in this category',
                'image_data_uri' => $this->categoryImageDataUri($category),
                'tags' => [
                    ['ar' => 'معرّف الفئة', 'en' => 'Category ID', 'value' => (string) $categoryId],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildMallInsight(int $mallId, array $filters): array
    {
        $mall = Mall::find($mallId);
        if (! $mall) {
            return ['data' => collect(), 'summary' => ['not_found' => true], 'chart_series' => [], 'chart_series_2' => [], 'entity_meta' => []];
        }

        $offerIds = Offer::where('mall_id', $mallId)->pluck('id');
        $q = Order::with(['user', 'merchant', 'items.offer']);
        if ($offerIds->isNotEmpty()) {
            $q->whereHas('items', fn ($q2) => $q2->whereIn('offer_id', $offerIds));
        } else {
            $q->whereRaw('0 = 1');
        }
        $this->applyDateRangeToOrderQuery($q, $filters);
        $orders = $q->orderByDesc('created_at')->get();

        $topItems = collect();
        if ($offerIds->isNotEmpty()) {
            $topItems = OrderItem::query()
                ->whereIn('offer_id', $offerIds)
                ->whereHas('order', function ($q2) use ($filters) {
                    $q2->where('payment_status', 'paid');
                    $this->applyDateRangeToOrderQuery($q2, $filters);
                })
                ->selectRaw('offer_id, SUM(total_price) as revenue')
                ->groupBy('offer_id')
                ->orderByDesc('revenue')
                ->limit(8)
                ->get();
        }

        $offers = $topItems->isEmpty()
            ? collect()
            : Offer::whereIn('id', $topItems->pluck('offer_id'))->get()->keyBy('id');
        $bar2 = [];
        foreach ($topItems as $row) {
            $o = $offers->get($row->offer_id);
            $label = $o ? (string) ($o->title_ar ?? $o->title_en ?? $o->title ?? '#'.$row->offer_id) : (string) $row->offer_id;
            $bar2[] = ['label' => mb_substr($label, 0, 32), 'value' => (float) $row->revenue];
        }

        $mallName = (string) ($mall->name_ar ?? $mall->name_en ?? $mall->name ?? '—');

        return [
            'data' => $orders,
            'summary' => [
                'mall' => $mallName,
                'offers_in_mall' => $offerIds->count(),
                'orders_in_period' => $orders->count(),
                'paid_revenue' => round((float) $orders->where('payment_status', 'paid')->sum('total_amount'), 2),
            ],
            'chart_series' => $this->dailyPaidRevenueSeries($orders),
            'chart_series_2' => $bar2,
            'entity_meta' => [
                'title_ar' => 'تقرير مول: '.$mallName,
                'title_en' => 'Mall insight: '.$mallName,
                'subtitle_ar' => 'حركة الطلبات للعروض المرتبطة بهذا المول',
                'subtitle_en' => 'Order activity for offers at this mall',
                'image_data_uri' => $this->mallImageDataUri($mall),
                'tags' => [
                    ['ar' => 'معرّف المول', 'en' => 'Mall ID', 'value' => (string) $mallId],
                ],
            ],
        ];
    }

    /**
     * @param  Builder<Order>  $q
     * @param  array<string, mixed>  $filters
     */
    private function applyDateRangeToOrderQuery(Builder $q, array $filters): void
    {
        if (! empty($filters['from'])) {
            $q->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->where('created_at', '<=', $filters['to']);
        }
    }

    /**
     * @param  iterable<int, Order>  $orders
     * @return array<int, array{label: string, value: float}>
     */
    private function dailyPaidRevenueSeries(iterable $orders): array
    {
        $rows = [];
        foreach ($orders as $order) {
            if (($order->payment_status ?? '') !== 'paid') {
                continue;
            }
            $d = $order->created_at ? $order->created_at->format('Y-m-d') : '';
            if ($d === '') {
                continue;
            }
            $rows[$d] = ($rows[$d] ?? 0) + (float) $order->total_amount;
        }
        ksort($rows);
        $out = [];
        foreach ($rows as $day => $amt) {
            $out[] = ['label' => (string) $day, 'value' => round((float) $amt, 2)];
        }

        return $out;
    }

    private function firstOfferImageDataUri(Offer $offer): ?string
    {
        $images = $offer->offer_images;
        if (! is_array($images) || $images === []) {
            return null;
        }
        $first = $images[0] ?? null;
        if (! is_string($first) || $first === '') {
            return null;
        }

        return $this->storageRelativeToDataUri($first);
    }

    private function categoryImageDataUri(Category $category): ?string
    {
        $img = $category->image ?? null;
        if (! is_string($img) || $img === '') {
            return null;
        }

        return $this->storageRelativeToDataUri($img);
    }

    private function mallImageDataUri(Mall $mall): ?string
    {
        $img = $mall->image_url ?? null;
        if (is_string($img) && str_contains($img, 'storage/')) {
            $pathPart = parse_url($img, PHP_URL_PATH);
            if (is_string($pathPart) && str_contains($pathPart, 'storage/')) {
                $rel = ltrim(str_replace(['/storage/', 'storage/'], '', $pathPart), '/');

                return $this->storageRelativeToDataUri($rel);
            }
        }
        if (is_array($mall->images ?? null) && $mall->images !== []) {
            $first = $mall->images[0] ?? null;
            if (is_string($first)) {
                return $this->storageRelativeToDataUri($first);
            }
        }

        return null;
    }

    private function storageRelativeToDataUri(string $relative): ?string
    {
        $relative = ltrim($relative, '/');
        $full = storage_path('app/public/'.$relative);
        if (! is_readable($full)) {
            return null;
        }
        $mime = @mime_content_type($full) ?: 'image/jpeg';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
    }

    /**
     * Map markers for admin: end-users with last GPS vs merchant branches with coordinates.
     * Not tied to report date filters — shows all known coordinates (capped) for a useful map.
     *
     * @param  array<string, mixed>  $filters
     * @return array{users: list<array<string, mixed>>, merchant_branches: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function generateGeoDistribution(array $filters): array
    {
        $filters = $this->normalizeReportFilters($filters);
        $max = 2500;

        $userQuery = User::query()
            ->select(['id', 'name', 'city', 'last_location_lat', 'last_location_lng'])
            ->whereHas('role', fn ($q) => $q->where('name', 'user'))
            ->whereNotNull('last_location_lat')
            ->whereNotNull('last_location_lng')
            ->where('last_location_lat', '!=', 0)
            ->where('last_location_lng', '!=', 0);

        if (! empty($filters['city'])) {
            $userQuery->where('city', $filters['city']);
        }

        $userRows = $userQuery->orderByDesc('updated_at')
            ->limit($max)
            ->get();

        $users = [];
        foreach ($userRows as $u) {
            $lat = (float) $u->last_location_lat;
            $lng = (float) $u->last_location_lng;
            if (! $this->isPlausibleMapCoordinate($lat, $lng)) {
                continue;
            }
            $cityLabel = $u->city;
            if ($cityLabel !== null && ! is_scalar($cityLabel)) {
                $cityLabel = null;
            }

            $users[] = [
                'id' => $u->id,
                'label' => (string) ($u->name ?? ''),
                'lat' => $lat,
                'lng' => $lng,
                'city' => $cityLabel !== null ? (string) $cityLabel : null,
            ];
        }

        $branchQuery = Branch::query()
            ->select(['id', 'merchant_id', 'name', 'name_ar', 'name_en', 'lat', 'lng', 'address'])
            ->with(['merchant:id,company_name,company_name_ar,company_name_en'])
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('lat', '!=', 0)
            ->where('lng', '!=', 0);

        $merchantFk = $filters['merchant_id'] ?? $filters['merchant'] ?? null;
        if ($merchantFk !== null && $merchantFk !== '') {
            $branchQuery->where('merchant_id', (int) $merchantFk);
        }

        $branchRows = $branchQuery->orderByDesc('updated_at')
            ->limit($max)
            ->get();

        $merchantBranches = [];
        foreach ($branchRows as $b) {
            $lat = (float) $b->lat;
            $lng = (float) $b->lng;
            if (! $this->isPlausibleMapCoordinate($lat, $lng)) {
                continue;
            }
            $m = $b->merchant;
            $merchantName = $m
                ? (string) ($m->company_name_ar ?? $m->company_name_en ?? $m->company_name ?? '')
                : '';
            $branchLabel = (string) ($b->name_ar ?? $b->name_en ?? $b->name ?? '');

            $merchantBranches[] = [
                'id' => $b->id,
                'merchant_id' => $b->merchant_id,
                'label' => $merchantName !== '' ? $merchantName : '—',
                'branch_label' => $branchLabel,
                'lat' => $lat,
                'lng' => $lng,
                'address' => $b->address ? (string) $b->address : null,
            ];
        }

        return [
            'users' => $users,
            'merchant_branches' => $merchantBranches,
            'summary' => [
                'user_points' => count($users),
                'merchant_branch_points' => count($merchantBranches),
            ],
        ];
    }

    private function isPlausibleMapCoordinate(float $lat, float $lng): bool
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false;
        }

        return $lat >= 21.0 && $lat <= 32.0 && $lng >= 24.0 && $lng <= 37.0;
    }
}
