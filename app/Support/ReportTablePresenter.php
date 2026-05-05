<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds bilingual headers + flat cell rows for PDF (and keeps parity with Excel export).
 */
class ReportTablePresenter
{
    /**
     * Avoid totally empty table cells — mPDF can throw on empty string buffer offsets.
     */
    public static function pdfStr(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE);
            if (! is_string($json) || $json === '') {
                return '—';
            }

            return Str::limit($json, 400);
        }
        if (is_object($value)) {
            if ($value instanceof CarbonInterface) {
                return self::fmtDate($value);
            }
            if (method_exists($value, 'toArray')) {
                /** @var mixed $arr */
                $arr = $value->toArray();
                if (is_array($arr)) {
                    $json = json_encode($arr, JSON_UNESCAPED_UNICODE);

                    return is_string($json) && $json !== '' ? Str::limit($json, 400) : '—';
                }
            }
            if (method_exists($value, '__toString')) {
                $s = trim((string) $value);

                return $s === '' ? '—' : Str::limit($s, 400);
            }

            // Never leak PHP object debug strings into PDF.
            return '—';
        }
        $s = trim((string) $value);

        return $s === '' ? '—' : $s;
    }

    /**
     * @return array{ar: string, en: string}
     */
    public static function reportTitles(string $reportType): array
    {
        $t = self::normalizeType($reportType);

        return match ($reportType) {
            'merchant_insight' => [
                'ar' => 'تحليل تفصيلي — تاجر',
                'en' => 'Deep insight — merchant',
            ],
            'offer_insight' => [
                'ar' => 'تحليل تفصيلي — عرض',
                'en' => 'Deep insight — offer',
            ],
            'category_insight' => [
                'ar' => 'تحليل تفصيلي — فئة',
                'en' => 'Deep insight — category',
            ],
            'mall_insight' => [
                'ar' => 'تحليل تفصيلي — مول',
                'en' => 'Deep insight — mall',
            ],
            'sales' => [
                'ar' => 'تقرير المبيعات والطلبات',
                'en' => 'Sales & orders report',
            ],
            'commission' => [
                'ar' => 'تقرير العمولات والمعاملات المالية',
                'en' => 'Commission & financial transactions',
            ],
            default => match ($t) {
                'users' => ['ar' => 'تقرير المستخدمين', 'en' => 'Users report'],
                'merchants' => ['ar' => 'تقرير التجار', 'en' => 'Merchants report'],
                'orders' => ['ar' => 'تقرير الطلبات', 'en' => 'Orders report'],
                'products' => ['ar' => 'تقرير العروض والمنتجات', 'en' => 'Offers & products report'],
                'payments' => ['ar' => 'تقرير المدفوعات', 'en' => 'Payments report'],
                'financial' => ['ar' => 'التقرير المالي', 'en' => 'Financial report'],
                default => ['ar' => 'تقرير', 'en' => 'Report'],
            },
        };
    }

    public static function forPdf(string $reportType, array $report, string $lang): array
    {
        $isAr = $lang === 'ar';
        $raw = $report['data'] ?? null;

        if ($raw instanceof Collection) {
            $raw = $raw->values();
        }

        // Metrics-style payload (associative array), e.g. GPS / funnel single object
        if (is_array($raw) && ! self::isListOfRows($raw)) {
            return self::metricsTable($raw, $isAr, $reportType);
        }

        $items = collect($raw ?? [])->values();
        $type = match ($reportType) {
            'merchant_insight', 'offer_insight', 'category_insight', 'mall_insight' => 'orders',
            default => self::normalizeType($reportType),
        };

        return match ($type) {
            'users' => self::usersTable($items, $isAr),
            'merchants' => self::merchantsTable($items, $isAr),
            'orders' => self::ordersTable($items, $isAr),
            'products' => self::productsTable($items, $isAr),
            'payments' => self::paymentsTable($items, $isAr),
            'financial' => self::financialTable($items, $isAr),
            default => self::genericTable($items, $isAr),
        };
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, mode: string}
     */
    public static function summaryBlocks(array $summary, string $lang, string $reportType): array
    {
        $isAr = $lang === 'ar';
        $blocks = [];
        foreach ($summary as $key => $value) {
            if (is_scalar($value)) {
                $blocks[] = [
                    'label' => self::translateSummaryKey((string) $key, $isAr),
                    'value' => self::formatScalar($value),
                ];

                continue;
            }
            if ($value instanceof Collection) {
                $value = $value->all();
            }
            if (is_array($value)) {
                foreach ($value as $subK => $subV) {
                    $label = self::translateSummaryKey((string) $key, $isAr).' · '.$subK;
                    if (is_scalar($subV)) {
                        $blocks[] = ['label' => $label, 'value' => self::formatScalar($subV)];
                    } elseif (is_array($subV)) {
                        $blocks[] = ['label' => $label, 'value' => json_encode($subV, JSON_UNESCAPED_UNICODE)];
                    } else {
                        $blocks[] = ['label' => $label, 'value' => self::pdfStr($subV)];
                    }
                }
            }
        }

        return $blocks;
    }

    /**
     * Final pass: mPDF Td.php assumes non-empty textbuffer segments; empty cells crash with "Uninitialized string offset 0".
     *
     * @param  array{headers?: array<int, string>, rows?: array<int, array<int, mixed>>, mode?: string}  $table
     * @param  array<int, array{label?: string, value?: string}>  $summaryBlocks
     * @return array{0: array, 1: array}
     */
    public static function sanitizeMpdfTablePayload(array $table, array $summaryBlocks): array
    {
        foreach ($table['headers'] ?? [] as $i => $h) {
            $table['headers'][$i] = self::pdfStr($h);
        }
        foreach ($table['rows'] ?? [] as $ri => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $ci => $cell) {
                $table['rows'][$ri][$ci] = self::pdfStr($cell);
            }
        }

        $cleanSummary = [];
        foreach ($summaryBlocks as $b) {
            $cleanSummary[] = [
                'label' => self::pdfStr($b['label'] ?? ''),
                'value' => self::pdfStr($b['value'] ?? ''),
            ];
        }

        return [$table, $cleanSummary];
    }

    protected static function normalizeType(string $reportType): string
    {
        return match ($reportType) {
            'sales' => 'orders',
            'commission' => 'financial',
            default => $reportType,
        };
    }

    protected static function isListOfRows(array $raw): bool
    {
        if ($raw === []) {
            return true;
        }
        $keys = array_keys($raw);

        return $keys === range(0, count($raw) - 1);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function metricsTable(array $data, bool $isAr, string $reportType): array
    {
        $headers = [
            $isAr ? 'المؤشر' : 'Metric',
            $isAr ? 'القيمة' : 'Value',
        ];
        $rows = [];
        foreach ($data as $k => $v) {
            if (is_scalar($v)) {
                $rows[] = [
                    self::translateMetricKey((string) $k, $isAr),
                    self::formatScalar($v),
                ];
            } elseif (is_array($v)) {
                $rows[] = [
                    self::translateMetricKey((string) $k, $isAr),
                    Str::limit(json_encode($v, JSON_UNESCAPED_UNICODE), 400),
                ];
            }
        }

        return ['headers' => $headers, 'rows' => $rows, 'mode' => 'metrics'];
    }

    protected static function usersTable(Collection $items, bool $isAr): array
    {
        $headers = $isAr
            ? ['#', 'الاسم', 'البريد', 'الجوال', 'الدور', 'تاريخ الإنشاء']
            : ['#', 'Name', 'Email', 'Phone', 'Role', 'Created'];

        $rows = [];
        foreach ($items as $row) {
            if (! is_object($row)) {
                continue;
            }
            $rows[] = [
                (string) ($row->id ?? ''),
                (string) ($row->name ?? '—'),
                (string) ($row->email ?? '—'),
                (string) ($row->phone ?? '—'),
                (string) (optional($row->role)->name ?? '—'),
                self::fmtDate($row->created_at ?? null),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows, 'mode' => 'tabular'];
    }

    protected static function merchantsTable(Collection $items, bool $isAr): array
    {
        $headers = $isAr
            ? ['#', 'الشركة', 'البريد', 'الهاتف', 'معتمد', 'تاريخ الإنشاء']
            : ['#', 'Company', 'Email', 'Phone', 'Approved', 'Created'];

        $rows = [];
        foreach ($items as $row) {
            if (! is_object($row)) {
                continue;
            }
            $rows[] = [
                (string) ($row->id ?? ''),
                (string) ($row->company_name ?? $row->company_name_ar ?? $row->company_name_en ?? '—'),
                (string) (optional($row->user)->email ?? '—'),
                (string) ($row->phone ?? '—'),
                isset($row->approved) ? ($row->approved ? ($isAr ? 'نعم' : 'Yes') : ($isAr ? 'لا' : 'No')) : '—',
                self::fmtDate($row->created_at ?? null),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows, 'mode' => 'tabular'];
    }

    protected static function ordersTable(Collection $items, bool $isAr): array
    {
        $headers = $isAr
            ? ['#', 'المستخدم', 'التاجر', 'المبلغ', 'الدفع', 'الحالة', 'التاريخ']
            : ['#', 'User', 'Merchant', 'Amount', 'Method', 'Status', 'Date'];

        $rows = [];
        foreach ($items as $row) {
            if (! is_object($row)) {
                continue;
            }
            $rows[] = [
                (string) ($row->id ?? ''),
                (string) (optional($row->user)->name ?? '—'),
                (string) (optional($row->merchant)->company_name ?? '—'),
                self::formatScalar($row->total_amount ?? 0),
                (string) ($row->payment_method ?? '—'),
                (string) ($row->payment_status ?? '—'),
                self::fmtDate($row->created_at ?? null),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows, 'mode' => 'tabular'];
    }

    protected static function productsTable(Collection $items, bool $isAr): array
    {
        $headers = $isAr
            ? ['#', 'العنوان', 'التاجر', 'السعر', 'الحالة', 'التاريخ']
            : ['#', 'Title', 'Merchant', 'Price', 'Status', 'Date'];

        $rows = [];
        foreach ($items as $row) {
            if (! is_object($row)) {
                continue;
            }
            $rows[] = [
                (string) ($row->id ?? ''),
                (string) ($row->title_ar ?? $row->title_en ?? $row->title ?? '—'),
                (string) (optional($row->merchant)->company_name ?? '—'),
                self::formatScalar($row->price ?? 0),
                (string) ($row->status ?? '—'),
                self::fmtDate($row->created_at ?? null),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows, 'mode' => 'tabular'];
    }

    protected static function paymentsTable(Collection $items, bool $isAr): array
    {
        $headers = $isAr
            ? ['#', 'طلب', 'المبلغ', 'البوابة', 'الحالة', 'التاريخ']
            : ['#', 'Order', 'Amount', 'Gateway', 'Status', 'Date'];

        $rows = [];
        foreach ($items as $row) {
            if (! is_object($row)) {
                continue;
            }
            $rows[] = [
                (string) ($row->id ?? ''),
                (string) ($row->order_id ?? '—'),
                self::formatScalar($row->amount ?? 0),
                (string) ($row->gateway ?? '—'),
                (string) ($row->status ?? '—'),
                self::fmtDate($row->created_at ?? null),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows, 'mode' => 'tabular'];
    }

    protected static function financialTable(Collection $items, bool $isAr): array
    {
        $headers = $isAr
            ? ['#', 'التاجر', 'النوع', 'الاتجاه', 'المبلغ', 'الحالة', 'التاريخ']
            : ['#', 'Merchant', 'Type', 'Flow', 'Amount', 'Status', 'Date'];

        $rows = [];
        foreach ($items as $row) {
            if (! is_object($row)) {
                continue;
            }
            $rows[] = [
                (string) ($row->id ?? ''),
                self::pdfStr(optional($row->merchant)->company_name ?? null),
                self::pdfStr($row->transaction_type ?? null),
                self::pdfStr($row->transaction_flow ?? null),
                self::formatScalar($row->amount ?? 0),
                self::pdfStr($row->status ?? null),
                self::fmtDate($row->created_at ?? null),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows, 'mode' => 'tabular'];
    }

    protected static function genericTable(Collection $items, bool $isAr): array
    {
        $headers = $isAr ? ['#', 'البيانات'] : ['#', 'Data'];
        $rows = [];
        $i = 1;
        foreach ($items as $row) {
            if (is_object($row) && method_exists($row, 'getAttributes')) {
                $attrs = array_diff_key($row->getAttributes(), array_flip(['password', 'remember_token', 'metadata']));
                $rows[] = [(string) $i, Str::limit(json_encode($attrs, JSON_UNESCAPED_UNICODE), 500)];
            } else {
                $rows[] = [(string) $i, Str::limit(json_encode($row, JSON_UNESCAPED_UNICODE), 500)];
            }
            $i++;
        }

        return ['headers' => $headers, 'rows' => $rows, 'mode' => 'tabular'];
    }

    protected static function translateSummaryKey(string $key, bool $isAr): string
    {
        $map = [
            'total' => ['إجمالي السجلات', 'Total records'],
            'total_orders' => ['إجمالي الطلبات', 'Total orders'],
            'total_revenue' => ['إجمالي الإيرادات', 'Total revenue'],
            'total_commission' => ['إجمالي العمولة', 'Total commission'],
            'net' => ['صافي', 'Net'],
            'total_incoming' => ['إجمالي الوارد', 'Total incoming'],
            'total_outgoing' => ['إجمالي الصادر', 'Total outgoing'],
            'approved' => ['معتمد', 'Approved'],
            'pending' => ['قيد الانتظار', 'Pending'],
            'by_role' => ['حسب الدور', 'By role'],
            'by_status' => ['حسب الحالة', 'By status'],
            'by_method' => ['حسب طريقة الدفع', 'By payment method'],
            'by_type' => ['حسب النوع', 'By type'],
            'by_gateway' => ['حسب البوابة', 'By gateway'],
        ];

        $pair = $map[$key] ?? null;
        if (is_array($pair)) {
            $idx = $isAr ? 0 : 1;

            return $pair[$idx] ?? str_replace('_', ' ', $key);
        }

        return str_replace('_', ' ', $key);
    }

    protected static function translateMetricKey(string $key, bool $isAr): string
    {
        $map = [
            'total_orders' => ['إجمالي الطلبات', 'Total orders'],
            'orders_with_gps' => ['طلبات مع GPS', 'Orders with GPS'],
            'orders_without_gps' => ['طلبات بدون GPS', 'Orders without GPS'],
            'nearby_orders' => ['طلبات قريبة', 'Nearby orders'],
            'total_users' => ['المستخدمون', 'Users'],
            'viewed_offers' => ['عروض تم عرضها', 'Offers viewed'],
            'added_to_cart' => ['إضافة للسلة', 'Added to cart'],
            'initiated_checkout' => ['بدء الدفع', 'Checkout started'],
            'completed_payment' => ['دفع مكتمل', 'Completed payment'],
            'activated_coupons' => ['كوبونات مفعّلة', 'Activated coupons'],
        ];

        $pair = $map[$key] ?? null;
        if (is_array($pair)) {
            $idx = $isAr ? 0 : 1;

            return $pair[$idx] ?? str_replace('_', ' ', $key);
        }

        return str_replace('_', ' ', $key);
    }

    protected static function formatScalar(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return is_numeric($value) ? number_format((float) $value, 2) : (string) $value;
        }

        $s = (string) $value;

        return $s === '' ? '—' : $s;
    }

    protected static function fmtDate(mixed $d): string
    {
        if ($d instanceof CarbonInterface) {
            return $d->format('Y-m-d H:i');
        }
        if (is_string($d) && $d !== '') {
            try {
                return Carbon::parse($d)->format('Y-m-d H:i');
            } catch (\Throwable) {
                return $d;
            }
        }

        return '—';
    }
}
