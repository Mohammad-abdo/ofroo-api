# OFROO API - تقرير المشاكل والإصلاحات

## ✅ المشاكل المكتشفة والمصححة

### 1. **MissingRateLimiterException: Rate limiter [api] is not defined**
   - **الحالة**: مصححة ✅
   - **المشكلة**: Laravel يحاول استخدام rate limiter باسم 'api' لكنه لم يكن معرّفاً
   - **الحل**: تم إضافة تعريف Rate Limiter في `app/Providers/AppServiceProvider.php`
   ```php
   RateLimiter::for('api', function (Request $request) {
       return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
   });
   ```

### 2. **RouteNotFoundException: Route [login] not defined**
   - **الحالة**: مصححة ✅
   - **المشكلة**: Middleware الـ Authentication يحاول redirect لـ route اسمه 'login' لكن هذا API وليس web app
   - **الحل**: تم إضافة custom exception handler في `bootstrap/app.php`
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

### 3. **Duplicate Entry في Database Seeders**
   - **الحالة**: معروفة ⚠️
   - **المشكلة**: عند تشغيل seeders مرة أخرى، يحدث duplicate entries
   - **السبب**: Seeders لا تتحقق من وجود البيانات قبل إدراجها
   - **التوصية**: استخدم `php artisan migrate:fresh --seed` لمسح واعادة بناء قاعدة البيانات

---

## 📋 قائمة الـ Endpoints الرئيسية

### Authentication Routes
- `POST /api/auth/register` - تسجيل مستخدم جديد
- `POST /api/auth/login` - تسجيل الدخول
- `POST /api/auth/register-merchant` - تسجيل تاجر جديد
- `POST /api/auth/otp/request` - طلب رمز OTP
- `POST /api/auth/otp/verify` - التحقق من رمز OTP
- `POST /api/auth/logout` - تسجيل الخروج

### Public Routes (بدون مصادقة)
- `GET /api/categories` - عرض الفئات
- `GET /api/categories/{id}` - تفاصيل فئة
- `GET /api/offers` - عرض العروض
- `GET /api/offers/{id}` - تفاصيل عرض

### User Routes (مع مصادقة)
- `GET /api/cart` - عرض السلة
- `POST /api/cart/add` - إضافة منتج للسلة
- `PUT /api/cart/{id}` - تحديث كمية المنتج
- `DELETE /api/cart/{id}` - حذف منتج من السلة
- `DELETE /api/cart` - مسح السلة

- `GET /api/orders` - عرض الطلبات
- `GET /api/orders/{id}` - تفاصيل طلب
- `POST /api/orders/checkout` - إنشاء طلب من السلة
- `POST /api/orders/{id}/cancel` - إلغاء طلب
- `GET /api/orders/{id}/coupons` - عرض كوبونات الطلب

- `POST /api/reviews` - إضافة تقييم
- `GET /api/wallet/coupons` - عرض كوبوناتي

### Merchant Routes (مع مصادقة + role merchant)
- `GET /api/merchant/offers` - عروضي
- `POST /api/merchant/offers` - إنشاء عرض
- `PUT /api/merchant/offers/{id}` - تحديث عرض
- `DELETE /api/merchant/offers/{id}` - حذف عرض

- `GET /api/merchant/orders` - الطلبات
- `GET /api/merchant/statistics` - الإحصائيات

- `GET /api/merchant/financial/wallet` - المحفظة
- `GET /api/merchant/financial/transactions` - المعاملات
- `GET /api/merchant/financial/earnings` - الأرباح
- `POST /api/merchant/financial/withdrawals` - طلب سحب
- `GET /api/merchant/financial/withdrawals` - طلبات السحب

### Admin Routes (مع مصادقة + role admin)
- `GET /api/admin/users` - المستخدمون
- `GET /api/admin/merchants` - التجار
- `POST /api/admin/merchants/{id}/approve` - الموافقة على تاجر

- `GET /api/admin/reports/sales` - تقرير المبيعات
- `GET /api/admin/reports/users` - تقرير المستخدمين
- `GET /api/admin/reports/merchants` - تقرير التجار
- `GET /api/admin/reports/orders` - تقرير الطلبات

---

## 🔧 متطلبات التشغيل

### قاعدة البيانات
```bash
# إنشاء قاعدة بيانات جديدة (مسح الموجودة)
php artisan migrate:fresh --seed

# أو فقط تشغيل الـ migrations
php artisan migrate

# تشغيل الـ seeders
php artisan db:seed
```

### أجهزة الخادم المحلي
```bash
# بدء الخادم
php artisan serve

# سيبدأ على: http://localhost:8000
```

### استخدام Postman
1. افتح Postman
2. استورد `docs/postman_collection.json`
3. تأكد من تحديث متغير `base_url` إلى `http://localhost:8000/api`
4. جرّب endpoints

---

## 🛠️ الأخطاء الشائعة وحلولها

### خطأ: "SQLSTATE[HY000]: General error: 1030 Got error"
- **السبب**: قاعدة البيانات قد تكون مالئة أو مغلقة
- **الحل**: أعد تشغيل الخادم وتأكد من اتصال قاعدة البيانات

### خطأ: "Illuminate\Database\QueryException: SQLSTATE[42S02]"
- **السبب**: جدول غير موجود
- **الحل**: شغّل `php artisan migrate`

### خطأ: "500 Internal Server Error"
- **السبب**: عادة تكون أخطاء في الكود
- **الحل**: اطّلع على ملف `storage/logs/laravel.log`

### خطأ: "419 CSRF token mismatch" (في الـ API)
- **السبب**: الـ CSRF middleware تم تفعيله للـ API
- **الحل**: هذا يجب أن لا يحدث للـ API - تأكد من استخدام `auth:sanctum` بدلاً من `web` middleware

---

## 📊 قاعدة البيانات

### الجداول الرئيسية:
- `users` - المستخدمون
- `roles` - الأدوار
- `merchants` - التجار
- `offers` - العروض
- `categories` - الفئات
- `cart` و `cart_items` - السلة
- `orders` و `order_items` - الطلبات
- `coupons` - الكوبونات
- `merchant_wallets` - محافظ التجار
- `withdrawals` - طلبات السحب
- والمزيد...

---

## ⚙️ ملفات التكوين الرئيسية

- `bootstrap/app.php` - إعدادات التطبيق الأساسية
- `app/Providers/AppServiceProvider.php` - تسجيل الخدمات
- `routes/api.php` - جميع routes الـ API
- `config/auth.php` - إعدادات المصادقة
- `config/database.php` - إعدادات قاعدة البيانات

---

## 📝 ملاحظات مهمة

1. **جميع الـ routes الـ API** محمية بـ throttling (60 طلب/دقيقة)
2. **جميع الـ requests الـ JSON** يجب أن تكون الـ header `Content-Type: application/json`
3. **جميع protected routes** تحتاج على `Authorization: Bearer {token}`
4. **الترميز**: جميع الـ fields تدعم العربية والإنجليزية

---

## 🧪 اختبار الـ API

### مثال: تسجيل مستخدم جديد
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ahmed",
    "email": "ahmed@example.com",
    "phone": "+96512345678",
    "password": "password123",
    "password_confirmation": "password123",
    "language": "ar"
  }'
```

### مثال: تسجيل الدخول
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "ahmed@example.com",
    "password": "password123"
  }'
```

---

## 📞 الدعم

للمزيد من المعلومات:
- اطّلع على `docs/POSTMAN_COLLECTION_GUIDE.md`
- اطّلع على OpenAPI spec في `docs/openapi.yaml`
- راجع ملفات الـ Controllers في `app/Http/Controllers/Api/`