<?php

use App\Http\Controllers\Api\Admin\ActivityLogController as AdminPanelActivityLogController;
use App\Http\Controllers\Api\Admin\AdController as AdminPanelAdController;
use App\Http\Controllers\Api\Admin\AdminNotificationController as AdminPanelNotificationController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminPanelCategoryController;
use App\Http\Controllers\Api\Admin\CouponController as AdminPanelCouponController;
use App\Http\Controllers\Api\Admin\FinancialTransactionController as AdminPanelFinancialTransactionController;
use App\Http\Controllers\Api\Admin\LocationController as AdminPanelLocationController;
use App\Http\Controllers\Api\Admin\MallController as AdminPanelMallController;
use App\Http\Controllers\Api\Admin\MerchantController as AdminPanelMerchantController;
use App\Http\Controllers\Api\Admin\MerchantInvoiceController as AdminPanelMerchantInvoiceController;
use App\Http\Controllers\Api\Admin\OfferController as AdminPanelOfferController;
use App\Http\Controllers\Api\Admin\OrderController as AdminPanelOrderController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPanelPaymentController;
use App\Http\Controllers\Api\Admin\PaymentGatewayController as AdminPanelPaymentGatewayController;
use App\Http\Controllers\Api\Admin\ReportController as AdminPanelReportController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminPanelSettingsController;
use App\Http\Controllers\Api\Admin\StaffController as AdminPanelStaffController;
use App\Http\Controllers\Api\Admin\TaxSettingController as AdminPanelTaxSettingController;
use App\Http\Controllers\Api\Admin\UserController as AdminPanelUserController;
use App\Http\Controllers\Api\Admin\WarningController as AdminPanelWarningController;
use App\Http\Controllers\Api\Admin\WithdrawalController as AdminPanelWithdrawalController;
use App\Http\Controllers\Api\AdminAppPolicyController;
use App\Http\Controllers\Api\AdminWalletController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CouponEntitlementController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\Api\FinancialController;
use App\Http\Controllers\Api\FinancialReportsCacheController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\MallPublicController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\MerchantProfileController;
use App\Http\Controllers\Api\MerchantStaffController;
use App\Http\Controllers\Api\MerchantVerificationController;
use App\Http\Controllers\Api\MerchantWarningController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\QrActivationController;
use App\Http\Controllers\Api\RegulatoryCheckController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewModerationController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletManagementController;
use App\Http\Controllers\Api\WalletTransactionController;
use App\Models\AdminNotification;
use App\Models\Merchant;
use App\Models\Setting;
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

// API Documentation
Route::get('/docs', [DocumentationController::class, 'apiDocs']);
Route::get('/docs/postman', [DocumentationController::class, 'postmanCollection']);
Route::get('/docs/openapi.yaml', [DocumentationController::class, 'openapiYaml']);

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/refresh', [AuthController::class, 'refreshToken'])->middleware('throttle:30,1');
    Route::post('/register-merchant', [AuthController::class, 'registerMerchant'])->middleware('throttle:3,1');
    Route::post('/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:3,1');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
});

// Public categories
Route::get('/categories/filter-options', [CategoryController::class, 'filterOptions']);
Route::get('/merchant-categories/options', [CategoryController::class, 'filterOptions']);
Route::get('/offer-categories/options', [CategoryController::class, 'filterOptions']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show'])->whereNumber('id');

// Public malls — تفاصيل مول: ?category_id= أو ?merchant_category_id= و ?offer_category_id=
Route::get('/malls/details/{id}', [MallPublicController::class, 'mobileMallDetails'])->whereNumber('id');
Route::get('/malls/{mallId}/merchants/all', [MallPublicController::class, 'merchantsAll'])->where('mallId', '[0-9]+');
Route::get('/malls/{mallId}/merchants', [MallPublicController::class, 'merchants'])->where('mallId', '[0-9]+');
Route::get('/malls', [MallPublicController::class, 'index']);

// Public offers
Route::get('/offers', [OfferController::class, 'index']);
Route::get('/offers/{offer}', [OfferController::class, 'show']);
Route::get('/offers/{offer}/coupons', [CouponController::class, 'index']);

// Mobile path aliases (some deployments may not register routes/mobile.php under /api/mobile)
Route::get('/mobile/offers', [OfferController::class, 'index']);
Route::get('/mobile/offers/{offer}', [OfferController::class, 'show'])->whereNumber('offer');
Route::get('/mobile/offers/{offer}/coupons', [CouponController::class, 'index'])->whereNumber('offer');

// للتاجر: لليوزر يشوف بيانات التجار وعروضهم (بدون مصادقة)
Route::get('/merchants', [MerchantProfileController::class, 'index']);
Route::get('/merchants/{id}/offers', [MerchantProfileController::class, 'offers']);
Route::get('/merchants/{id}', [MerchantProfileController::class, 'show'])->where('id', '[0-9]+');

// Static app pages (admin-managed under Settings → Static pages)
Route::get('/content/static-pages', function () {
    return response()->json([
        'data' => [
            'complaints_suggestions' => [
                'ar' => Setting::getValue('static_complaints_ar', ''),
                'en' => Setting::getValue('static_complaints_en', ''),
            ],
            'privacy' => [
                'ar' => Setting::getValue('static_privacy_ar', ''),
                'en' => Setting::getValue('static_privacy_en', ''),
            ],
            'support' => [
                'ar' => Setting::getValue('static_support_ar', ''),
                'en' => Setting::getValue('static_support_en', ''),
            ],
            'about' => [
                'ar' => Setting::getValue('static_about_ar', ''),
                'en' => Setting::getValue('static_about_en', ''),
            ],
        ],
    ]);
});

// Test route without middleware
Route::get('/test-notifications', function () {
    $notifications = AdminNotification::with('creator')->take(5)->get();

    return response()->json([
        'message' => 'Test successful',
        'count' => $notifications->count(),
        'data' => $notifications,
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
                'is_admin' => $user->isAdmin(),
            ] : null,
        ]);
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ], 500);
    }
})->middleware(['auth:sanctum', 'access.token']);

// Test admin notifications directly
Route::get('/test-admin-notifications', function () {
    try {
        $notifications = AdminNotification::with('creator')->take(5)->get();

        return response()->json([
            'message' => 'Admin notifications test',
            'count' => $notifications->count(),
            'data' => $notifications,
        ]);
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ], 500);
    }
});

// Test mark notification as read
Route::post('/test-mark-read/{id}', function ($id) {
    try {
        $notification = AdminNotification::findOrFail($id);
        $notification->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $notification->fresh(),
        ]);
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ], 500);
    }
});

// =============================================================================
// Authenticated routes only - كل المسارات التالية تتطلب تسجيل الدخول + Token
// (Authorization: Bearer <token>). السلة والمفضلة والطلبات مرتبطة بالمستخدم المسجل.
// =============================================================================
Route::middleware(['auth:sanctum', 'access.token'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Offers (create/update/delete/favorite require auth)
    Route::prefix('offers')->group(function () {
        Route::get('/', [OfferController::class, 'index']);
        Route::post('/', [OfferController::class, 'store']);
        Route::get('/{offer}', [OfferController::class, 'show']);
        Route::put('/{offer}', [OfferController::class, 'update']);
        Route::delete('/{offer}', [OfferController::class, 'destroy']);
        Route::post('/{offer}/favorite', [OfferController::class, 'toggleFavorite']); // requires auth
        Route::post('/{offer}/status', [OfferController::class, 'toggleStatus']);

        Route::prefix('{offer}/coupons')->group(function () {
            Route::get('/', [CouponController::class, 'index']);
            Route::post('/', [CouponController::class, 'store']);
            Route::put('/{coupon}', [CouponController::class, 'update']);
            Route::delete('/{coupon}', [CouponController::class, 'destroy']);
        });
    });

    // Cart - يتطلب auth + token (لا يمكن الوصول بدون تسجيل الدخول)
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
        Route::post('/checkout', [OrderController::class, 'checkout'])->middleware('throttle:5,1');
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
    });

    // Wallet & Coupons - يتطلب مصادقة
    Route::prefix('wallet')->middleware('auth:sanctum')->group(function () {
        Route::get('/coupons', [OrderController::class, 'walletCoupons']);
        Route::get('/coupons/{id}', [OrderController::class, 'walletCouponShow'])->whereNumber('id');
        Route::post('/entitlements/{entitlementId}/share', [CouponEntitlementController::class, 'share'])
            ->whereNumber('entitlementId');
    });

    // Reviews - يتطلب مصادقة
    Route::post('/reviews', [OrderController::class, 'createReview']);
    Route::post('/offers/{offer}/reviews', [OrderController::class, 'createOfferReview'])
        ->whereNumber('offer');

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
    Route::get('/search', [OfferController::class, 'search'])->middleware('throttle:30,1');

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
            Route::post('/', [MerchantController::class, 'createOffer'])->middleware('throttle:20,1');
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
        Route::get('/me/activations', [MerchantController::class, 'myActivationHistory']);

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
            Route::post('/{id}/deactivate', [MerchantController::class, 'deactivateCoupon']); // منقول من الأدمن
        });

        Route::post('/entitlements/{id}/activate', [MerchantController::class, 'activateCoupon']);

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
            Route::get('/{id}/activations', [MerchantStaffController::class, 'activations']);
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
            Route::post('/{id}/read', [MerchantController::class, 'markNotificationAsRead']);
            Route::delete('/{id}', [MerchantController::class, 'deleteMerchantNotification']);
        });
    });

    // Admin routes
    Route::prefix('admin')->middleware(['auth:sanctum', 'access.token', 'admin'])->group(function () {
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminPanelUserController::class, 'users']);
            Route::get('/{id}', [AdminPanelUserController::class, 'getUser']);
            Route::post('/', [AdminPanelUserController::class, 'createUser']);
            Route::put('/{id}', [AdminPanelUserController::class, 'updateUser']);
            Route::delete('/{id}', [AdminPanelUserController::class, 'deleteUser']);
            Route::post('/{id}/block', [AdminPanelUserController::class, 'blockUser']);
        });

        Route::prefix('merchants')->group(function () {
            Route::get('/', [AdminPanelMerchantController::class, 'merchants']);
            Route::get('/select', [AdminPanelMerchantController::class, 'getMerchantsForSelect']); // للاستخدام في dropdowns
            Route::get('/test', function () {
                try {
                    $count = Merchant::count();

                    return response()->json([
                        'message' => 'Test successful',
                        'merchants_count' => $count,
                        'timestamp' => now()->toIso8601String(),
                    ]);
                } catch (Exception $e) {
                    return response()->json([
                        'message' => 'Test failed',
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }); // اختبار بسيط
            Route::put('/{id}/commission', [AdminPanelMerchantController::class, 'updateMerchantCommission']);
            Route::get('/{id}', [AdminPanelMerchantController::class, 'getMerchant']);
            Route::post('/', [AdminPanelMerchantController::class, 'createMerchant']);
            Route::put('/{id}', [AdminPanelMerchantController::class, 'updateMerchant']);
            Route::delete('/{id}', [AdminPanelMerchantController::class, 'deleteMerchant']);
            Route::post('/{id}/approve', [AdminPanelMerchantController::class, 'approveMerchant']);
            Route::post('/{id}/block', [AdminPanelMerchantController::class, 'blockMerchant']);
        });

        Route::prefix('offers')->group(function () {
            Route::get('/', [AdminPanelOfferController::class, 'offers']);
            Route::post('/{offerId}/coupons', [AdminPanelOfferController::class, 'storeOfferCoupon']);
            Route::get('/{id}', [AdminPanelOfferController::class, 'getOffer']);
            Route::post('/', [AdminPanelOfferController::class, 'createOffer'])->middleware('throttle:20,1');
            Route::put('/{id}', [AdminPanelOfferController::class, 'updateOffer']);
            Route::delete('/{id}', [AdminPanelOfferController::class, 'deleteOffer']);
            Route::post('/{id}/approve', [AdminPanelOfferController::class, 'approveOffer']);
        });

        // Banners Management
        Route::prefix('banners')->group(function () {
            Route::get('/', [AdminPanelAdController::class, 'getBanners']);
            Route::post('/', [AdminPanelAdController::class, 'createBanner']);
            Route::post('/{id}', [AdminPanelAdController::class, 'updateBanner']); // Use POST for multipart updates
            Route::delete('/{id}', [AdminPanelAdController::class, 'deleteBanner']);
        });

        // Coupons Management (Admin) - توحيد مع منطق التاجر
        Route::prefix('coupons')->group(function () {
            Route::get('/allCoupons', [AdminPanelCouponController::class, 'allCoupons']);
            Route::get('/stats', [AdminPanelCouponController::class, 'couponStats']);
            Route::get('/by-mall/{mallId}', [AdminPanelCouponController::class, 'getCouponsByMall']);
            Route::get('/by-category/{categoryId}', [AdminPanelCouponController::class, 'getCouponsByCategory']);
            Route::get('/available', [AdminPanelCouponController::class, 'getAvailableCoupons']); // For category + mall
            Route::get('/{id}', [AdminPanelCouponController::class, 'getCoupon']);
            Route::post('/', [AdminPanelCouponController::class, 'createCoupon']);
            Route::put('/{id}', [AdminPanelCouponController::class, 'updateCoupon']);
            Route::post('/{id}', [AdminPanelCouponController::class, 'updateCoupon']); // FormData with _method=PUT
            Route::delete('/{id}', [AdminPanelCouponController::class, 'deleteCoupon']);
            Route::post('/{id}/activate', [AdminPanelCouponController::class, 'activateCoupon']);
            Route::post('/{id}/deactivate', [AdminPanelCouponController::class, 'deactivateCoupon']);
        });

        // Mall Coupons Management (Admin) - منقول من التاجر
        Route::get('/mall-coupons', [AdminPanelCouponController::class, 'getMallCoupons']);

        // Coupon Activations (Admin) - منقول من التاجر
        Route::get('/coupon-activations', [AdminPanelCouponController::class, 'getCouponActivations']);

        // Dashboard Stats (unified endpoint)
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
        Route::post('/dashboard/refresh', [DashboardController::class, 'refresh']);

        // Wallet Management
        Route::prefix('wallet')->group(function () {
            Route::get('/', [WalletManagementController::class, 'index']);
            Route::get('/transactions', [WalletManagementController::class, 'transactions']);
            Route::post('/adjust', [WalletManagementController::class, 'adjust']);
            Route::get('/merchants', [WalletManagementController::class, 'allMerchantWallets']);
            Route::get('/merchants/{merchantId}', [WalletManagementController::class, 'merchantWallet']);
            Route::post('/merchants/{merchantId}/freeze', [WalletManagementController::class, 'freezeMerchant']);
            Route::post('/merchants/{merchantId}/unfreeze', [WalletManagementController::class, 'unfreezeMerchant']);
            Route::get('/settings', [WalletManagementController::class, 'settings']);
            Route::put('/settings', [WalletManagementController::class, 'updateSettings']);
        });

        // Commission Management
        Route::prefix('commissions')->group(function () {
            Route::get('/', [CommissionController::class, 'index']);
            Route::get('/by-merchant', [CommissionController::class, 'byMerchant']);
            Route::get('/summary', [CommissionController::class, 'summary']);
            Route::get('/export', [CommissionController::class, 'export']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('/sales', [AdminPanelReportController::class, 'salesReport']);
            Route::get('/sales/export', [AdminPanelReportController::class, 'exportSalesReport']);
        });

        Route::get('/financial/dashboard', [AdminPanelReportController::class, 'financialDashboard']);

        Route::get('/settings', [AdminPanelSettingsController::class, 'getSettings']);
        Route::put('/settings', [AdminPanelSettingsController::class, 'updateSettings']);
        Route::post('/settings/logo', [AdminPanelSettingsController::class, 'uploadLogo']);
        Route::put('/categories/order', [AdminPanelCategoryController::class, 'updateCategoryOrder']);

        // Static CMS sections used by the mobile app:
        //   - Privacy policy → /api/mobile/app/policy
        //   - About app     → /api/mobile/app/about
        //   - Support       → /api/mobile/support (and /api/mobile/app/about)
        //
        // Two prefixes are exposed:
        //   /api/admin/app-policies (legacy, keeps old integrations working)
        //   /api/admin/app-sections (preferred, generic over the `type` column)
        foreach (['app-policies', 'app-sections'] as $adminSectionsPrefix) {
            Route::prefix($adminSectionsPrefix)->group(function () {
                Route::get('/', [AdminAppPolicyController::class, 'index']);
                Route::post('/', [AdminAppPolicyController::class, 'store']);
                Route::put('/order', [AdminAppPolicyController::class, 'reorder']);
                Route::get('/{id}', [AdminAppPolicyController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [AdminAppPolicyController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [AdminAppPolicyController::class, 'destroy'])->whereNumber('id');
            });
        }

        // Categories Management - Full CRUD
        Route::prefix('categories')->group(function () {
            Route::get('/', [AdminPanelCategoryController::class, 'getCategories']);
            Route::get('/{id}', [AdminPanelCategoryController::class, 'getCategory']);
            Route::post('/', [AdminPanelCategoryController::class, 'createCategory']);
            Route::put('/{id}', [AdminPanelCategoryController::class, 'updateCategory']);
            Route::delete('/{id}', [AdminPanelCategoryController::class, 'deleteCategory']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/entity-insight', [ReportController::class, 'entityInsightReport']);
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
            Route::get('/geo-distribution', [ReportController::class, 'geoDistribution']);
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
            Route::get('/', [AdminPanelWithdrawalController::class, 'withdrawals']);
            Route::post('/{id}/approve', [AdminPanelWithdrawalController::class, 'approveWithdrawal']);
            Route::post('/{id}/reject', [AdminPanelWithdrawalController::class, 'rejectWithdrawal']);
            Route::post('/{id}/complete', [AdminPanelWithdrawalController::class, 'completeWithdrawal']);
        });

        // Support Tickets Management
        Route::prefix('support')->group(function () {
            Route::get('/tickets', [SupportTicketController::class, 'index']);
            Route::post('/tickets/{id}/assign', [SupportTicketController::class, 'assign']);
            Route::post('/tickets/{id}/resolve', [SupportTicketController::class, 'resolve']);
        });

        // Activity Logs
        Route::get('/activity-logs', [AdminPanelActivityLogController::class, 'activityLogs']);

        // Payment Gateways
        Route::prefix('payment-gateways')->group(function () {
            Route::get('/', [AdminPanelPaymentGatewayController::class, 'paymentGateways']);
            Route::post('/', [AdminPanelPaymentGatewayController::class, 'createPaymentGateway']);
            Route::put('/{id}', [AdminPanelPaymentGatewayController::class, 'updatePaymentGateway']);
        });

        // Tax Settings
        Route::prefix('tax')->group(function () {
            Route::get('/', [AdminPanelTaxSettingController::class, 'taxSettings']);
            Route::put('/', [AdminPanelTaxSettingController::class, 'updateTaxSettings']);
        });

        // Generate Invoices
        Route::post('/invoices/generate', [InvoiceController::class, 'generateMonthly']);

        // Activation Reports
        Route::get('/activation-reports', [AdminPanelReportController::class, 'activationReports']);

        // Admin Wallet
        Route::prefix('wallet')->group(function () {
            Route::get('/', [AdminWalletController::class, 'index']);
            Route::get('/transactions', [AdminWalletController::class, 'transactions']);
            Route::post('/adjust', [AdminWalletController::class, 'adjust']);
            Route::get('/merchants/{id}', [AdminWalletController::class, 'getMerchantWallet']);
        });

        // Merchant Management
        Route::prefix('merchants')->group(function () {
            Route::post('/{id}/suspend', [AdminPanelMerchantController::class, 'suspendMerchant']);
            Route::get('/{id}/wallet', [AdminPanelMerchantController::class, 'getMerchantWallet']);
        });

        // Merchant Verification
        Route::prefix('verifications')->group(function () {
            Route::get('/', [MerchantVerificationController::class, 'index']);
            Route::post('/{merchantId}/review', [MerchantVerificationController::class, 'review']);
            Route::get('/{merchantId}/documents/{type}', [MerchantVerificationController::class, 'downloadDocument']);
        });

        // Merchant Warnings (admin list = getMerchantWarnings)
        Route::prefix('warnings')->group(function () {
            Route::get('/', [AdminPanelWarningController::class, 'getMerchantWarnings']);
            Route::get('/merchants', [AdminPanelWarningController::class, 'getMerchantWarnings']);
            Route::get('/users', [AdminPanelWarningController::class, 'getUserWarnings']);
            Route::post('/merchants/{id}', [MerchantWarningController::class, 'issue']);
            Route::post('/users/{id}', [AdminPanelWarningController::class, 'issueUserWarning']);
            Route::post('/{id}/deactivate', [MerchantWarningController::class, 'deactivate']);
            Route::post('/users/{id}/deactivate', [AdminPanelWarningController::class, 'deactivateUserWarning']);
        });

        // Review Moderation
        Route::prefix('reviews')->group(function () {
            Route::get('/', [ReviewModerationController::class, 'index']);
            Route::put('/{id}', [ReviewModerationController::class, 'update']);
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
            Route::get('/', [AdminPanelOrderController::class, 'getOrders']);
            Route::get('/{id}', [AdminPanelOrderController::class, 'getOrder']);
            Route::post('/', [AdminPanelOrderController::class, 'createOrder']);
            Route::put('/{id}', [AdminPanelOrderController::class, 'updateOrder']);
            Route::delete('/{id}', [AdminPanelOrderController::class, 'deleteOrder']);
            Route::post('/{id}/cancel', [AdminPanelOrderController::class, 'cancelOrder']);
            Route::post('/{id}/refund', [AdminPanelOrderController::class, 'refundOrder']);
        });

        // Payments Management
        Route::prefix('payments')->group(function () {
            Route::get('/', [AdminPanelPaymentController::class, 'getPayments']);
            Route::get('/{id}', [AdminPanelPaymentController::class, 'getPayment']);
            Route::post('/', [AdminPanelPaymentController::class, 'createPayment']);
            Route::put('/{id}', [AdminPanelPaymentController::class, 'updatePayment']);
            Route::delete('/{id}', [AdminPanelPaymentController::class, 'deletePayment']);
            Route::post('/{id}/refund', [AdminPanelPaymentController::class, 'refundPayment']);
        });

        // Transactions Management
        Route::prefix('transactions')->group(function () {
            Route::get('/', [AdminPanelFinancialTransactionController::class, 'getTransactions']);
            Route::get('/{id}', [AdminPanelFinancialTransactionController::class, 'getTransaction']);
            Route::post('/', [AdminPanelFinancialTransactionController::class, 'createTransaction']);
            Route::put('/{id}', [AdminPanelFinancialTransactionController::class, 'updateTransaction']);
            Route::delete('/{id}', [AdminPanelFinancialTransactionController::class, 'deleteTransaction']);
        });

        // Locations Management
        Route::prefix('locations')->group(function () {
            Route::get('/', [AdminPanelLocationController::class, 'getLocations']);
            Route::get('/{id}', [AdminPanelLocationController::class, 'getLocation']);
            Route::post('/', [AdminPanelLocationController::class, 'createLocation']);
            Route::put('/{id}', [AdminPanelLocationController::class, 'updateLocation']);
            Route::delete('/{id}', [AdminPanelLocationController::class, 'deleteLocation']);
        });

        // Staff Management
        Route::prefix('staff')->group(function () {
            Route::get('/', [AdminPanelStaffController::class, 'getStaff']);
            Route::get('/{id}', [AdminPanelStaffController::class, 'getStaffMember']);
            Route::post('/', [AdminPanelStaffController::class, 'createStaff']);
            Route::put('/{id}', [AdminPanelStaffController::class, 'updateStaff']);
            Route::delete('/{id}', [AdminPanelStaffController::class, 'deleteStaff']);
        });

        // Notifications Management (static paths before /{id} so "mark-all-read" is not treated as an id)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [AdminPanelNotificationController::class, 'getNotifications']);
            Route::post('/mark-all-read', [AdminPanelNotificationController::class, 'markAllNotificationsAsRead']);
            Route::post('/', [AdminPanelNotificationController::class, 'createNotification']);
            Route::get('/{id}', [AdminPanelNotificationController::class, 'getNotification']);
            Route::put('/{id}', [AdminPanelNotificationController::class, 'updateNotification']);
            Route::delete('/{id}', [AdminPanelNotificationController::class, 'deleteNotification']);
            Route::post('/{id}/delete', [AdminPanelNotificationController::class, 'deleteNotification']);
            Route::post('/{id}/send', [AdminPanelNotificationController::class, 'sendNotification']);
            Route::post('/{id}/read', [AdminPanelNotificationController::class, 'markNotificationAsRead']);
        });

        // Malls Management
        Route::prefix('malls')->group(function () {
            Route::get('/', [AdminPanelMallController::class, 'getMalls']);
            Route::get('/{id}', [AdminPanelMallController::class, 'getMall']);
            Route::post('/', [AdminPanelMallController::class, 'createMall']);
            Route::put('/{id}', [AdminPanelMallController::class, 'updateMall']);
            Route::delete('/{id}', [AdminPanelMallController::class, 'deleteMall']);
        });

        // ─── Wallet Management ───────────────────────────────────────────────────────
        Route::prefix('wallet')->group(function () {
            // Admin wallet summary
            Route::get('/', [AdminWalletController::class, 'index']);
            // All wallet transactions (admin + merchant)
            Route::get('/transactions', [AdminWalletController::class, 'transactions']);
            // Adjust any wallet (credit / debit)
            Route::post('/adjust', [AdminWalletController::class, 'adjust']);
            // All merchant wallets list
            Route::get('/merchants', [AdminWalletController::class, 'getMerchantWallets']);
            // Single merchant wallet detail
            Route::get('/merchants/{merchantId}', [AdminWalletController::class, 'getMerchantWallet']);
            // Freeze / unfreeze merchant wallet
            Route::post('/merchants/{merchantId}/freeze', [AdminWalletController::class, 'freezeMerchantWallet']);
            Route::post('/merchants/{merchantId}/unfreeze', [AdminWalletController::class, 'unfreezeMerchantWallet']);
            // Wallet global settings
            Route::get('/settings', [AdminWalletController::class, 'getSettings']);
            Route::put('/settings', [AdminWalletController::class, 'updateSettings']);
        });

        // ─── Withdrawals Management ──────────────────────────────────────────────────
        Route::prefix('withdrawals')->group(function () {
            Route::get('/', [AdminWalletController::class, 'getWithdrawals']);
            Route::get('/{id}', [AdminWalletController::class, 'getWithdrawal']);
            Route::post('/{id}/approve', [AdminWalletController::class, 'approveWithdrawal']);
            Route::post('/{id}/reject', [AdminWalletController::class, 'rejectWithdrawal']);
        });

        // Ads Management
        Route::prefix('ads')->group(function () {
            Route::get('/report-stats', [AdminPanelAdController::class, 'getAdsReportStats']);
            Route::get('/', [AdminPanelAdController::class, 'getAds']);
            Route::get('/{id}', [AdminPanelAdController::class, 'getAd']);
            Route::post('/', [AdminPanelAdController::class, 'createAd']);
            Route::put('/{id}', [AdminPanelAdController::class, 'updateAd']);
            Route::delete('/{id}', [AdminPanelAdController::class, 'deleteAd']);
        });

        // Activity Logs Additional Routes
        Route::prefix('activity-logs')->group(function () {
            Route::get('/{id}', [AdminPanelActivityLogController::class, 'getActivityLog']);
            Route::delete('/{id}', [AdminPanelActivityLogController::class, 'deleteActivityLog']);
            Route::delete('/', [AdminPanelActivityLogController::class, 'clearActivityLogs']);
        });

        // Payment Gateways Additional Routes
        Route::prefix('payment-gateways')->group(function () {
            Route::get('/{id}', [AdminPanelPaymentGatewayController::class, 'getPaymentGateway']);
            Route::delete('/{id}', [AdminPanelPaymentGatewayController::class, 'deletePaymentGateway']);
        });

        // Tax Settings Additional Routes
        Route::prefix('tax')->group(function () {
            Route::post('/', [AdminPanelTaxSettingController::class, 'createTaxSetting']);
            Route::delete('/{id}', [AdminPanelTaxSettingController::class, 'deleteTaxSetting']);
        });

        // Invoices Additional Routes
        Route::prefix('invoices')->group(function () {
            Route::put('/{id}', [AdminPanelMerchantInvoiceController::class, 'updateInvoice']);
            Route::delete('/{id}', [AdminPanelMerchantInvoiceController::class, 'deleteInvoice']);
        });
    });
});
