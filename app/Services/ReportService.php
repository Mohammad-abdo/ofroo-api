<?php

namespace App\Services;

use App\Models\User;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\FinancialTransaction;
use App\Models\ActivationReport;
use App\Models\Coupon;
use App\Models\OrderItem;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Generate Users Report
     */
    public function generateUsersReport(array $filters = []): array
    {
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
        $query = Merchant::with(['user', 'branches']);

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
        $query = Order::with(['user', 'merchant', 'items.offer']);

        if (isset($filters['merchant'])) {
            $query->where('merchant_id', $filters['merchant']);
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
        $query = Offer::with(['merchant', 'category']);

        if (isset($filters['merchant'])) {
            $query->where('merchant_id', $filters['merchant']);
        }

        if (isset($filters['category'])) {
            $query->where('category_id', $filters['category']);
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
        $query = Payment::with(['order.merchant', 'order.user']);

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

        $pdf = Pdf::loadView('reports.pdf.report', [
            'data' => $data,
            'filters' => $filters,
            'reportType' => $reportType,
            'generated_at' => now(),
        ]);

        $filename = "report_{$reportType}_" . date('Y-m-d_H-i-s') . '.pdf';
        $path = 'reports/' . $filename;
        
        \Storage::disk('public')->put($path, $pdf->output());
        
        return \Storage::url($path);
    }

    /**
     * Export report to Excel
     */
    public function exportToExcel(string $reportType, array $data, array $filters = []): string
    {
        $filename = "report_{$reportType}_" . date('Y-m-d_H-i-s') . '.xlsx';
        Excel::store(new \App\Exports\ReportExport($data, $reportType), 'reports/' . $filename, 'public');
        return \Storage::url('reports/' . $filename);
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
}

