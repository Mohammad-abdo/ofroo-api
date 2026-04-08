# Mobile User API Endpoints

This document lists all available API endpoints for mobile users (regular users, not merchants or admins).

## Base URL
All endpoints are prefixed with `/api`

## Authentication
Most endpoints require authentication using Bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

---

## 🔐 Authentication Endpoints

### Register
- **POST** `/api/auth/register`
- **Description**: Register a new user account
- **Body**:
  ```json
  {
    "name": "string",
    "email": "string",
    "phone": "string",
    "password": "string",
    "language": "ar|en",
    "city": "string"
  }
  ```

### Login
- **POST** `/api/auth/login`
- **Description**: Login with email/phone and password
- **Body**:
  ```json
  {
    "email": "string (optional)",
    "phone": "string (optional)",
    "password": "string"
  }
  ```

### Logout
- **POST** `/api/auth/logout`
- **Description**: Logout current user (revoke token)
- **Auth**: Required

### OTP Request
- **POST** `/api/auth/otp/request`
- **Description**: Request OTP code for login
- **Body**:
  ```json
  {
    "phone": "string"
  }
  ```

### OTP Verify
- **POST** `/api/auth/otp/verify`
- **Description**: Verify OTP and login
- **Body**:
  ```json
  {
    "phone": "string",
    "otp_code": "string"
  }
  ```

---

## 👤 User Profile Endpoints

### Get Profile
- **GET** `/api/user/profile`
- **Description**: Get authenticated user's profile
- **Auth**: Required
- **Response**:
  ```json
  {
    "data": {
      "id": 1,
      "name": "string",
      "email": "string",
      "phone": "string",
      "avatar": "string|null",
      "language": "ar|en",
      "city": "string",
      "country": "string",
      "role": {
        "id": 1,
        "name": "user"
      },
      "created_at": "ISO8601",
      "updated_at": "ISO8601"
    }
  }
  ```

### Update Profile
- **PUT** `/api/user/profile`
- **Description**: Update user profile
- **Auth**: Required
- **Body**:
  ```json
  {
    "name": "string (optional)",
    "email": "string (optional)",
    "phone": "string (optional)",
    "language": "ar|en (optional)",
    "city": "string (optional)"
  }
  ```

### Change Password
- **PUT** `/api/user/password`
- **Description**: Change user password
- **Auth**: Required
- **Body**:
  ```json
  {
    "current_password": "string",
    "new_password": "string",
    "new_password_confirmation": "string"
  }
  ```

### Update Phone
- **PUT** `/api/user/phone`
- **Description**: Update phone number
- **Auth**: Required
- **Body**:
  ```json
  {
    "phone": "string"
  }
  ```

### Upload Avatar
- **POST** `/api/user/avatar`
- **Description**: Upload user avatar image
- **Auth**: Required
- **Content-Type**: `multipart/form-data`
- **Body**: `avatar` (file, max 2MB, jpeg/png/jpg/gif)

### Delete Avatar
- **DELETE** `/api/user/avatar`
- **Description**: Delete user avatar
- **Auth**: Required

---

## 🔔 Notifications Endpoints

### Get Notifications
- **GET** `/api/user/notifications`
- **Description**: Get user notifications
- **Auth**: Required
- **Query Parameters**:
  - `per_page`: number (default: 15)
  - `is_read`: boolean (filter by read/unread)
  - `type`: string (filter by type)
  - `search`: string (search in notifications)
- **Response**:
  ```json
  {
    "data": [
      {
        "id": "string",
        "type": "string",
        "title_ar": "string",
        "title_en": "string",
        "message_ar": "string",
        "message_en": "string",
        "read_at": "ISO8601|null",
        "created_at": "ISO8601"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 0
    }
  }
  ```

### Mark Notification as Read
- **POST** `/api/user/notifications/{id}/read`
- **Description**: Mark a notification as read
- **Auth**: Required

### Mark All Notifications as Read
- **POST** `/api/user/notifications/mark-all-read`
- **Description**: Mark all notifications as read
- **Auth**: Required

### Delete Notification
- **DELETE** `/api/user/notifications/{id}`
- **Description**: Delete a notification
- **Auth**: Required

---

## 📊 Statistics Endpoints

### Get User Stats
- **GET** `/api/user/stats`
- **Description**: Get user statistics
- **Auth**: Required
- **Response**:
  ```json
  {
    "data": {
      "orders_count": 10,
      "active_coupons_count": 5,
      "total_spent": 1500.50,
      "loyalty_points": 250
    }
  }
  ```

---

## ⚙️ Settings Endpoints

### Get Settings
- **GET** `/api/user/settings`
- **Description**: Get user settings
- **Auth**: Required
- **Response**:
  ```json
  {
    "data": {
      "language": "ar|en",
      "notifications_enabled": true,
      "email_notifications": true,
      "push_notifications": true
    }
  }
  ```

### Update Settings
- **PUT** `/api/user/settings`
- **Description**: Update user settings
- **Auth**: Required
- **Body**:
  ```json
  {
    "language": "ar|en (optional)",
    "notifications_enabled": "boolean (optional)",
    "email_notifications": "boolean (optional)",
    "push_notifications": "boolean (optional)"
  }
  ```

---

## 📦 Orders Endpoints

### Get Orders History
- **GET** `/api/user/orders`
- **Description**: Get user orders history
- **Auth**: Required
- **Query Parameters**:
  - `per_page`: number (default: 15)
  - `status`: string (filter by status)
- **Response**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "order_number": "string",
        "status": "string",
        "total_amount": 150.50,
        "items_count": 2,
        "merchant": {
          "id": 1,
          "company_name": "string",
          "logo_url": "string|null"
        },
        "created_at": "ISO8601"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 0
    }
  }
  ```

### Get All Orders
- **GET** `/api/orders`
- **Description**: Get all user orders (same as above, alternative endpoint)
- **Auth**: Required

### Get Order Details
- **GET** `/api/orders/{id}`
- **Description**: Get specific order details
- **Auth**: Required

### Get Order Coupons
- **GET** `/api/orders/{id}/coupons`
- **Description**: Get coupons for an order
- **Auth**: Required

### Checkout
- **POST** `/api/orders/checkout`
- **Description**: Create order from cart
- **Auth**: Required

### Cancel Order
- **POST** `/api/orders/{id}/cancel`
- **Description**: Cancel an order
- **Auth**: Required

---

## 🛒 Cart Endpoints

### Get Cart
- **GET** `/api/cart`
- **Description**: Get user cart with items and total
- **Auth**: Required

### Add to Cart
- **POST** `/api/cart/add`
- **Description**: Add item to cart
- **Auth**: Required

### Update Cart Item
- **PUT** `/api/cart/{id}`
- **Description**: Update cart item quantity
- **Auth**: Required

### Remove from Cart
- **DELETE** `/api/cart/{id}`
- **Description**: Remove item from cart
- **Auth**: Required

### Clear Cart
- **DELETE** `/api/cart`
- **Description**: Clear entire cart
- **Auth**: Required

---

## 💰 Wallet Endpoints

### Get Wallet Coupons
- **GET** `/api/wallet/coupons`
- **Description**: Get user wallet coupons (active coupons)
- **Auth**: Required

---

## 🎯 Offers Endpoints

### Get Offers
- **GET** `/api/offers`
- **Description**: List offers with filters
- **Query Parameters**:
  - `category`: number (category ID)
  - `nearby`: boolean
  - `lat`: number (latitude)
  - `lng`: number (longitude)
  - `distance`: number (meters, default: 10000)
  - `q`: string (search query)
  - `page`: number

### Get Offer Details
- **GET** `/api/offers/{id}`
- **Description**: Get specific offer details
- **Auth**: Optional (for personalized data)

### Search Offers
- **GET** `/api/search`
- **Description**: Search offers
- **Query Parameters**:
  - `q`: string (search query)
  - `category`: number (optional)
  - `lat`: number (optional)
  - `lng`: number (optional)

### WhatsApp Contact
- **GET** `/api/offers/{id}/whatsapp`
- **Description**: Get WhatsApp contact link for offer
- **Auth**: Required

---

## ⭐ Reviews Endpoints

### Create Review
- **POST** `/api/reviews`
- **Description**: Create a review for an order
- **Auth**: Required

---

## 🎁 Loyalty Endpoints

### Get Loyalty Account
- **GET** `/api/loyalty/account`
- **Description**: Get user loyalty account information
- **Auth**: Required

### Get Loyalty Transactions
- **GET** `/api/loyalty/transactions`
- **Description**: Get loyalty points transactions
- **Auth**: Required

### Redeem Loyalty Points
- **POST** `/api/loyalty/redeem`
- **Description**: Redeem loyalty points
- **Auth**: Required

---

## 🎫 Support Tickets Endpoints

### Create Ticket
- **POST** `/api/support/tickets`
- **Description**: Create a support ticket
- **Auth**: Required

### Get Tickets
- **GET** `/api/support/tickets`
- **Description**: Get user support tickets
- **Auth**: Required

### Get Ticket Details
- **GET** `/api/support/tickets/{id}`
- **Description**: Get specific ticket details
- **Auth**: Required

---

## 🏷️ Categories Endpoints

### Get Categories
- **GET** `/api/categories`
- **Description**: List all categories
- **Auth**: Optional

### Get Category Details
- **GET** `/api/categories/{id}`
- **Description**: Get specific category details
- **Auth**: Optional

---

## 🗑️ Account Management

### Delete Account
- **DELETE** `/api/user/account`
- **Description**: Delete user account (requires password verification)
- **Auth**: Required
- **Body**:
  ```json
  {
    "password": "string"
  }
  ```
- **Note**: This will anonymize user data (GDPR compliant)

---

## Error Responses

All endpoints may return these error responses:

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 422 Validation Error
```json
{
  "message": "Validation failed",
  "errors": {
    "field": ["Error message"]
  }
}
```

### 404 Not Found
```json
{
  "message": "Resource not found"
}
```

### 500 Server Error
```json
{
  "message": "Server error message"
}
```

