# OFROO API - قائمة اختبار الـ Endpoints

## 📋 التحضيرات

### 1. تجهيز البيئة
- [ ] تأكد من تشغيل MySQL
- [ ] شغّل `php artisan migrate:fresh --seed`
- [ ] شغّل `php artisan serve`
- [ ] تأكد من أن الـ URL هو `http://localhost:8000`

### 2. استيراد Postman
- [ ] افتح Postman
- [ ] اضغط Import → اختر `docs/postman_collection.json`
- [ ] تأكد من تعيين `{{base_url}}` إلى `http://localhost:8000/api`

---

## 🔐 اختبارات المصادقة

### Register User
- [ ] **Endpoint**: `POST /api/auth/register`
- [ ] **الحالة المتوقعة**: 201 Created
- [ ] **البيانات المطلوبة**:
  ```json
  {
    "name": "أحمد علي",
    "email": "ahmed@example.com",
    "phone": "+96512345678",
    "password": "password123",
    "password_confirmation": "password123",
    "language": "ar"
  }
  ```
- [ ] **التحقق**: يجب أن يتم حفظ token في `auth_token`

### Login
- [ ] **Endpoint**: `POST /api/auth/login`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **البيانات**:
  ```json
  {
    "email": "ahmed@example.com",
    "password": "password123"
  }
  ```

### Logout
- [ ] **Endpoint**: `POST /api/auth/logout`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **المتطلبات**: يجب أن تكون مسجل دخول

---

## 🏪 اختبارات الفئات والعروض

### List Categories
- [ ] **Endpoint**: `GET /api/categories`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **التحقق**: يجب أن ترى قائمة الفئات

### Get Category
- [ ] **Endpoint**: `GET /api/categories/1`
- [ ] **الحالة المتوقعة**: 200 OK

### List Offers
- [ ] **Endpoint**: `GET /api/offers`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **الفلاتر المتاحة**:
  - `category`: رقم الفئة
  - `q`: البحث عن كلمة
  - `nearby=true&lat=...&lng=...`: العروض القريبة

### Get Offer
- [ ] **Endpoint**: `GET /api/offers/1`
- [ ] **الحالة المتوقعة**: 200 OK

---

## 🛒 اختبارات السلة

### Get Cart
- [ ] **Endpoint**: `GET /api/cart`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **المتطلبات**: يجب أن تكون مسجل دخول

### Add to Cart
- [ ] **Endpoint**: `POST /api/cart/add`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **البيانات**:
  ```json
  {
    "offer_id": 1,
    "quantity": 2
  }
  ```

### Update Cart Item
- [ ] **Endpoint**: `PUT /api/cart/1`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **البيانات**:
  ```json
  {
    "quantity": 5
  }
  ```

### Remove from Cart
- [ ] **Endpoint**: `DELETE /api/cart/1`
- [ ] **الحالة المتوقعة**: 200 OK

### Clear Cart
- [ ] **Endpoint**: `DELETE /api/cart`
- [ ] **الحالة المتوقعة**: 200 OK

---

## 🛍️ اختبارات الطلبات

### Create Order (Checkout)
- [ ] **Endpoint**: `POST /api/orders/checkout`
- [ ] **الحالة المتوقعة**: 201 Created
- [ ] **البيانات**:
  ```json
  {
    "cart_id": 1,
    "payment_method": "cash"
  }
  ```
- [ ] **ملاحظة**: يجب أن تكون هناك عناصر في السلة أولاً

### List Orders
- [ ] **Endpoint**: `GET /api/orders`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **التحقق**: يجب أن ترى قائمة طلباتك

### Get Order Details
- [ ] **Endpoint**: `GET /api/orders/1`
- [ ] **الحالة المتوقعة**: 200 OK

### Cancel Order
- [ ] **Endpoint**: `POST /api/orders/1/cancel`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **ملاحظة**: تأكد من أن الطلب قابل للإلغاء

### Get Order Coupons
- [ ] **Endpoint**: `GET /api/orders/1/coupons`
- [ ] **الحالة المتوقعة**: 200 OK

---

## 💰 اختبارات التمويل (Merchant)

### Get Wallet
- [ ] **Endpoint**: `GET /api/merchant/financial/wallet`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **المتطلبات**: يجب أن تكون تاجر

### Get Transactions
- [ ] **Endpoint**: `GET /api/merchant/financial/transactions`
- [ ] **الحالة المتوقعة**: 200 OK

### Get Earnings Report
- [ ] **Endpoint**: `GET /api/merchant/financial/earnings`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **الفلاتر**:
  - `from`: تاريخ البدء
  - `to`: تاريخ الانتهاء
  - `group_by`: يومي/أسبوعي/شهري

### Request Withdrawal
- [ ] **Endpoint**: `POST /api/merchant/financial/withdrawals`
- [ ] **الحالة المتوقعة**: 201 Created
- [ ] **البيانات**:
  ```json
  {
    "amount": 500.00,
    "bank_account": "IB1234567890"
  }
  ```

---

## 📊 اختبارات التقارير (Admin)

### Sales Report
- [ ] **Endpoint**: `GET /api/admin/reports/sales`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **المتطلبات**: يجب أن تكون admin

### Users Report
- [ ] **Endpoint**: `GET /api/admin/reports/users`
- [ ] **الحالة المتوقعة**: 200 OK

### Merchants Report
- [ ] **Endpoint**: `GET /api/admin/reports/merchants`
- [ ] **الحالة المتوقعة**: 200 OK

### Orders Report
- [ ] **Endpoint**: `GET /api/admin/reports/orders`
- [ ] **الحالة المتوقعة**: 200 OK

### Export Report (PDF)
- [ ] **Endpoint**: `GET /api/admin/reports/export/sales/pdf`
- [ ] **الحالة المتوقعة**: 200 OK (PDF file)

### Export Report (Excel)
- [ ] **Endpoint**: `GET /api/admin/reports/export/sales/excel`
- [ ] **الحالة المتوقعة**: 200 OK (Excel file)

---

## 🎁 اختبارات الكوبونات والمحفظة

### Get Wallet Coupons
- [ ] **Endpoint**: `GET /api/wallet/coupons`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **التحقق**: يجب أن ترى جميع كوبوناتك

### Create Review
- [ ] **Endpoint**: `POST /api/reviews`
- [ ] **الحالة المتوقعة**: 201 Created
- [ ] **البيانات**:
  ```json
  {
    "order_id": 1,
    "merchant_id": 1,
    "rating": 5,
    "notes": "ممتاز جداً"
  }
  ```

---

## 🏪 اختبارات التاجر (Merchant)

### Get My Offers
- [ ] **Endpoint**: `GET /api/merchant/offers`
- [ ] **الحالة المتوقعة**: 200 OK

### Create Offer
- [ ] **Endpoint**: `POST /api/merchant/offers`
- [ ] **الحالة المتوقعة**: 201 Created
- [ ] **البيانات**:
  ```json
  {
    "title_ar": "عنوان العرض",
    "title_en": "Offer Title",
    "description_ar": "وصف العرض",
    "description_en": "Offer Description",
    "category_id": 1,
    "price": 99.99,
    "original_price": 149.99,
    "total_coupons": 100,
    "start_at": "2024-11-21T00:00:00",
    "end_at": "2024-12-21T23:59:59"
  }
  ```

### Update Offer
- [ ] **Endpoint**: `PUT /api/merchant/offers/1`
- [ ] **الحالة المتوقعة**: 200 OK

### Delete Offer
- [ ] **Endpoint**: `DELETE /api/merchant/offers/1`
- [ ] **الحالة المتوقعة**: 200 OK

### Get Statistics
- [ ] **Endpoint**: `GET /api/merchant/statistics`
- [ ] **الحالة المتوقعة**: 200 OK

---

## 👨‍💼 اختبارات الإدارة (Admin)

### Get All Users
- [ ] **Endpoint**: `GET /api/admin/users`
- [ ] **الحالة المتوقعة**: 200 OK

### Get All Merchants
- [ ] **Endpoint**: `GET /api/admin/merchants`
- [ ] **الحالة المتوقعة**: 200 OK

### Approve Merchant
- [ ] **Endpoint**: `POST /api/admin/merchants/1/approve`
- [ ] **الحالة المتوقعة**: 200 OK
- [ ] **البيانات**:
  ```json
  {
    "status": "approved",
    "notes": "تم الموافقة"
  }
  ```

### Approve Offer
- [ ] **Endpoint**: `POST /api/admin/offers/1/approve`
- [ ] **الحالة المتوقعة**: 200 OK

---

## 📝 حالات الأخطاء الشائعة للاختبار

### 400 Bad Request
- [ ] عدم إرسال حقل مطلوب
- [ ] صيغة البيانات خاطئة
- [ ] قيمة غير صحيحة

### 401 Unauthorized
- [ ] عدم إرسال token
- [ ] token منتهي الصلاحية
- [ ] token غير صحيح

### 403 Forbidden
- [ ] صلاحيات غير كافية
- [ ] الدور غير صحيح

### 404 Not Found
- [ ] البحث عن عنصر غير موجود
- [ ] route غير موجود

### 409 Conflict
- [ ] محاولة إنشاء عنصر مكرر
- [ ] تحديث حالة غير صحيحة

### 422 Unprocessable Entity
- [ ] خطأ في التحقق من البيانات

### 429 Too Many Requests
- [ ] تجاوز حد الـ rate limiting

### 500 Internal Server Error
- [ ] خطأ في الخادم
- [ ] شاهد logs: `storage/logs/laravel.log`

---

## ✅ نموذج اختبار نهائي

1. **تسجيل مستخدم جديد** ✓
2. **تسجيل الدخول** ✓
3. **عرض الفئات والعروض** ✓
4. **إضافة عنصر للسلة** ✓
5. **إنشاء طلب** ✓
6. **عرض الطلبات** ✓
7. **عرض الكوبونات** ✓
8. **إضافة تقييم** ✓
9. **تسجيل الخروج** ✓

---

## 🔧 نصائح للاختبار

1. استخدم **Postman Collections** للاختبارات السريعة
2. استخدم **Postman Tests** للتحقق من النتائج تلقائياً
3. راقب **Response Headers** للتحقق من الـ status codes
4. اطّلع على **Response Body** للتحقق من البيانات
5. تابع **Logs** في `storage/logs/laravel.log` للتصحيح

---

## 📞 استكشاف الأخطاء

- **تحقق من الـ database connection**: `php artisan db`
- **شاهد الـ logs**: `tail -f storage/logs/laravel.log`
- **اختبر الـ routing**: `php artisan route:list`
- **تحقق من الـ migrations**: `php artisan migrate:status`