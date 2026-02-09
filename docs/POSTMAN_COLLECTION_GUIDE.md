# 📬 OFROO API - Postman Collection Guide

## 🎯 Overview

This Postman collection provides complete access to all OFROO Platform APIs including:
- Authentication & Authorization
- Offers & Categories
- Cart & Orders
- Financial System (Wallet, Transactions, Withdrawals)
- Advanced Reporting (PDF & Excel)
- Roles & Permissions (RBAC)
- Certificates & Courses
- Admin Control Panel

## 🚀 Quick Start

### 1. Import Collection

1. Open Postman
2. Click **Import** button
3. Select `postman_collection.json` file
4. Collection will be imported with all endpoints organized

### 2. Configure Environment Variables

The collection uses these variables:
- `base_url` - API base URL (default: `http://localhost:8000/api`)
- `auth_token` - Authentication token (auto-saved after login)
- `merchant_id` - Merchant ID (optional)
- `user_id` - User ID (optional)

**To update base_url:**
1. Click on collection name
2. Go to **Variables** tab
3. Update `base_url` value
4. Click **Save**

### 3. Authentication Flow

1. **Register** or **Login** to get authentication token
2. Token is automatically saved to `auth_token` variable
3. All protected endpoints use Bearer token automatically

**Login Example:**
```
POST /auth/login
{
  "email": "user@example.com",
  "password": "password123"
}
```

Token will be saved automatically after successful login.

## 📁 Collection Structure

### 🔐 Authentication
- Register User
- Register Merchant
- Login (auto-saves token)
- Request OTP
- Verify OTP
- Logout

### 📦 Offers & Categories
- List Categories
- Get Category Details
- List Offers (with filters: category, search, nearby)
- Get Offer Details

### 🛒 Cart & Orders
- Get Cart
- Add to Cart
- Update Cart Item
- Remove from Cart
- Clear Cart
- Checkout
- List Orders
- Get Order Details
- Get Order Coupons
- Cancel Order
- Get Wallet Coupons
- Create Review

### 🏪 Merchant
- List Merchant Offers
- Create Offer
- Update Offer
- Delete Offer
- List Merchant Orders
- Activate Coupon
- Get Store Locations
- Add Store Location
- Get Statistics

### 💰 Financial System
- Get Wallet (balance, earnings, withdrawals)
- Get Transactions (with filters)
- Get Earnings Report (P&L)
- Record Expense
- Get Expenses
- Request Withdrawal
- Get Withdrawals
- Get Sales Tracking

### 👑 Admin
- **Users Management**
  - List Users
  - Get User Details
  - Update User
  - Delete User

- **Merchants Management**
  - List Merchants
  - Approve Merchant

- **Offers Management**
  - List All Offers
  - Approve Offer

- **Reports**
  - Users Report
  - Merchants Report
  - Orders Report
  - Products Report
  - Payments Report
  - Financial Report
  - Sales Report
  - Export Report PDF
  - Export Report Excel

- **Financial Dashboard**
  - Get Financial Dashboard

- **Withdrawals Management**
  - List Withdrawals
  - Approve Withdrawal
  - Reject Withdrawal
  - Complete Withdrawal

- **Roles & Permissions**
  - List Permissions
  - Create Permission
  - List Roles
  - Create Role
  - Assign Permissions to Role

- **Settings**
  - Get Settings
  - Update Settings
  - Update Category Order

- **Courses**
  - List Courses
  - Create Course

- **Certificates**
  - List Certificates
  - Generate Certificate
  - Verify Certificate

## 🔑 Authentication

### Bearer Token
All protected endpoints use Bearer token authentication. Token is automatically included in requests after login.

### Token Management
- Token is saved automatically after login
- Token is stored in collection variable `auth_token`
- Token is used in Authorization header: `Bearer {{auth_token}}`

## 📊 Common Query Parameters

### Pagination
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15)

### Date Filters
- `from` - Start date (format: YYYY-MM-DD)
- `to` - End date (format: YYYY-MM-DD)

### Status Filters
- `status` - Filter by status (varies by endpoint)
- `payment_status` - Filter by payment status
- `approved` - Filter by approval status (true/false)

## 📝 Request Examples

### Create Offer (Merchant)
```json
POST /merchant/offers
{
  "title_ar": "خصم 50%",
  "title_en": "50% Discount",
  "description_ar": "وصف العرض",
  "description_en": "Offer description",
  "price": 25.00,
  "original_price": 50.00,
  "discount_percent": 50,
  "total_coupons": 100,
  "start_at": "2024-01-01 00:00:00",
  "end_at": "2024-12-31 23:59:59",
  "category_id": 1,
  "location_id": 1
}
```

### Record Expense (Merchant)
```json
POST /merchant/financial/expenses
{
  "expense_type": "advertising",
  "amount": 500.00,
  "description": "Facebook ads campaign",
  "expense_date": "2024-01-15",
  "receipt_url": "https://example.com/receipt.pdf"
}
```

### Request Withdrawal (Merchant)
```json
POST /merchant/financial/withdrawals
{
  "amount": 1000.00,
  "withdrawal_method": "bank_transfer",
  "account_details": "Bank: ABC Bank, Account: 1234567890"
}
```

### Generate Report (Admin)
```
GET /admin/reports/orders?from=2024-01-01&to=2024-12-31&merchant=1&payment_status=paid
```

### Export Report PDF (Admin)
```
GET /admin/reports/export/orders/pdf?from=2024-01-01&to=2024-12-31&language=ar
```

## 🎨 Response Format

All responses follow this format:

### Success Response
```json
{
  "message": "Operation successful",
  "data": { ... },
  "meta": { ... }  // For paginated responses
}
```

### Error Response
```json
{
  "message": "Error message",
  "errors": { ... }  // Validation errors
}
```

## 🔍 Testing Tips

### 1. Test Authentication First
Always start by testing login endpoint to get authentication token.

### 2. Use Collection Variables
Collection variables are automatically updated:
- `auth_token` - Updated after login
- `user_id` - Updated after login (if available)

### 3. Test in Order
1. Authentication → Get token
2. Browse Offers → View available offers
3. Add to Cart → Build cart
4. Checkout → Create order
5. View Orders → Check order status

### 4. Merchant Flow
1. Register/Login as Merchant
2. Create Offer → Wait for admin approval
3. View Orders → See customer orders
4. Check Financial → View wallet and earnings
5. Request Withdrawal → Request payout

### 5. Admin Flow
1. Login as Admin
2. Approve Merchants → Activate merchant accounts
3. Approve Offers → Activate offers
4. View Reports → Generate reports
5. Manage Withdrawals → Approve/reject withdrawals
6. Manage Permissions → Create roles and assign permissions

## 📥 Export Reports

### PDF Export
```
GET /admin/reports/export/{type}/pdf?from=2024-01-01&to=2024-12-31&language=ar
```
Types: `users`, `merchants`, `orders`, `products`, `payments`, `financial`

### Excel Export
```
GET /admin/reports/export/{type}/excel?from=2024-01-01&to=2024-12-31
```
Types: `users`, `merchants`, `orders`, `products`, `payments`, `financial`

## 🛡️ Security Notes

1. **Never commit tokens** - Tokens are stored in collection variables, not in code
2. **Use HTTPS in production** - Update `base_url` to use HTTPS
3. **Token expiration** - Tokens may expire, re-login if you get 401 errors
4. **Rate limiting** - Some endpoints have rate limiting (5 requests per minute)

## 🐛 Troubleshooting

### 401 Unauthorized
- Token expired or invalid
- Solution: Re-login to get new token

### 403 Forbidden
- Insufficient permissions
- Solution: Check user role and permissions

### 404 Not Found
- Endpoint doesn't exist
- Solution: Check endpoint URL and method

### 422 Validation Error
- Invalid request data
- Solution: Check request body and validation rules

### 500 Server Error
- Server-side error
- Solution: Check server logs and contact support

## 📚 Additional Resources

- API Documentation: `docs/openapi.yaml`
- Database Schema: `database/ofroo_database.sql`
- Upgrade Summary: `UPGRADE_COMPLETE.md`

## 💡 Pro Tips

1. **Use Postman Environments** - Create different environments for dev, staging, production
2. **Save Responses** - Save example responses for documentation
3. **Use Tests** - Add tests to validate responses automatically
4. **Use Pre-request Scripts** - Generate dynamic data for requests
5. **Organize with Folders** - Collection is already organized by feature

## 📞 Support

For issues or questions:
1. Check API documentation
2. Review error messages
3. Check server logs
4. Contact development team

---

**Happy Testing! 🚀**

