<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationReport;
use App\Models\FinancialTransaction;
use App\Models\MerchantWallet;
use App\Models\Order;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Sales report
     */
    public function salesReport(Request $request): JsonResponse
    {
        $query = Order::with(['merchant', 'items.offer.category'])
            ->where('payment_status', 'paid');

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        if ($request->has('merchant')) {
            $query->where('merchant_id', $request->merchant);
        }

        if ($request->has('category')) {
            $query->whereHas('items.offer', function ($q) use ($request) {
                $q->where('category_id', $request->category);
            });
        }

        $orders = $query->get();

        $report = [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total_amount'),
            'total_coupons_generated' => $orders->sum(function ($order) {
                return $order->coupons()->count();
            }),
            'total_coupons_activated' => $orders->sum(function ($order) {
                return $order->coupons()->where('status', 'activated')->count();
            }),
            'conversion_rate' => $orders->count() > 0
                ? ($orders->sum(function ($order) {
                    return $order->coupons()->where('status', 'activated')->count();
                }) / $orders->sum(function ($order) {
                    return $order->coupons()->count();
                })) * 100
                : 0,
            'by_merchant' => $orders->groupBy('merchant_id')->map(function ($merchantOrders) {
                return [
                    'merchant_id' => $merchantOrders->first()->merchant_id,
                    'merchant_name' => $merchantOrders->first()->merchant->company_name ?? 'N/A',
                    'total_orders' => $merchantOrders->count(),
                    'total_revenue' => $merchantOrders->sum('total_amount'),
                ];
            })->values(),
        ];

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * Export sales report as CSV
     */
    public function exportSalesReport(Request $request): StreamedResponse
    {
        $query = Order::with(['merchant', 'items.offer.category'])
            ->where('payment_status', 'paid');

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $orders = $query->get();

        $filename = 'sales_report_'.date('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Order ID', 'Date', 'Merchant', 'Total Amount', 'Payment Method', 'Coupons Generated', 'Coupons Activated']);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->id,
                    $order->created_at->format('Y-m-d H:i:s'),
                    $order->merchant->company_name ?? 'N/A',
                    $order->total_amount,
                    $order->payment_method,
                    $order->coupons()->count(),
                    $order->coupons()->where('status', 'activated')->count(),
                ]);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Get financial dashboard
     */
    public function financialDashboard(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->endOfMonth()->toDateString());

        $platformRevenue = FinancialTransaction::where('transaction_type', 'commission')
            ->where('transaction_flow', 'outgoing')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $totalPayouts = Withdrawal::where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->sum('amount');

        $outstandingBalances = MerchantWallet::sum('balance');

        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();

            $monthlyData[] = [
                'month' => $monthStart->format('Y-m'),
                'revenue' => FinancialTransaction::where('transaction_type', 'commission')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount'),
                'payouts' => Withdrawal::where('status', 'completed')
                    ->whereBetween('completed_at', [$monthStart, $monthEnd])
                    ->sum('amount'),
            ];
        }

        return response()->json([
            'data' => [
                'platform_revenue' => $platformRevenue,
                'total_payouts' => $totalPayouts,
                'outstanding_balances' => $outstandingBalances,
                'net_profit' => $platformRevenue - $totalPayouts,
                'monthly_analytics' => $monthlyData,
            ],
        ]);
    }

    /**
     * Get activation reports
     */
    public function activationReports(Request $request): JsonResponse
    {
        $query = ActivationReport::with(['coupon', 'merchant', 'user', 'order'])
            ->orderBy('created_at', 'desc');

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $reports = $query->paginate($request->get('per_page', 50));

        $data = $reports->getCollection()->map(function ($report) {
            return [
                'id' => $report->id,
                'coupon_id' => $report->coupon_id,
                'merchant_id' => $report->merchant_id,
                'user_id' => $report->user_id,
                'order_id' => $report->order_id,
                'coupon_code' => $report->coupon ? $report->coupon->code : null,
                'merchant_name' => $report->merchant ? $report->merchant->company_name : null,
                'user_name' => $report->user ? $report->user->name : null,
                'order_total' => $report->order ? $report->order->total_amount : null,
                'activation_type' => $report->activation_type,
                'created_at' => $report->created_at ? $report->created_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }
}
