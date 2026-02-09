<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FinancialController;
use App\Http\Controllers\Api\MerchantController;
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
| Mobile API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Mobile API routes for your application.
| These routes are loaded by the RouteServiceProvider under the "mobile" 
| middleware group and all have the "/api/mobile" prefix.
|
*/

// ================================
// 1. معلومات الـ Mobile API (عام)
// ================================
Route::get('/', function () {
    return response()->json([
        'name' => 'OFROO Mobile API',
        'version' => '1.0.0',
        'status' => 'active',
        'endpoints' => [
            'auth' => '/api/mobile/auth',
            'categories' => '/api/mobile/categories',
            'offers' => '/api/mobile/offers',
            'cart' => '/api/mobile/cart',
            'orders' => '/api/mobile/orders',
            'user' => '/api/mobile/user',
            'merchant' => '/api/mobile/merchant',
        ],
    ]);
});

// ================================
// 2. المصادقة (Authentication) - Public Routes
// ================================
Route::prefix('auth')->group(function () {
    // تسجيل مستخدم عادي
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    
    // تسجيل الدخول
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    
    // تسجيل تاجر جديد
    Route::post('/register-merchant', [AuthController::class, 'registerMerchant'])->middleware('throttle:3,1');
    
    // طلب OTP
    Route::post('/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:3,1');
    
    // التحقق من OTP
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
});

// ================================
// 3. التصنيفات (Categories) - Public Routes
// ================================
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

// ================================
// 4. العروض (Offers) - Public Routes
// ================================
Route::get('/offers', [OfferController::class, 'index']);
Route::get('/offers/{offer}', [OfferController::class, 'show']);
Route::get('/offers/{offer}/coupons', [CouponController::class, 'index']);

// ================================
// Protected Routes (تتطلب مصادقة)
// ================================
Route::middleware('auth:sanctum')->group(function () {
    
    // ================================
    // 2. المصادقة - Protected Routes
    // ================================
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    // ================================
    // 4. العروض - Protected Routes
    // ================================
    Route::get('/search', [OfferController::class, 'search']);
    Route::get('/offers/{id}/whatsapp', [OfferController::class, 'whatsappContact']);
    
    // ================================
    // 5. السلة (Cart)
    // ================================
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/add', [CartController::class, 'add']);
        Route::put('/{id}', [CartController::class, 'update']);
        Route::delete('/{id}', [CartController::class, 'remove']);
        Route::delete('/', [CartController::class, 'clear']);
    });
    
    // ================================
    // 6. الطلبات (Orders)
    // ================================
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::get('/{id}/coupons', [OrderController::class, 'getOrderCoupons']);
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
    });
    
    // ================================
    // 7. المحفظة والكوبونات (Wallet)
    // ================================
    Route::prefix('wallet')->group(function () {
        Route::get('/coupons', [OrderController::class, 'walletCoupons']);
    });
    
    // ================================
    // 8. التقييمات (Reviews)
    // ================================
    Route::post('/reviews', [OrderController::class, 'createReview']);
    
    // ================================
    // 9. الدعم الفني (Support)
    // ================================
    Route::prefix('support')->group(function () {
        Route::post('/tickets', [SupportTicketController::class, 'create']);
        Route::get('/tickets', [SupportTicketController::class, 'index']);
        Route::get('/tickets/{id}', [SupportTicketController::class, 'show']);
    });
    
    // ================================
    // 10. الولاء (Loyalty)
    // ================================
    Route::prefix('loyalty')->group(function () {
        Route::get('/account', [LoyaltyController::class, 'account']);
        Route::get('/transactions', [LoyaltyController::class, 'transactions']);
        Route::post('/redeem', [LoyaltyController::class, 'redeem']);
    });
    
    // ================================
    // 11. المستخدم (User Profile & Settings)
    // ================================
    Route::prefix('user')->group(function () {
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
    
    // ================================
    // 12. التاجر (Merchant) - تتطلب middleware 'merchant'
    // ================================
    Route::prefix('merchant')->middleware('merchant')->group(function () {
        
        // ===== العروض (Merchant Offers) - توحيد مع منطق الأدمن =====
        Route::prefix('offers')->group(function () {
            Route::get('/', [MerchantController::class, 'offers']);
            Route::get('/{id}', [MerchantController::class, 'getOffer']);
            Route::post('/', [MerchantController::class, 'createOffer']);
            Route::put('/{id}', [MerchantController::class, 'updateOffer']);
            Route::delete('/{id}', [MerchantController::class, 'deleteOffer']);
            
            // إضافة كوبونات للعرض (منقول من الأدمن)
            Route::post('/{offerId}/coupons', [MerchantController::class, 'storeOfferCoupon']);
        });
        
        // ===== الطلبات (Merchant Orders) =====
        Route::prefix('orders')->group(function () {
            Route::get('/', [MerchantController::class, 'orders']);
        });
        
        // ===== المواقع والفروع (Locations) =====
        Route::prefix('locations')->group(function () {
            Route::get('/', [MerchantController::class, 'storeLocations']);
            Route::get('/{id}', [MerchantController::class, 'getStoreLocation']);
            Route::post('/', [MerchantController::class, 'createStoreLocation']);
            Route::put('/{id}', [MerchantController::class, 'updateStoreLocation']);
            Route::delete('/{id}', [MerchantController::class, 'deleteStoreLocation']);
        });
        
        // ===== الإحصائيات =====
        Route::get('/statistics', [MerchantController::class, 'statistics']);
        
        // ===== Profile Management =====
        Route::get('/profile', [MerchantController::class, 'getProfile']);
        Route::put('/profile', [MerchantController::class, 'updateProfile']);
        Route::post('/profile/logo', [MerchantController::class, 'uploadLogo']);
        
        // ===== الكوبونات (Coupons Management) - توحيد مع منطق الأدمن =====
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
        
        // ===== Mall Coupons Management =====
        Route::get('/mall-coupons', [MerchantController::class, 'getMallCoupons']);
        
        // ===== Coupon Activations =====
        Route::get('/coupon-activations', [MerchantController::class, 'getCouponActivations']);
        
        // ===== العمولات (Commissions Management) =====
        Route::prefix('commissions')->group(function () {
            Route::get('/', [MerchantController::class, 'getCommissions']);
            Route::get('/transactions', [MerchantController::class, 'getCommissionTransactions']);
            Route::get('/rates', [MerchantController::class, 'getCommissionRates']);
        });
        
        // ===== الإعلانات (Ads Management) =====
        Route::prefix('ads')->group(function () {
            Route::get('/', [MerchantController::class, 'getAds']);
            Route::get('/{id}', [MerchantController::class, 'getAd']);
            Route::post('/', [MerchantController::class, 'createAd']);
            Route::put('/{id}', [MerchantController::class, 'updateAd']);
            Route::delete('/{id}', [MerchantController::class, 'deleteAd']);
            Route::get('/{id}/status', [MerchantController::class, 'getAdStatus']);
        });
        
        // ===== QR Activation =====
        Route::prefix('qr')->group(function () {
            Route::post('/scan', [QrActivationController::class, 'scanAndActivate']);
            Route::post('/validate', [QrActivationController::class, 'validateQr']);
            Route::get('/scanner', [QrActivationController::class, 'scannerPage']);
        });
        
        // ===== الفواتير (Invoices) =====
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('/{id}', [InvoiceController::class, 'show']);
            Route::get('/{id}/download', [InvoiceController::class, 'downloadPdf']);
        });
        
        // ===== الموظفون (Staff Management) =====
        Route::prefix('staff')->group(function () {
            Route::get('/', [MerchantStaffController::class, 'index']);
            Route::post('/', [MerchantStaffController::class, 'create']);
            Route::put('/{id}', [MerchantStaffController::class, 'update']);
            Route::delete('/{id}', [MerchantStaffController::class, 'delete']);
        });
        
        // ===== PIN/Biometric Login =====
        Route::post('/login-pin', [AuthController::class, 'loginWithPin']);
        Route::post('/setup-pin', [MerchantController::class, 'setupPin']);
        
        // ===== المالية (Financial routes) =====
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
        
        // ===== محfظة المعاملات (Wallet Transactions) =====
        Route::prefix('wallet')->group(function () {
            Route::get('/transactions', [WalletTransactionController::class, 'index']);
            Route::get('/transactions/export', [WalletTransactionController::class, 'export']);
        });
        
        // ===== التحقق (Merchant Verification) =====
        Route::get('/verification', [MerchantVerificationController::class, 'show']);
        Route::post('/verification/upload', [MerchantVerificationController::class, 'uploadDocuments']);
        
        // ===== التحذيرات (Merchant Warnings) =====
        Route::get('/warnings', [MerchantWarningController::class, 'index']);
        
        // ===== إشعارات التاجر (Notifications Management) =====
        Route::prefix('notifications')->group(function () {
            Route::get('/', [MerchantController::class, 'getNotifications']);
            Route::post('/mark-all-read', [MerchantController::class, 'markAllNotificationsAsRead']);
        });
    });
});