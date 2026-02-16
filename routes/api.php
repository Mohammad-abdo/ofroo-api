<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FinancialController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\MerchantProfileController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\QrActivationController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MerchantStaffController;
use App\Http\Controllers\Api\AdminWalletController;
use App\Http\Controllers\Api\WalletTransactionController;
use App\Http\Controllers\Api\MerchantVerificationController;
use App\Http\Controllers\Api\MerchantWarningController;
use App\Http\Controllers\Api\ReviewModerationController;
use App\Http\Controllers\Api\RegulatoryCheckController;
use App\Http\Controllers\Api\FinancialReportsCacheController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Info endpoint
Route::get('/', function () {
    return response()->json([
        'name' => 'OFROO API',
        'version' => '1.0.0',
        'status' => 'active',
        'endpoints' => [
            'auth' => '/api/auth',
            'categories' => '/api/categories',
            'offers' => '/api/offers',
            'cart' => '/api/cart',
            'orders' => '/api/orders',
        ],
    ]);
});


// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register-merchant', [AuthController::class, 'registerMerchant'])->middleware('throttle:3,1');
    Route::post('/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:3,1');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
});

// Public categories
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

// Public offers
Route::get('/offers', [OfferController::class, 'index']);
Route::get('/offers/{offer}', [OfferController::class, 'show']);
Route::get('/offers/{offer}/coupons', [CouponController::class, 'index']);

// للتاجر: لليوزر يشوف بيانات التجار وعروضهم (بدون مصادقة)
Route::get('/merchants', [MerchantProfileController::class, 'index']);
Route::get('/merchants/{id}/offers', [MerchantProfileController::class, 'offers']);
Route::get('/merchants/{id}', [MerchantProfileController::class, 'show'])->where('id', '[0-9]+');

// Test route without middleware
Route::get('/test-notifications', function () {
    $notifications = \App\Models\AdminNotification::with('creator')->take(5)->get();
    return response()->json([
        'message' => 'Test successful',
        'count' => $notifications->count(),
        'data' => $notifications
    ]);
});

// Test admin authentication
Route::get('/test-admin-auth', function () {
    try {
        $user = auth('sanctum')->user();
        return response()->json([
            'message' => 'Auth test',
            'authenticated' => $user ? true : false,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? $user->role->name : 'no role',
                'is_admin' => $user->isAdmin()
            ] : null
        ]);
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
})->middleware('auth:sanctum');

// Test admin notifications directly
Route::get('/test-admin-notifications', function () {
    try {
        $notifications = \App\Models\AdminNotification::with('creator')->take(5)->get();
        return response()->json([
            'message' => 'Admin notifications test',
            'count' => $notifications->count(),
            'data' => $notifications
        ]);
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
});

// Test mark notification as read
Route::post('/test-mark-read/{id}', function ($id) {
    try {
        $notification = \App\Models\AdminNotification::findOrFail($id);
        $notification->update(['read_at' => now()]);
        
        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $notification->fresh()
        ]);
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Offers
    Route::prefix('offers')->group(function () {
        Route::get('/', [OfferController::class, 'index']);
        Route::post('/', [OfferController::class, 'store']);
        Route::get('/{offer}', [OfferController::class, 'show']);
        Route::put('/{offer}', [OfferController::class, 'update']);
        Route::delete('/{offer}', [OfferController::class, 'destroy']);
        Route::post('/{offer}/favorite', [OfferController::class, 'toggleFavorite']);
        Route::post('/{offer}/status', [OfferController::class, 'toggleStatus']);

        // Nested Coupons
        Route::prefix('{offer}/coupons')->group(function () {
            Route::get('/', [CouponController::class, 'index']);
            Route::post('/', [CouponController::class, 'store']);
            Route::put('/{coupon}', [CouponController::class, 'update']);
            Route::delete('/{coupon}', [CouponController::class, 'destroy']);
        });
    });

    // Cart (يتطلب تسجيل الدخول)
    Route::prefix('cart')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/add', [CartController::class, 'add']);
        Route::put('/{id}', [CartController::class, 'update']);
        Route::delete('/{id}', [CartController::class, 'remove']);
        Route::delete('/', [CartController::class, 'clear']);
    });

    // Orders & Payment (checkout) - يتطلب مصادقة
    Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::get('/{id}/coupons', [OrderController::class, 'getOrderCoupons']);
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
    });

    // Wallet & Coupons - يتطلب مصادقة
    Route::prefix('wallet')->middleware('auth:sanctum')->group(function () {
        Route::get('/coupons', [OrderController::class, 'walletCoupons']);
    });

    // Reviews - يتطلب مصادقة
    Route::post('/reviews', [OrderController::class, 'createReview']);

    // Support Tickets - يتطلب مصادقة
    Route::prefix('support')->middleware('auth:sanctum')->group(function () {
        Route::post('/tickets', [SupportTicketController::class, 'create']);
        Route::get('/tickets', [SupportTicketController::class, 'index']);
        Route::get('/tickets/{id}', [SupportTicketController::class, 'show']);
    });

    // Loyalty - يتطلب مصادقة
    Route::prefix('loyalty')->middleware('auth:sanctum')->group(function () {
        Route::get('/account', [LoyaltyController::class, 'account']);
        Route::get('/transactions', [LoyaltyController::class, 'transactions']);
        Route::post('/redeem', [LoyaltyController::class, 'redeem']);
    });


    // Search
    Route::get('/search', [OfferController::class, 'search']);

    // WhatsApp Contact
    Route::get('/offers/{id}/whatsapp', [OfferController::class, 'whatsappContact']);

    // User Profile & Settings - يتطلب مصادقة
    Route::prefix('user')->middleware('auth:sanctum')->group(function () {
        // Profile Management
        Route::get('/profile', [UserController::class, 'getProfile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::put('/password', [UserController::class, 'changePassword']);
        Route::put('/phone', [UserController::class, 'updatePhone']);
        
        // Avatar Management
        Route::post('/avatar', [UserController::class, 'uploadAvatar']);
        Route::delete('/avatar', [UserController::class, 'deleteAvatar']);
        
        // Notifications
        Route::get('/notifications', [UserController::class, 'getNotifications']);
        Route::post('/notifications/{id}/read', [UserController::class, 'markNotificationAsRead']);
        Route::post('/notifications/mark-all-read', [UserController::class, 'markAllNotificationsAsRead']);
        Route::delete('/notifications/{id}', [UserController::class, 'deleteNotification']);
        
        // Statistics
        Route::get('/stats', [UserController::class, 'getStats']);
        
        // Settings
        Route::get('/settings', [UserController::class, 'getSettings']);
        Route::put('/settings', [UserController::class, 'updateSettings']);
        
        // Orders History
        Route::get('/orders', [UserController::class, 'getOrdersHistory']);
        
        // Account Management
        Route::delete('/account', [UserController::class, 'deleteAccount']);
    });

    // Merchant routes (التاجر: بياناته، عروضه، كوبوناته)
    Route::prefix('merchant')->middleware('merchant')->group(function () {
        // بيانات التاجر نفسه + عروضه + كوبوناته (اند بوينتس واضحة)
        Route::get('/me', [MerchantController::class, 'getProfile']);
        Route::get('/me/offers', [MerchantController::class, 'offers']);
        Route::get('/me/coupons', [MerchantController::class, 'getCoupons']);

        // Offers Management (Merchant) - توحيد مع منطق الأدمن
        Route::prefix('offers')->group(function () {
            Route::get('/', [MerchantController::class, 'offers']);
            Route::get('/{id}', [MerchantController::class, 'getOffer']);
            Route::post('/', [MerchantController::class, 'createOffer']);
            Route::put('/{id}', [MerchantController::class, 'updateOffer']);
            Route::delete('/{id}', [MerchantController::class, 'deleteOffer']);
            
            // إضافة كوبونات للعرض (منقول من الأدمن)
            Route::post('/{offerId}/coupons', [MerchantController::class, 'storeOfferCoupon']);
        });

        Route::prefix('orders')->group(function () {
            Route::get('/', [MerchantController::class, 'orders']);
        });

        Route::prefix('locations')->group(function () {
            Route::get('/', [MerchantController::class, 'storeLocations']);
            Route::get('/{id}', [MerchantController::class, 'getStoreLocation']);
            Route::post('/', [MerchantController::class, 'createStoreLocation']);
            Route::put('/{id}', [MerchantController::class, 'updateStoreLocation']);
            Route::delete('/{id}', [MerchantController::class, 'deleteStoreLocation']);
        });

        Route::get('/statistics', [MerchantController::class, 'statistics']);

        // Profile Management
        Route::get('/profile', [MerchantController::class, 'getProfile']);
        Route::put('/profile', [MerchantController::class, 'updateProfile']);
        Route::post('/profile/logo', [MerchantController::class, 'uploadLogo']);

        // Coupons Management (Merchant) - توحيد مع منطق الأدمن
        Route::prefix('coupons')->group(function () {
            Route::get('/', [MerchantController::class, 'getCoupons']);
            Route::get('/my-coupons', [MerchantController::class, 'getMyCoupons']); // منقول من الأدمن (allCoupons معدل)
            Route::get('/by-mall/{mallId}', [MerchantController::class, 'getCouponsByMall']); // منقول من الأدمن
            Route::get('/by-category/{categoryId}', [MerchantController::class, 'getCouponsByCategory']); // منقول من الأدمن
            Route::get('/available', [MerchantController::class, 'getAvailableCoupons']); // منقول من الأدمن
            Route::get('/{id}', [MerchantController::class, 'getCoupon']);
            Route::post('/', [MerchantController::class, 'createCoupon']);
            Route::put('/{id}', [MerchantController::class, 'updateCoupon']);
            Route::post('/{id}', [MerchantController::class, 'updateCoupon']); // FormData with _method=PUT
            Route::delete('/{id}', [MerchantController::class, 'deleteCoupon']);
            Route::post('/{id}/activate', [MerchantController::class, 'activateCoupon']);
            Route::post('/{id}/deactivate', [MerchantController::class, 'deactivateCoupon']); // منقول من الأدمن
        });

        // Mall Coupons Management (Merchant)
        Route::get('/mall-coupons', [MerchantController::class, 'getMallCoupons']);

        // Coupon Activations
        Route::get('/coupon-activations', [MerchantController::class, 'getCouponActivations']);

        // Commissions Management
        Route::prefix('commissions')->group(function () {
            Route::get('/', [MerchantController::class, 'getCommissions']);
            Route::get('/transactions', [MerchantController::class, 'getCommissionTransactions']);
            Route::get('/rates', [MerchantController::class, 'getCommissionRates']);
        });

        // Ads Management (Merchant)
        Route::prefix('ads')->group(function () {
            Route::get('/', [MerchantController::class, 'getAds']);
            Route::get('/{id}', [MerchantController::class, 'getAd']);
            Route::post('/', [MerchantController::class, 'createAd']);
            Route::put('/{id}', [MerchantController::class, 'updateAd']);
            Route::delete('/{id}', [MerchantController::class, 'deleteAd']);
            Route::get('/{id}/status', [MerchantController::class, 'getAdStatus']);
        });

        // QR Activation
        Route::prefix('qr')->group(function () {
            Route::post('/scan', [QrActivationController::class, 'scanAndActivate']);
            Route::post('/validate', [QrActivationController::class, 'validateQr']);
            Route::get('/scanner', [QrActivationController::class, 'scannerPage']);
        });

        // Invoices
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('/{id}', [InvoiceController::class, 'show']);
            Route::get('/{id}/download', [InvoiceController::class, 'downloadPdf']);
        });

        // Staff Management
        Route::prefix('staff')->group(function () {
            Route::get('/', [MerchantStaffController::class, 'index']);
            Route::post('/', [MerchantStaffController::class, 'create']);
            Route::put('/{id}', [MerchantStaffController::class, 'update']);
            Route::delete('/{id}', [MerchantStaffController::class, 'delete']);
        });

        // PIN/Biometric Login
        Route::post('/login-pin', [AuthController::class, 'loginWithPin']);
        Route::post('/setup-pin', [MerchantController::class, 'setupPin']);

        // Financial routes
        Route::prefix('financial')->group(function () {
            Route::get('/wallet', [FinancialController::class, 'getWallet']);
            Route::get('/transactions', [FinancialController::class, 'getTransactions']);
            Route::get('/earnings', [FinancialController::class, 'getEarningsReport']);
            Route::post('/expenses', [FinancialController::class, 'recordExpense']);
            Route::get('/expenses', [FinancialController::class, 'getExpenses']);
            Route::post('/withdrawals', [FinancialController::class, 'requestWithdrawal']);
            Route::get('/withdrawals', [FinancialController::class, 'getWithdrawals']);
            Route::get('/sales', [FinancialController::class, 'getSalesTracking']);
        });

        // Wallet Transactions
        Route::prefix('wallet')->group(function () {
            Route::get('/transactions', [WalletTransactionController::class, 'index']);
            Route::get('/transactions/export', [WalletTransactionController::class, 'export']);
        });

        // Merchant Verification
        Route::get('/verification', [MerchantVerificationController::class, 'show']);
        Route::post('/verification/upload', [MerchantVerificationController::class, 'uploadDocuments']);

        // Merchant Warnings
        Route::get('/warnings', [MerchantWarningController::class, 'index']);

        // Notifications Management
        Route::prefix('notifications')->group(function () {
            Route::get('/', [MerchantController::class, 'getNotifications']);
            Route::post('/mark-all-read', [MerchantController::class, 'markAllNotificationsAsRead']);
        });
    });

    // Admin routes
    Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'users']);
            Route::get('/{id}', [AdminController::class, 'getUser']);
            Route::post('/', [AdminController::class, 'createUser']);
            Route::put('/{id}', [AdminController::class, 'updateUser']);
            Route::delete('/{id}', [AdminController::class, 'deleteUser']);
            Route::post('/{id}/block', [AdminController::class, 'blockUser']);
        });

        Route::prefix('merchants')->group(function () {
            Route::get('/', [AdminController::class, 'merchants']);
            Route::get('/select', [AdminController::class, 'getMerchantsForSelect']); // للاستخدام في dropdowns
            Route::get('/test', function() {
                try {
                    $count = \App\Models\Merchant::count();
                    return response()->json([
                        'message' => 'Test successful',
                        'merchants_count' => $count,
                        'timestamp' => now()->toIso8601String()
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Test failed',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }); // اختبار بسيط
            Route::get('/{id}', [AdminController::class, 'getMerchant']);
            Route::post('/', [AdminController::class, 'createMerchant']);
            Route::put('/{id}', [AdminController::class, 'updateMerchant']);
            Route::delete('/{id}', [AdminController::class, 'deleteMerchant']);
            Route::post('/{id}/approve', [AdminController::class, 'approveMerchant']);
            Route::post('/{id}/block', [AdminController::class, 'blockMerchant']);
        });

        Route::prefix('offers')->group(function () {
            Route::get('/', [AdminController::class, 'offers']);
            Route::post('/{offerId}/coupons', [AdminController::class, 'storeOfferCoupon']);
            Route::get('/{id}', [AdminController::class, 'getOffer']);
            Route::post('/', [AdminController::class, 'createOffer']);
            Route::put('/{id}', [AdminController::class, 'updateOffer']);
            Route::delete('/{id}', [AdminController::class, 'deleteOffer']);
            Route::post('/{id}/approve', [AdminController::class, 'approveOffer']);
        });

        // Banners Management
        Route::prefix('banners')->group(function () {
            Route::get('/', [AdminController::class, 'getBanners']);
            Route::post('/', [AdminController::class, 'createBanner']);
            Route::post('/{id}', [AdminController::class, 'updateBanner']); // Use POST for multipart updates
            Route::delete('/{id}', [AdminController::class, 'deleteBanner']);
        });

        // Coupons Management (Admin) - توحيد مع منطق التاجر
        Route::prefix('coupons')->group(function () {
            Route::get('/allCoupons', [AdminController::class, 'allCoupons']);
            Route::get('/stats', [AdminController::class, 'couponStats']);
            Route::get('/by-mall/{mallId}', [AdminController::class, 'getCouponsByMall']);
            Route::get('/by-category/{categoryId}', [AdminController::class, 'getCouponsByCategory']);
            Route::get('/available', [AdminController::class, 'getAvailableCoupons']); // For category + mall
            Route::get('/{id}', [AdminController::class, 'getCoupon']);
            Route::post('/', [AdminController::class, 'createCoupon']);
            Route::put('/{id}', [AdminController::class, 'updateCoupon']);
            Route::post('/{id}', [AdminController::class, 'updateCoupon']); // FormData with _method=PUT
            Route::delete('/{id}', [AdminController::class, 'deleteCoupon']);
            Route::post('/{id}/activate', [AdminController::class, 'activateCoupon']);
            Route::post('/{id}/deactivate', [AdminController::class, 'deactivateCoupon']);
        });

        // Mall Coupons Management (Admin) - منقول من التاجر
        Route::get('/mall-coupons', [AdminController::class, 'getMallCoupons']);

        // Coupon Activations (Admin) - منقول من التاجر
        Route::get('/coupon-activations', [AdminController::class, 'getCouponActivations']);

        Route::prefix('reports')->group(function () {
            Route::get('/sales', [AdminController::class, 'salesReport']);
            Route::get('/sales/export', [AdminController::class, 'exportSalesReport']);
        });

        Route::get('/financial/dashboard', [AdminController::class, 'financialDashboard']);

        Route::get('/settings', [AdminController::class, 'getSettings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);
        Route::post('/settings/logo', [AdminController::class, 'uploadLogo']);
        Route::put('/categories/order', [AdminController::class, 'updateCategoryOrder']);

        // Categories Management - Full CRUD
        Route::prefix('categories')->group(function () {
            Route::get('/', [AdminController::class, 'getCategories']);
            Route::get('/{id}', [AdminController::class, 'getCategory']);
            Route::post('/', [AdminController::class, 'createCategory']);
            Route::put('/{id}', [AdminController::class, 'updateCategory']);
            Route::delete('/{id}', [AdminController::class, 'deleteCategory']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/users', [ReportController::class, 'usersReport']);
            Route::get('/merchants', [ReportController::class, 'merchantsReport']);
            Route::get('/orders', [ReportController::class, 'ordersReport']);
            Route::get('/products', [ReportController::class, 'productsReport']);
            Route::get('/payments', [ReportController::class, 'paymentsReport']);
            Route::get('/financial', [ReportController::class, 'financialReport']);
            Route::get('/activations', [ReportController::class, 'activationsReport']);
            Route::get('/gps-engagement', [ReportController::class, 'gpsEngagementReport']);
            Route::get('/conversion-funnel', [ReportController::class, 'conversionFunnelReport']);
            Route::get('/failed-payments', [ReportController::class, 'failedPaymentsReport']);
            Route::get('/export/{type}/pdf', [ReportController::class, 'exportPdf']);
            Route::get('/export/{type}/excel', [ReportController::class, 'exportExcel']);
        });

        // Permissions & Roles
        Route::prefix('permissions')->group(function () {
            Route::get('/', [PermissionController::class, 'index']);
            Route::post('/', [PermissionController::class, 'create']);
            Route::put('/{id}', [PermissionController::class, 'update']);
            Route::delete('/{id}', [PermissionController::class, 'delete']);
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [PermissionController::class, 'roles']);
            Route::post('/', [PermissionController::class, 'createRole']);
            Route::put('/{id}', [PermissionController::class, 'updateRole']);
            Route::post('/{id}/permissions', [PermissionController::class, 'assignPermissions']);
            Route::delete('/{id}', [PermissionController::class, 'deleteRole']);
        });

        // Withdrawals management
        Route::prefix('withdrawals')->group(function () {
            Route::get('/', [AdminController::class, 'withdrawals']);
            Route::post('/{id}/approve', [AdminController::class, 'approveWithdrawal']);
            Route::post('/{id}/reject', [AdminController::class, 'rejectWithdrawal']);
            Route::post('/{id}/complete', [AdminController::class, 'completeWithdrawal']);
        });


        // Support Tickets Management
        Route::prefix('support')->group(function () {
            Route::get('/tickets', [SupportTicketController::class, 'index']);
            Route::post('/tickets/{id}/assign', [SupportTicketController::class, 'assign']);
            Route::post('/tickets/{id}/resolve', [SupportTicketController::class, 'resolve']);
        });


        // Activity Logs
        Route::get('/activity-logs', [AdminController::class, 'activityLogs']);

        // Payment Gateways
        Route::prefix('payment-gateways')->group(function () {
            Route::get('/', [AdminController::class, 'paymentGateways']);
            Route::post('/', [AdminController::class, 'createPaymentGateway']);
            Route::put('/{id}', [AdminController::class, 'updatePaymentGateway']);
        });

        // Tax Settings
        Route::prefix('tax')->group(function () {
            Route::get('/', [AdminController::class, 'taxSettings']);
            Route::put('/', [AdminController::class, 'updateTaxSettings']);
        });

        // Generate Invoices
        Route::post('/invoices/generate', [InvoiceController::class, 'generateMonthly']);

        // Activation Reports
        Route::get('/activation-reports', [AdminController::class, 'activationReports']);

        // Admin Wallet
        Route::prefix('wallet')->group(function () {
            Route::get('/', [AdminWalletController::class, 'index']);
            Route::get('/transactions', [AdminWalletController::class, 'transactions']);
            Route::post('/adjust', [AdminWalletController::class, 'adjust']);
            Route::get('/merchants/{id}', [AdminWalletController::class, 'getMerchantWallet']);
        });

        // Withdrawals Management
        Route::prefix('withdrawals')->group(function () {
            Route::get('/', [AdminController::class, 'withdrawals']);
            Route::post('/{id}/approve', [AdminController::class, 'approveWithdrawal']);
            Route::post('/{id}/reject', [AdminController::class, 'rejectWithdrawal']);
            Route::post('/{id}/complete', [AdminController::class, 'completeWithdrawal']);
        });

        // Merchant Management
        Route::prefix('merchants')->group(function () {
            Route::post('/{id}/suspend', [AdminController::class, 'suspendMerchant']);
            Route::get('/{id}/wallet', [AdminController::class, 'getMerchantWallet']);
        });

        // Merchant Verification
        Route::prefix('verifications')->group(function () {
            Route::get('/', [MerchantVerificationController::class, 'index']);
            Route::post('/{merchantId}/review', [MerchantVerificationController::class, 'review']);
            Route::get('/{merchantId}/documents/{type}', [MerchantVerificationController::class, 'downloadDocument']);
        });

        // Merchant Warnings (admin list = getMerchantWarnings)
        Route::prefix('warnings')->group(function () {
            Route::get('/', [AdminController::class, 'getMerchantWarnings']);
            Route::get('/merchants', [AdminController::class, 'getMerchantWarnings']);
            Route::get('/users', [AdminController::class, 'getUserWarnings']);
            Route::post('/merchants/{id}', [MerchantWarningController::class, 'issue']);
            Route::post('/users/{id}', [AdminController::class, 'issueUserWarning']);
            Route::post('/{id}/deactivate', [MerchantWarningController::class, 'deactivate']);
            Route::post('/users/{id}/deactivate', [AdminController::class, 'deactivateUserWarning']);
        });

        // Review Moderation
        Route::prefix('reviews')->group(function () {
            Route::get('/', [ReviewModerationController::class, 'index']);
            Route::post('/{id}/moderate', [ReviewModerationController::class, 'moderate']);
            Route::post('/{id}/restore', [ReviewModerationController::class, 'restore']);
        });

        // Invoices Management
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('/{id}', [InvoiceController::class, 'show']);
            Route::post('/orders/{orderId}/generate', [InvoiceController::class, 'generateOrderInvoice']);
            Route::post('/{id}/reissue', [InvoiceController::class, 'reissue']);
            Route::post('/{id}/cancel', [InvoiceController::class, 'cancel']);
        });

        // Regulatory Checks
        Route::prefix('regulatory-checks')->group(function () {
            Route::get('/', [RegulatoryCheckController::class, 'index']);
            Route::post('/', [RegulatoryCheckController::class, 'store']);
            Route::get('/{id}', [RegulatoryCheckController::class, 'show']);
            Route::put('/{id}', [RegulatoryCheckController::class, 'update']);
            Route::delete('/{id}', [RegulatoryCheckController::class, 'destroy']);
            Route::get('/merchants/{merchantId}', [RegulatoryCheckController::class, 'getMerchantChecks']);
            Route::post('/merchants/{merchantId}/automated', [RegulatoryCheckController::class, 'runAutomatedCheck']);
        });

        // Financial Reports Cache
        Route::prefix('reports-cache')->group(function () {
            Route::get('/', [FinancialReportsCacheController::class, 'index']);
            Route::get('/{id}', [FinancialReportsCacheController::class, 'show']);
            Route::post('/generate', [FinancialReportsCacheController::class, 'generate']);
            Route::get('/{id}/download', [FinancialReportsCacheController::class, 'download']);
            Route::delete('/{id}', [FinancialReportsCacheController::class, 'destroy']);
            Route::post('/clear-expired', [FinancialReportsCacheController::class, 'clearExpired']);
            Route::get('/statistics', [FinancialReportsCacheController::class, 'statistics']);
        });

        // Orders Management
        Route::prefix('orders')->group(function () {
            Route::get('/', [AdminController::class, 'getOrders']);
            Route::get('/{id}', [AdminController::class, 'getOrder']);
            Route::post('/', [AdminController::class, 'createOrder']);
            Route::put('/{id}', [AdminController::class, 'updateOrder']);
            Route::delete('/{id}', [AdminController::class, 'deleteOrder']);
            Route::post('/{id}/cancel', [AdminController::class, 'cancelOrder']);
            Route::post('/{id}/refund', [AdminController::class, 'refundOrder']);
        });

        // Payments Management
        Route::prefix('payments')->group(function () {
            Route::get('/', [AdminController::class, 'getPayments']);
            Route::get('/{id}', [AdminController::class, 'getPayment']);
            Route::post('/', [AdminController::class, 'createPayment']);
            Route::put('/{id}', [AdminController::class, 'updatePayment']);
            Route::delete('/{id}', [AdminController::class, 'deletePayment']);
            Route::post('/{id}/refund', [AdminController::class, 'refundPayment']);
        });

        // Transactions Management
        Route::prefix('transactions')->group(function () {
            Route::get('/', [AdminController::class, 'getTransactions']);
            Route::get('/{id}', [AdminController::class, 'getTransaction']);
            Route::post('/', [AdminController::class, 'createTransaction']);
            Route::put('/{id}', [AdminController::class, 'updateTransaction']);
            Route::delete('/{id}', [AdminController::class, 'deleteTransaction']);
        });

        // Locations Management
        Route::prefix('locations')->group(function () {
            Route::get('/', [AdminController::class, 'getLocations']);
            Route::get('/{id}', [AdminController::class, 'getLocation']);
            Route::post('/', [AdminController::class, 'createLocation']);
            Route::put('/{id}', [AdminController::class, 'updateLocation']);
            Route::delete('/{id}', [AdminController::class, 'deleteLocation']);
        });


        // Staff Management
        Route::prefix('staff')->group(function () {
            Route::get('/', [AdminController::class, 'getStaff']);
            Route::get('/{id}', [AdminController::class, 'getStaffMember']);
            Route::post('/', [AdminController::class, 'createStaff']);
            Route::put('/{id}', [AdminController::class, 'updateStaff']);
            Route::delete('/{id}', [AdminController::class, 'deleteStaff']);
        });

        // Notifications Management
        Route::prefix('notifications')->group(function () {
            Route::get('/', [AdminController::class, 'getNotifications']);
            Route::get('/{id}', [AdminController::class, 'getNotification']);
            Route::post('/', [AdminController::class, 'createNotification']);
            Route::put('/{id}', [AdminController::class, 'updateNotification']);
            Route::delete('/{id}', [AdminController::class, 'deleteNotification']);
            Route::post('/{id}/send', [AdminController::class, 'sendNotification']);
            Route::post('/{id}/read', [AdminController::class, 'markNotificationAsRead']);
            Route::post('/mark-all-read', [AdminController::class, 'markAllNotificationsAsRead']);
        });

        // Malls Management
        Route::prefix('malls')->group(function () {
            Route::get('/', [AdminController::class, 'getMalls']);
            Route::get('/{id}', [AdminController::class, 'getMall']);
            Route::post('/', [AdminController::class, 'createMall']);
            Route::put('/{id}', [AdminController::class, 'updateMall']);
            Route::delete('/{id}', [AdminController::class, 'deleteMall']);
        });

        // Ads Management
        Route::prefix('ads')->group(function () {
            Route::get('/', [AdminController::class, 'getAds']);
            Route::get('/{id}', [AdminController::class, 'getAd']);
            Route::post('/', [AdminController::class, 'createAd']);
            Route::put('/{id}', [AdminController::class, 'updateAd']);
            Route::delete('/{id}', [AdminController::class, 'deleteAd']);
        });

        // Activity Logs Additional Routes
        Route::prefix('activity-logs')->group(function () {
            Route::get('/{id}', [AdminController::class, 'getActivityLog']);
            Route::delete('/{id}', [AdminController::class, 'deleteActivityLog']);
            Route::delete('/', [AdminController::class, 'clearActivityLogs']);
        });

        // Payment Gateways Additional Routes
        Route::prefix('payment-gateways')->group(function () {
            Route::get('/{id}', [AdminController::class, 'getPaymentGateway']);
            Route::delete('/{id}', [AdminController::class, 'deletePaymentGateway']);
        });

        // Tax Settings Additional Routes
        Route::prefix('tax')->group(function () {
            Route::post('/', [AdminController::class, 'createTaxSetting']);
            Route::delete('/{id}', [AdminController::class, 'deleteTaxSetting']);
        });


        // Invoices Additional Routes
        Route::prefix('invoices')->group(function () {
            Route::put('/{id}', [AdminController::class, 'updateInvoice']);
            Route::delete('/{id}', [AdminController::class, 'deleteInvoice']);
        });
    });
});
