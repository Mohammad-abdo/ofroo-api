# OFROO - Local Coupons & Offers Application

## نظرة عامة / Overview

OFROO is a comprehensive Laravel-based backend application for managing local coupons and offers. The application supports multiple user roles (Admin, Merchant, User), bilingual support (Arabic/English), location-based offers, coupon generation with barcodes, and payment processing.

OFROO هو تطبيق باك-أند شامل مبني على Laravel لإدارة الكوبونات والعروض المحلية. يدعم التطبيق أدوار مستخدمين متعددة (مدير، تاجر، مستخدم)، دعم ثنائي اللغة (عربي/إنجليزي)، عروض قائمة على الموقع الجغرافي، توليد كوبونات مع باركود، ومعالجة المدفوعات.

## المتطلبات / Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js & NPM (for frontend assets)
- Redis (optional, for queues and caching)

## التثبيت / Installation

### 1. Clone the repository
```bash
git clone <repository-url>
cd OFROO
```

### 2. Install dependencies
```bash
composer install
npm install
```

### 3. Environment setup
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure .env file
Edit `.env` file and set the following:

```env
APP_NAME=OFROO
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ofroo
DB_USERNAME=root
DB_PASSWORD=

# Mail Configuration (SMTP/SendGrid)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@ofroo.com
MAIL_FROM_NAME="${APP_NAME}"

# Google Maps API
GOOGLE_MAPS_API_KEY=your_google_maps_api_key

# Barcode Settings
BARCODE_TYPE=code128
BARCODE_FORMAT=png

# Queue Configuration
QUEUE_CONNECTION=database
# or use Redis:
# QUEUE_CONNECTION=redis
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379

# Firebase Cloud Messaging (FCM)
FCM_SERVER_KEY=your_fcm_server_key
FCM_SENDER_ID=your_fcm_sender_id
```

### 5. Run migrations and seeders
```bash
php artisan migrate
php artisan db:seed
```

Or use the SQL script:
```bash
mysql -u root -p ofroo < database/ofroo_database.sql
```

### 6. Publish vendor assets
```bash
php artisan vendor:publish --tag=laravel-assets --ansi
php artisan storage:link
```

### 7. Start the development server
```bash
php artisan serve
```

The application will be available at `http://localhost:8000`

## استخدام Docker / Using Docker

### Docker Compose Setup

```bash
docker-compose up -d
```

This will start:
- MySQL database
- PHP-FPM
- Nginx
- Redis
- Queue worker

### Access the application
- Application: http://localhost
- Database: localhost:3306
- Redis: localhost:6379

## البنية / Structure

### Models
- `User` - Users with roles (admin, merchant, user)
- `Role` - User roles and permissions
- `Merchant` - Merchant accounts
- `StoreLocation` - Store locations with GPS coordinates
- `Category` - Offer categories (hierarchical)
- `Offer` - Offers/coupons
- `Cart` & `CartItem` - Shopping cart
- `Order` & `OrderItem` - Orders
- `Coupon` - Generated coupons with barcodes
- `Payment` - Payment transactions
- `Review` - User reviews (not public per SRS)
- `Notification` - System notifications
- `Setting` - Application settings
- `LoginAttempt` - Failed login tracking
- `Subscription` - Future subscription feature

### API Endpoints

#### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `POST /api/auth/otp/request` - Request OTP
- `POST /api/auth/otp/verify` - Verify OTP

#### Offers (Public)
- `GET /api/offers` - List offers with filters
- `GET /api/offers/{id}` - Get offer details

#### Cart
- `POST /api/cart/add` - Add item to cart
- `GET /api/cart` - Get cart
- `DELETE /api/cart/{id}` - Remove item from cart

#### Orders
- `POST /api/checkout` - Create order
- `GET /api/orders` - List user orders
- `GET /api/orders/{id}` - Get order details

#### Wallet
- `GET /api/wallet/coupons` - Get user coupons

#### Reviews
- `POST /api/reviews` - Create review

#### Merchant Endpoints
- `GET /api/merchant/offers` - List merchant offers
- `POST /api/merchant/offers` - Create offer
- `PUT /api/merchant/offers/{id}` - Update offer
- `DELETE /api/merchant/offers/{id}` - Delete offer
- `GET /api/merchant/orders` - List merchant orders
- `POST /api/merchant/coupons/{id}/activate` - Activate coupon

#### Admin Endpoints
- `GET /api/admin/users` - List users
- `GET /api/admin/merchants` - List merchants
- `POST /api/admin/merchants/{id}/approve` - Approve merchant
- `GET /api/admin/reports/sales` - Sales reports
- `GET /api/admin/settings` - Get settings
- `PUT /api/admin/settings` - Update settings

## Features

### 1. Authentication & Authorization
- Laravel Sanctum for API authentication
- Role-based access control (RBAC)
- OTP verification via email/SMS
- Failed login attempt tracking

### 2. Offers Management
- Bilingual support (Arabic/English)
- Location-based offers with GPS coordinates
- Image uploads
- Category hierarchy
- Status management (draft, pending, active, expired)

### 3. Coupon System
- Unique coupon code generation (OFR-XXXXXX)
- Barcode generation (Code128/QR)
- PDF generation with barcodes
- Email delivery with coupon attachments
- Status tracking (reserved, activated, used, cancelled, expired)

### 4. Payment Processing
- Multiple payment methods (cash, card)
- Payment gateway integration ready
- Transaction logging

### 5. Location Services
- Google Maps integration
- Haversine distance calculation
- Nearby offers search
- Store location management

### 6. Notifications
- Email notifications (queued)
- Push notifications (FCM ready)
- In-app notifications

### 7. Admin Panel Features
- User management
- Merchant approval
- Offer approval
- Settings management
- Reports and analytics
- CSV/PDF exports

## Testing

Run tests:
```bash
php artisan test
```

## Queue Workers

Process queued jobs:
```bash
php artisan queue:work
```

Or use supervisor for production.

## Scheduled Tasks

The application includes scheduled tasks for:
- Expiring coupons
- Sending reminder emails
- Database backups

Run scheduler:
```bash
php artisan schedule:work
```

Or add to crontab:
```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Security

- Password hashing (bcrypt)
- CSRF protection
- Rate limiting on auth endpoints
- Input validation and sanitization
- CORS configuration
- Failed login attempt logging
- GDPR compliance (soft delete + anonymize)

## API Documentation

API documentation is available via:
- Postman Collection: `docs/postman_collection.json`
- OpenAPI/Swagger: `docs/openapi.yaml`

## License

This project is proprietary software.

## Support

For support, contact: support@ofroo.com

---

## ملاحظات إضافية / Additional Notes

- جميع الحقول تدعم العربية والإنجليزية / All fields support Arabic and English
- قاعدة البيانات تحتوي على مؤشرات محسّنة للاستعلامات الجغرافية / Database includes optimized indexes for geo queries
- النظام يدعم النسخ الاحتياطي التلقائي / System supports automatic backups
- متوافق مع سياسات البيانات المصرية / Compliant with Egypt data policies
