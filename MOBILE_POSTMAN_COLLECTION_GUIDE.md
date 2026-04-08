# 📱 OFROO Mobile Postman Collection Guide

## Overview

This Postman collection is organized by **mobile app screens** to make it easy to find and test endpoints for each section of the mobile application. All endpoints include **full image URLs** and **complete data structures** for mobile integration.

## 📋 Collection Structure

The collection is organized into the following mobile screens:

### 1. 🔐 Authentication Screen
- Register
- Login with Email
- Login with Phone
- Request OTP
- Verify OTP
- Logout

### 2. 🏠 Home & Categories Screen
- Get All Categories (with full image URLs)
- Get Category Details
- Get All Offers (Home Feed)
- Get Offers by Category
- Get Nearby Offers (with location)

### 3. 🔍 Search Screen
- Search Offers (by keyword, category, location)

### 4. 📱 Offer Details Screen
- Get Offer Details (complete with all images, merchant info, pricing)
- Get WhatsApp Contact

### 5. 🛒 Cart Screen
- Get Cart (with full item details and images)
- Add to Cart
- Update Cart Item
- Remove from Cart
- Clear Cart

### 6. 💳 Checkout Screen
- Checkout (create order from cart)

### 7. 📦 Orders Screen
- Get Orders History (with pagination and filters)
- Get Order Details (complete order information)
- Get Order Coupons (with QR codes)
- Cancel Order

### 8. 💰 Wallet Screen
- Get Wallet Coupons (all active coupons with full data)

### 9. 👤 Profile Screen
- Get Profile (with avatar full URL)
- Update Profile
- Upload Avatar (returns full image URL)
- Delete Avatar
- Change Password
- Update Phone
- Get User Statistics

### 10. ⚙️ Settings Screen
- Get Settings
- Update Settings

### 11. 🔔 Notifications Screen
- Get Notifications (with filters and pagination)
- Get Unread Notifications
- Mark Notification as Read
- Mark All Notifications as Read
- Delete Notification

### 12. ⭐ Reviews Screen
- Create Review (with rating, comment, images)

### 13. 🎁 Loyalty Screen
- Get Loyalty Account
- Get Loyalty Transactions
- Redeem Loyalty Points

### 14. 🎫 Support Screen
- Create Support Ticket
- Get Support Tickets
- Get Ticket Details

### 15. 🗑️ Account Management
- Delete Account (with password verification)

## 🔑 Variables

The collection uses two variables:

1. **`base_url`**: Base URL for API (default: `http://127.0.0.1:8000`)
   - Change this to your production server URL

2. **`access_token`**: Authentication token
   - Auto-populated after login/register
   - Can be manually set if needed

## 📸 Full Image URLs

All endpoints that return images include **full URLs** in the format:
```
{{base_url}}/storage/path/to/image.jpg
```

For example:
- Offer images: `http://127.0.0.1:8000/storage/offers/image.jpg`
- User avatars: `http://127.0.0.1:8000/storage/avatars/avatar.jpg`
- Category images: `http://127.0.0.1:8000/storage/categories/category.jpg`

## 📊 Complete Data Structures

All endpoints return complete data structures including:

- **Offers**: Full offer details with images array, merchant info, category, location, pricing, availability
- **Orders**: Complete order information with items, merchant details, payment info, status
- **Cart**: Full cart data with offer details, quantities, prices, totals
- **Profile**: User profile with avatar URL, language, city, country
- **Coupons**: Full coupon data with QR codes, offer details, merchant info
- **Categories**: Category information with images and descriptions

## 🚀 Usage Instructions

### 1. Import Collection
1. Open Postman
2. Click **Import**
3. Select `OFROO_Mobile_User_API.postman_collection.json`
4. Collection will be imported with all folders

### 2. Set Environment Variables
1. Click on the collection name
2. Go to **Variables** tab
3. Set `base_url` to your API server URL
4. `access_token` will be auto-populated after login

### 3. Test Flow
1. Start with **Authentication Screen** → **Register** or **Login**
2. Copy the `token` from the response
3. The token will be automatically saved to `access_token` variable (if auto-script works)
4. Or manually paste it in the collection variables
5. All other endpoints will automatically use the token

### 4. Testing Endpoints
- Navigate to the screen folder you want to test
- Select the endpoint
- Click **Send**
- Check the response for full data including image URLs

## 📝 Response Format

All endpoints return data in this format:

```json
{
  "data": {
    // Full data object
  },
  "meta": {
    // Pagination info (if applicable)
  }
}
```

### Example: Get Offer Details
```json
{
  "data": {
    "id": 1,
    "title": "Pizza Offer",
    "title_ar": "عرض البيتزا",
    "title_en": "Pizza Offer",
    "description": "50% off on all pizzas",
    "price": 10.50,
    "original_price": 21.00,
    "discount_percent": 50,
    "images": [
      "http://127.0.0.1:8000/storage/offers/image1.jpg",
      "http://127.0.0.1:8000/storage/offers/image2.jpg"
    ],
    "merchant": {
      "id": 1,
      "company_name": "Pizza Place",
      "logo_url": "http://127.0.0.1:8000/storage/merchants/logo.jpg"
    },
    "category": {
      "id": 1,
      "name": "Food & Beverage",
      "image_url": "http://127.0.0.1:8000/storage/categories/food.jpg"
    }
  }
}
```

## 🔒 Authentication

Most endpoints require authentication. Include the token in the Authorization header:

```
Authorization: Bearer {{access_token}}
```

The collection automatically adds this header to all authenticated endpoints.

## 📱 Mobile-Specific Features

1. **Full Image URLs**: All images are returned as complete URLs ready for mobile display
2. **Pagination**: List endpoints support pagination for mobile scrolling
3. **Filters**: Endpoints support filtering (by category, status, location, etc.)
4. **Location-Based**: Nearby offers endpoint uses GPS coordinates
5. **OTP Login**: Phone-based authentication for mobile users
6. **File Upload**: Avatar upload uses multipart/form-data

## 🎯 Endpoint Coverage

- ✅ **Authentication**: 6 endpoints
- ✅ **Categories**: 2 endpoints
- ✅ **Offers**: 6 endpoints (list, details, search, nearby, by category)
- ✅ **Cart**: 5 endpoints
- ✅ **Orders**: 5 endpoints
- ✅ **Wallet**: 1 endpoint
- ✅ **Profile**: 7 endpoints
- ✅ **Settings**: 2 endpoints
- ✅ **Notifications**: 5 endpoints
- ✅ **Reviews**: 1 endpoint
- ✅ **Loyalty**: 3 endpoints
- ✅ **Support**: 3 endpoints
- ✅ **Account Management**: 1 endpoint

**Total: 47 endpoints** covering all mobile app screens

## 🔄 Auto-Token Extraction

The collection includes a test script that automatically extracts the token from login/register responses and saves it to the `access_token` variable. This makes testing seamless.

## 📌 Notes

1. **Base URL**: Make sure to update `base_url` variable for your environment
2. **Image URLs**: All image URLs are absolute and include the base URL
3. **Pagination**: Use `page` and `per_page` parameters for list endpoints
4. **Filters**: Enable/disable query parameters as needed
5. **File Upload**: For avatar upload, select a file in the form-data body

## ✅ Ready for Mobile Integration

This collection is fully ready for mobile app development. All endpoints return complete data structures with full image URLs, making it easy to integrate with React Native, Flutter, or any mobile framework.

---

**Last Updated**: 2024-12-28  
**Collection Version**: 2.0.0  
**Total Endpoints**: 47



