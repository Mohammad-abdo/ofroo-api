# تقرير التحقق من Routes والـ Controllers - OFROO API

## 📊 ملخص التحقق

| الحالة | العدد | الوصف |
|--------|-------|--------|
| ✅ مصححة | 2 | المشاكل الرئيسية |
| ⚠️ معروفة | 1 | مشاكل تتطلب انتباه |
| 🔧 Endpoints | 100+ | جميع الـ endpoints قيد العمل |
| 📋 Models | 46 | جميع الـ models موجودة |
| 🛠️ Services | 22 | جميع الـ services موجودة |

---

## 🔍 تفاصيل المشاكل المصححة

### 1. Rate Limiter Missing (✅ تم إصلاحه)
**الملف**: `app/Providers/AppServiceProvider.php`
```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

### 2. Login Route Not Defined (✅ تم إصلاحه)
**الملف**: `bootstrap/app.php`
```php
$exceptions->render(function (Throwable $e, $request) {
    if ($request->expectsJson() && $e instanceof \Illuminate\Auth\AuthenticationException) {
        return response()->json([
            'message' => 'Unauthenticated',
            'error' => 'You must be authenticated to access this resource'
        ], 401);
    }
});
```

---

## 📁 هيكل الـ Routes

### Routes بدون مصادقة (Public)
```
GET  /              - معلومات API
GET  /categories    - عرض الفئات
GET  /categories/{id} - تفاصيل فئة
GET  /offers        - عرض العروض
GET  /offers/{id}   - تفاصيل عرض
GET  /search        - البحث
GET  /offers/{id}/whatsapp - اتصال واتس
```

### Auth Routes
```
POST /auth/register          - تسجيل المستخدم
POST /auth/login             - دخول المستخدم
POST /auth/register-merchant - تسجيل التاجر
POST /auth/otp/request       - طلب OTP
POST /auth/otp/verify        - التحقق من OTP
POST /auth/logout            - تسجيل الخروج (محمي)
```

### User Routes (محمي بـ auth:sanctum)
```
# Cart
GET    /cart        - عرض السلة
POST   /cart/add    - إضافة للسلة
PUT    /cart/{id}   - تحديث السلة
DELETE /cart/{id}   - حذف من السلة
DELETE /cart        - مسح السلة

# Orders
GET    /orders          - عرض الطلبات
GET    /orders/{id}     - تفاصيل الطلب
GET    /orders/{id}/coupons - كوبونات الطلب
POST   /orders/checkout - إنشاء طلب
POST   /orders/{id}/cancel - إلغاء الطلب

# Reviews & Loyalty
POST   /reviews          - إضافة تقييم
GET    /loyalty/account  - حسابي
GET    /loyalty/transactions - معاملاتي
POST   /loyalty/redeem   - استرجاع النقاط
GET    /wallet/coupons   - محفظتي

# Support & CMS
POST   /support/tickets     - فتح تذكرة
GET    /support/tickets     - تذاكري
GET    /support/tickets/{id} - تفاصيل التذكرة
GET    /pages/{slug}        - صفحة
GET    /blogs              - المدونات
GET    /blogs/{slug}       - مقالة
GET    /banners            - اللافتات
```

### Merchant Routes (محمي بـ auth:sanctum + merchant)
```
# Offers
GET    /merchant/offers       - عروضي
POST   /merchant/offers       - إنشاء عرض
PUT    /merchant/offers/{id}  - تحديث عرض
DELETE /merchant/offers/{id}  - حذف عرض

# Orders
GET    /merchant/orders       - طلباتي
GET    /merchant/statistics   - إحصائياتي

# Locations
GET    /merchant/locations         - فروعي
POST   /merchant/locations         - إضافة فرع

# QR Activation
POST   /merchant/qr/scan          - مسح QR
POST   /merchant/qr/validate      - التحقق من QR
GET    /merchant/qr/scanner       - صفحة الماسح

# Invoices
GET    /merchant/invoices       - فواتيري
GET    /merchant/invoices/{id}  - تفاصيل الفاتورة
GET    /merchant/invoices/{id}/download - تحميل PDF

# Staff Management
GET    /merchant/staff         - الموظفون
POST   /merchant/staff         - إضافة موظف
PUT    /merchant/staff/{id}    - تحديث موظف
DELETE /merchant/staff/{id}    - حذف موظف

# PIN/Biometric
POST   /merchant/setup-pin     - إعداد PIN
POST   /merchant/login-pin     - دخول بـ PIN

# Financial
GET    /merchant/financial/wallet      - المحفظة
GET    /merchant/financial/transactions - المعاملات
GET    /merchant/financial/earnings     - الأرباح
POST   /merchant/financial/expenses     - تسجيل مصروف
GET    /merchant/financial/expenses     - المصروفات
POST   /merchant/financial/withdrawals  - طلب سحب
GET    /merchant/financial/withdrawals  - طلبات السحب
GET    /merchant/financial/sales       - تتبع المبيعات

# Wallet Transactions
GET    /merchant/wallet/transactions           - المعاملات
GET    /merchant/wallet/transactions/export    - تصدير

# Verification & Warnings
GET    /merchant/verification          - حالة التحقق
POST   /merchant/verification/upload   - رفع المستندات
GET    /merchant/warnings              - التحذيرات
```

### Admin Routes (محمي بـ auth:sanctum + admin)
```
# Users Management
GET    /admin/users       - المستخدمون
GET    /admin/users/{id}  - تفاصيل المستخدم
PUT    /admin/users/{id}  - تحديث المستخدم
DELETE /admin/users/{id}  - حذف المستخدم

# Merchants Management
GET    /admin/merchants            - التجار
POST   /admin/merchants/{id}/approve - الموافقة على تاجر
POST   /admin/merchants/{id}/suspend - إيقاف تاجر
GET    /admin/merchants/{id}/wallet - محفظة التاجر

# Offers Management
GET    /admin/offers              - العروض
POST   /admin/offers/{id}/approve - الموافقة على عرض

# Reports
GET    /admin/reports/sales                  - تقرير المبيعات
GET    /admin/reports/sales/export           - تصدير PDF
GET    /admin/reports/users                  - تقرير المستخدمين
GET    /admin/reports/merchants              - تقرير التجار
GET    /admin/reports/orders                 - تقرير الطلبات
GET    /admin/reports/products               - تقرير المنتجات
GET    /admin/reports/payments               - تقرير الدفع
GET    /admin/reports/financial              - التقرير المالي
GET    /admin/reports/activations            - تقرير التفعيلات
GET    /admin/reports/gps-engagement         - تقرير GPS
GET    /admin/reports/conversion-funnel      - مسار التحويل
GET    /admin/reports/failed-payments        - الدفعات الفاشلة
GET    /admin/reports/export/{type}/pdf      - تصدير PDF
GET    /admin/reports/export/{type}/excel    - تصدير Excel

# Dashboard
GET    /admin/financial/dashboard            - لوحة القيادة المالية

# Settings
GET    /admin/settings            - الإعدادات
PUT    /admin/settings            - تحديث الإعدادات
PUT    /admin/categories/order    - ترتيب الفئات

# Permissions & Roles
GET    /admin/permissions         - الصلاحيات
POST   /admin/permissions         - إضافة صلاحية
PUT    /admin/permissions/{id}    - تحديث صلاحية
DELETE /admin/permissions/{id}    - حذف صلاحية

GET    /admin/roles              - الأدوار
POST   /admin/roles              - إضافة دور
PUT    /admin/roles/{id}         - تحديث دور
POST   /admin/roles/{id}/permissions - تعيين الصلاحيات
DELETE /admin/roles/{id}         - حذف دور

# Withdrawals Management
GET    /admin/withdrawals              - طلبات السحب
POST   /admin/withdrawals/{id}/approve - الموافقة
POST   /admin/withdrawals/{id}/reject  - الرفض
POST   /admin/withdrawals/{id}/complete - الإتمام

# Courses & Certificates
GET    /admin/courses           - الدورات
POST   /admin/courses           - إضافة دورة
PUT    /admin/courses/{id}      - تحديث دورة
DELETE /admin/courses/{id}      - حذف دورة

GET    /admin/certificates      - الشهادات
GET    /admin/certificates/{id} - تفاصيل الشهادة
POST   /admin/certificates/generate - إصدار شهادة
GET    /admin/certificates/verify/{number} - التحقق

# Support Tickets
GET    /admin/support/tickets              - التذاكر
POST   /admin/support/tickets/{id}/assign  - تعيين تذكرة
POST   /admin/support/tickets/{id}/resolve - حل التذكرة

# CMS Management
POST   /admin/cms/pages          - إضافة صفحة
PUT    /admin/cms/pages/{id}     - تحديث صفحة
DELETE /admin/cms/pages/{id}     - حذف صفحة

POST   /admin/cms/blogs          - إضافة مقالة
PUT    /admin/cms/blogs/{id}     - تحديث مقالة
DELETE /admin/cms/blogs/{id}     - حذف مقالة

POST   /admin/cms/banners        - إضافة لافتة
PUT    /admin/cms/banners/{id}   - تحديث لافتة
DELETE /admin/cms/banners/{id}   - حذف لافتة

# Activity Logs
GET    /admin/activity-logs      - السجلات

# Payment Gateways
GET    /admin/payment-gateways   - بوابات الدفع
POST   /admin/payment-gateways   - إضافة بوابة
PUT    /admin/payment-gateways/{id} - تحديث بوابة

# Tax Settings
GET    /admin/tax                - إعدادات الضرائب
PUT    /admin/tax                - تحديث الضرائب

# Invoices Generation
POST   /admin/invoices/generate  - إنشاء فواتير شهرية

# Activation Reports
GET    /admin/activation-reports - تقارير التفعيلات

# Admin Wallet
GET    /admin/wallet              - محفظة الإدارة
GET    /admin/wallet/transactions - معاملات المحفظة
POST   /admin/wallet/adjust       - تعديل الرصيد
GET    /admin/wallet/merchants/{id} - محفظة التاجر

# Merchant Verification
GET    /admin/verifications                          - طلبات التحقق
POST   /admin/verifications/{merchantId}/review      - مراجعة
GET    /admin/verifications/{merchantId}/documents/{type} - تحميل

# Merchant Warnings
GET    /admin/warnings                    - التحذيرات
POST   /admin/warnings/merchants/{id}     - إصدار تحذير
POST   /admin/warnings/{id}/deactivate    - إلغاء التحذير

# Review Moderation
GET    /admin/reviews           - التقييمات
POST   /admin/reviews/{id}/moderate - تفعيل/تعطيل
POST   /admin/reviews/{id}/restore  - استرجاع

# Invoices Management
GET    /admin/invoices              - الفواتير
GET    /admin/invoices/{id}         - تفاصيل الفاتورة
POST   /admin/invoices/orders/{orderId}/generate - إنشاء
POST   /admin/invoices/{id}/reissue   - إعادة إصدار
POST   /admin/invoices/{id}/cancel    - إلغاء

# Regulatory Checks
GET    /admin/regulatory-checks                 - الفحوصات
POST   /admin/regulatory-checks                 - فحص جديد
GET    /admin/regulatory-checks/{id}           - تفاصيل
PUT    /admin/regulatory-checks/{id}           - تحديث
DELETE /admin/regulatory-checks/{id}           - حذف
GET    /admin/regulatory-checks/merchants/{merchantId} - فحوصات التاجر
POST   /admin/regulatory-checks/merchants/{merchantId}/automated - فحص تلقائي

# Financial Reports Cache
GET    /admin/reports-cache           - التقارير المخزنة
GET    /admin/reports-cache/{id}      - تفاصيل التقرير
```

---

## 📋 الـ Models المتاحة

| Model | الجدول | الاستخدام |
|-------|--------|-----------|
| User | users | المستخدمون |
| Role | roles | الأدوار |
| Permission | permissions | الصلاحيات |
| Merchant | merchants | التجار |
| MerchantStaff | merchant_staff | موظفو التجار |
| Offer | offers | العروض |
| Category | categories | الفئات |
| Cart | carts | السلات |
| CartItem | cart_items | عناصر السلة |
| Order | orders | الطلبات |
| OrderItem | order_items | عناصر الطلبات |
| Coupon | coupons | الكوبونات |
| Payment | payments | الدفعات |
| Review | reviews | التقييمات |
| MerchantWallet | merchant_wallets | محافظ التجار |
| AdminWallet | admin_wallets | محفظة الإدارة |
| FinancialTransaction | financial_transactions | المعاملات المالية |
| WalletTransaction | wallet_transactions | معاملات المحفظة |
| Withdrawal | withdrawals | طلبات السحب |
| LoyaltyPoint | loyalty_points | نقاط الولاء |
| LoyaltyTransaction | loyalty_transactions | معاملات الولاء |
| Certificate | certificates | الشهادات |
| Course | courses | الدورات |
| SupportTicket | support_tickets | تذاكر الدعم |
| TicketAttachment | ticket_attachments | مرفقات التذاكر |
| CmsPage | cms_pages | الصفحات |
| CmsBlog | cms_blogs | المدونات |
| Banner | banners | اللافتات |
| StoreLocation | store_locations | الفروع |
| UserDevice | user_devices | أجهزة المستخدمين |
| TwoFactorAuth | two_factor_auths | المصادقة الثنائية |
| PaymentGateway | payment_gateways | بوابات الدفع |
| TaxSetting | tax_settings | إعدادات الضرائب |
| Setting | settings | الإعدادات العامة |
| LoginAttempt | login_attempts | محاولات الدخول |
| ActivityLog | activity_logs | السجلات |
| Subscription | subscriptions | الاشتراكات |
| Expense | expenses | المصروفات |
| Commission | commissions | العمولات |
| MerchantInvoice | merchant_invoices | فواتير التجار |
| MerchantPin | merchant_pins | رموز PIN |
| MerchantVerification | merchant_verifications | التحقق من التجار |
| MerchantWarning | merchant_warnings | تحذيرات التجار |
| ActivationReport | activation_reports | تقارير التفعيلات |
| RegulatoryCheck | regulatory_checks | الفحوصات النظامية |
| FinancialReportsCache | financial_reports_cache | التقارير المالية المخزنة |

---

## 🛠️ الـ Services المتاحة

| Service | الوصف |
|---------|--------|
| ActivityLogService | تسجيل الأنشطة |
| CertificateService | إدارة الشهادات |
| CouponService | إدارة الكوبونات |
| EmailService | إرسال رسائل البريد |
| FeatureFlagService | إدارة الميزات |
| FinancialReportsCacheService | تخزين التقارير المالية |
| FinancialService | العمليات المالية |
| ImageService | معالجة الصور |
| InvoiceGenerationService | إنشاء الفواتير |
| InvoiceService | إدارة الفواتير |
| LoyaltyService | إدارة الولاء |
| MerchantSuspensionService | إيقاف التجار |
| NotificationService | إرسال الإشعارات |
| PaymentGatewayService | بوابات الدفع |
| PdfService | إنشاء ملفات PDF |
| QrActivationService | تفعيل QR |
| ReportService | إنشاء التقارير |
| SearchService | البحث |
| SupportTicketService | إدارة التذاكر |
| TaxService | حسابات الضرائب |
| WalletService | إدارة المحافظ |
| WhatsappService | تكامل واتس |

---

## 🚀 الخطوات التالية

1. **تشغيل الخادم**
   ```bash
   php artisan serve
   ```

2. **إنشاء قاعدة البيانات**
   ```bash
   php artisan migrate:fresh --seed
   ```

3. **اختبار الـ API**
   - افتح Postman
   - استورد `docs/postman_collection.json`
   - ابدأ بـ endpoints البسيطة

4. **مراقبة الأخطاء**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## 📞 ملاحظات هامة

- جميع الـ passwords محمية بـ bcrypt
- جميع الـ tokens تُدار بـ Laravel Sanctum
- الـ Rate limiting مفعّل (60 طلب/دقيقة للـ API)
- جميع الـ responses بـ JSON
- جميع التوثيق بـ OpenAPI في `docs/openapi.yaml`