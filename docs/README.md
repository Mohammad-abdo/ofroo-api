# OFROO Backend API Documentation

## Project Overview

**OFROO** is a Laravel-based SaaS platform for managing coupons, offers, and merchant services. The system provides APIs for a mobile app and admin dashboard.

---

## Technology Stack

| Component | Technology | Version |
|-----------|------------|---------|
| Framework | Laravel | 10.x |
| Language | PHP | 8.2+ |
| Database | MySQL | 8.0 |
| Cache | Redis | 7.x |
| Authentication | Laravel Sanctum | - |
| Queue | Redis/Broadcast | - |

---

## Project Structure

```
api/
├── app/
│   ├── Console/Commands/          # Artisan commands
│   ├── Exceptions/                # Exception handling
│   ├── Exports/                   # Excel/PDF exports
│   ├── Helpers/                   # Utility helpers
│   ├── Http/
│   │   ├── Controllers/Api/      # API Controllers
│   │   ├── Middleware/            # Custom middleware
│   │   ├── Requests/              # Form request validation
│   │   └── Resources/             # API resources/transformers
│   ├── Jobs/                     # Queue jobs
│   ├── Mail/                     # Email templates
│   ├── Models/                    # Eloquent models
│   ├── Notifications/             # Notification classes
│   ├── Policies/                 # Authorization policies
│   ├── Providers/                 # Service providers
│   ├── Repositories/              # Repository pattern
│   ├── Services/                 # Business logic services
│   └── Traits/                   # Reusable traits
├── config/                        # Configuration files
├── database/
│   ├── factories/                 # Model factories
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Database seeders
├── routes/
│   ├── api.php                   # Main API routes
│   ├── mobile.php                # Mobile-specific routes
│   └── web.php                   # Web routes
└── tests/                       # Unit & Feature tests
```

---

## Authentication

### Sanctum Token Authentication

All protected routes require Bearer token authentication:

```
Authorization: Bearer {token}
```

### Auth Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | User login |
| POST | `/api/auth/logout` | User logout |
| POST | `/api/auth/otp/request` | Request OTP code |
| POST | `/api/auth/otp/verify` | Verify OTP code |
| POST | `/api/auth/register-merchant` | Register as merchant |

---

## API Endpoints

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/categories` | List all categories |
| GET | `/api/categories/{id}` | Get category details |
| GET | `/api/offers` | List active offers |
| GET | `/api/offers/{id}` | Get offer details |
| GET | `/api/offers/search` | Search offers |
| GET | `/api/merchants/{id}` | Get merchant profile |
| GET | `/api/merchants/{id}/offers` | Get merchant offers |

### Authenticated User Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/user/profile` | Get user profile |
| PUT | `/api/user/profile` | Update user profile |
| GET | `/api/cart` | Get user cart |
| POST | `/api/cart/add` | Add item to cart |
| POST | `/api/orders/checkout` | Checkout order |
| GET | `/api/orders` | Get user orders |
| GET | `/api/wallet/coupons` | Get user's wallet coupons |
| POST | `/api/reviews` | Create review |
| GET | `/api/loyalty/account` | Get loyalty account |

### Merchant Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/merchant/me` | Get merchant profile |
| GET | `/api/merchant/offers` | List merchant offers |
| POST | `/api/merchant/offers` | Create offer |
| PUT | `/api/merchant/offers/{id}` | Update offer |
| DELETE | `/api/merchant/offers/{id}` | Delete offer |
| GET | `/api/merchant/coupons` | List merchant coupons |
| POST | `/api/merchant/coupons` | Create coupon |
| GET | `/api/merchant/statistics` | Get merchant statistics |
| GET | `/api/merchant/financial/wallet` | Get wallet balance |
| GET | `/api/merchant/financial/transactions` | Get transactions |
| POST | `/api/merchant/financial/withdrawals` | Request withdrawal |
| GET | `/api/merchant/locations` | Get store locations |
| POST | `/api/merchant/locations` | Create location |
| GET | `/api/merchant/commissions` | Get commissions |
| GET | `/api/merchant/qr/activations` | QR activations |

### Admin Endpoints

#### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/dashboard/stats` | Dashboard statistics |
| GET | `/api/admin/dashboard/overview` | Full dashboard data |

#### User Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/users` | List users |
| GET | `/api/admin/users/{id}` | Get user details |
| POST | `/api/admin/users` | Create user |
| PUT | `/api/admin/users/{id}` | Update user |
| DELETE | `/api/admin/users/{id}` | Delete user |
| POST | `/api/admin/users/{id}/block` | Block user |

#### Merchant Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/merchants` | List merchants |
| GET | `/api/admin/merchants/{id}` | Get merchant details |
| POST | `/api/admin/merchants` | Create merchant |
| PUT | `/api/admin/merchants/{id}` | Update merchant |
| DELETE | `/api/admin/merchants/{id}` | Delete merchant |
| POST | `/api/admin/merchants/{id}/approve` | Approve merchant |
| POST | `/api/admin/merchants/{id}/block` | Block merchant |

#### Offer Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/offers` | List offers |
| GET | `/api/admin/offers/{id}` | Get offer details |
| POST | `/api/admin/offers` | Create offer |
| PUT | `/api/admin/offers/{id}` | Update offer |
| DELETE | `/api/admin/offers/{id}` | Delete offer |
| POST | `/api/admin/offers/{id}/approve` | Approve offer |

#### Coupon Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/coupons` | List coupons |
| GET | `/api/admin/coupons/{id}` | Get coupon details |
| POST | `/api/admin/coupons` | Create coupon |
| PUT | `/api/admin/coupons/{id}` | Update coupon |
| DELETE | `/api/admin/coupons/{id}` | Delete coupon |

#### Category Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/categories` | List categories |
| POST | `/api/admin/categories` | Create category |
| PUT | `/api/admin/categories/{id}` | Update category |
| DELETE | `/api/admin/categories/{id}` | Delete category |
| PUT | `/api/admin/categories/order` | Update category order |

#### Mall Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/malls` | List malls |
| POST | `/api/admin/malls` | Create mall |
| PUT | `/api/admin/malls/{id}` | Update mall |
| DELETE | `/api/admin/malls/{id}` | Delete mall |

#### Wallet Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/wallet` | Wallet overview & stats |
| GET | `/api/admin/wallet/transactions` | Transaction list |
| POST | `/api/admin/wallet/adjust` | Adjust balance |
| GET | `/api/admin/wallet/merchants` | All merchant wallets |
| GET | `/api/admin/wallet/merchants/{id}` | Merchant wallet details |
| POST | `/api/admin/wallet/merchants/{id}/freeze` | Freeze merchant wallet |
| POST | `/api/admin/wallet/merchants/{id}/unfreeze` | Unfreeze wallet |
| GET | `/api/admin/wallet/settings` | Wallet settings |
| PUT | `/api/admin/wallet/settings` | Update wallet settings |

#### Commission Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/commissions` | Commission list |
| GET | `/api/admin/commissions/summary` | Commission statistics |
| GET | `/api/admin/commissions/by-merchant` | Commission by merchant |
| GET | `/api/admin/commissions/export` | Export commissions |

#### Withdrawal Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/withdrawals` | List withdrawals |
| POST | `/api/admin/withdrawals/{id}/approve` | Approve withdrawal |
| POST | `/api/admin/withdrawals/{id}/reject` | Reject withdrawal |
| POST | `/api/admin/withdrawals/{id}/complete` | Complete withdrawal |

#### Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/reports/sales` | Sales report |
| GET | `/api/admin/reports/users` | Users report |
| GET | `/api/admin/reports/merchants` | Merchants report |
| GET | `/api/admin/reports/orders` | Orders report |
| GET | `/api/admin/reports/financial` | Financial report |
| GET | `/api/admin/reports/export/{type}/pdf` | Export PDF |
| GET | `/api/admin/reports/export/{type}/excel` | Export Excel |

#### Settings
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/settings` | Get settings |
| PUT | `/api/admin/settings` | Update settings |
| POST | `/api/admin/settings/logo` | Upload logo |

#### Other
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/activity-logs` | Activity logs |
| GET | `/api/admin/notifications` | Notifications |
| GET | `/api/admin/ads` | List ads |
| GET | `/api/admin/banners` | List banners |

---

## Services Architecture

### Core Services

| Service | Description |
|---------|-------------|
| `OfferService` | Offer CRUD and business logic |
| `CouponService` | Coupon management and activation |
| `WalletService` | Wallet credits, debits, and transactions |
| `FinancialService` | Financial operations, withdrawals |
| `CommissionService` | Commission calculations and tracking |
| `DashboardService` | Dashboard statistics |
| `SearchService` | Global search functionality |
| `ImageUploadService` | Image upload handling |
| `PaymentProcessingService` | Payment processing |
| `ActivityLogService` | Activity logging |

---

## Models

### Core Models

| Model | Description |
|-------|-------------|
| `User` | User accounts with roles |
| `Merchant` | Merchant profiles |
| `MerchantWallet` | Merchant wallet balances |
| `AdminWallet` | Admin platform wallet |
| `Offer` | Special offers |
| `Coupon` | Discount coupons |
| `Order` | Purchase orders |
| `OrderItem` | Order line items |
| `Category` | Offer categories |
| `Mall` | Shopping malls |
| `Branch` | Merchant branches |
| `WalletTransaction` | All wallet transactions |
| `Withdrawal` | Withdrawal requests |
| `Commission` | Commission records |
| `Review` | User reviews |
| `Ad` | Advertisements |
| `SupportTicket` | Support tickets |
| `LoyaltyPoint` | Loyalty program |

---

## Database Tables

### Core Tables

| Table | Description |
|-------|-------------|
| `users` | User accounts |
| `merchants` | Merchant profiles |
| `merchant_wallets` | Merchant wallet balances |
| `admin_wallets` | Admin platform wallet |
| `offers` | Special offers |
| `coupons` | Discount coupons |
| `orders` | Purchase orders |
| `order_items` | Order line items |
| `categories` | Offer categories |
| `malls` | Shopping malls |
| `branches` | Merchant branches |
| `wallet_transactions` | Wallet transaction history |
| `withdrawals` | Withdrawal requests |
| `commissions` | Commission records |
| `reviews` | User reviews |
| `ads` | Advertisements |
| `support_tickets` | Support tickets |
| `activity_logs` | Activity audit log |
| `settings` | Application settings |

### Pivot Tables

| Table | Description |
|-------|-------------|
| `offer_branch` | Offer to branch mapping |
| `offer_category` | Offer to category mapping |
| `user_favorite_offers` | User favorites |

---

## Configuration

### Environment Variables

```env
APP_NAME=OFROO
APP_ENV=local|production
APP_KEY=
APP_DEBUG=true|false
APP_URL=https://domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ofroo
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=redis|file
QUEUE_CONNECTION=redis|sync
SESSION_DRIVER=database|redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=

FRONTEND_URL=https://frontend-domain.com
ADMIN_DASHBOARD_URL=https://admin-domain.com
```

### Key Settings

| Setting Key | Default | Description |
|-------------|---------|-------------|
| `commission_rate` | 0.10 | Platform commission (10%) |
| `minimum_withdrawal` | 100 | Minimum withdrawal amount |
| `withdrawal_fee` | 0 | Fixed withdrawal fee |
| `withdrawal_fee_percent` | 0 | Percentage withdrawal fee |

---

## Installation

### Prerequisites

- PHP 8.2+
- Composer
- MySQL 8.0+
- Redis (optional)

### Steps

```bash
# Clone repository
git clone <repo-url>
cd api

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Create storage link
php artisan storage:link

# Start development server
php artisan serve
```

### Production Setup

```bash
# Install dependencies
composer install --optimize-autoloader

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link

# Setup queue worker
php artisan queue:work
```

---

## API Response Format

### Success Response

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Pagination Response

```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

---

## Rate Limiting

| Endpoint Group | Limit |
|----------------|-------|
| Auth (login/register) | 5 requests/minute |
| OTP | 3 requests/minute |
| Checkout | 5 requests/minute |
| Offer Creation | 20 requests/minute |
| Search | 30 requests/minute |

---

## WebSocket Events

| Event | Channel | Description |
|-------|---------|-------------|
| `OrderCreated` | private-user.{id} | New order notification |
| `CouponActivated` | private-merchant.{id} | Coupon activation alert |
| `WithdrawalStatus` | private-merchant.{id} | Withdrawal update |

---

## Queue Jobs

| Job | Description |
|-----|-------------|
| `SendOtpEmail` | Send OTP via email |
| `SendOrderConfirmationEmail` | Order confirmation email |
| `GenerateScheduledFinancialReport` | Generate scheduled reports |

---

## Scheduled Tasks

| Task | Schedule | Description |
|------|----------|-------------|
| ExpireCoupons | Daily | Expire old coupons |
| AutoUnfreezeMerchants | Daily | Unfreeze expired suspensions |
| BackupDatabase | Weekly | Database backup |
| ProcessScheduledReports | Daily | Generate scheduled reports |

---

## Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=OrderTest

# Run with coverage
php artisan test --coverage
```

---

## Security

- **Authentication**: Laravel Sanctum tokens
- **Authorization**: Role-based access control
- **Validation**: Form Request classes
- **SQL Injection**: Eloquent ORM (parameterized queries)
- **XSS**: Blade auto-escaping
- **CORS**: Configured allowed origins

---

## Performance Optimizations

- Database indexes on frequently queried columns
- Redis caching for settings and categories
- Eager loading to prevent N+1 queries
- Pagination on all list endpoints
- Composite indexes for complex queries

---

## Postman Collection

Import the Postman collection from:
```
/api/docs/postman_collection.json
```

---

## License

Proprietary - OFROO Platform
