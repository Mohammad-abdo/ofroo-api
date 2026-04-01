# OFROO API Quick Reference

## Common Requests

### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

### Register
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

### Create Offer (Merchant)
```http
POST /api/merchant/offers
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "50% Off Electronics",
  "title_ar": "خصم 50% على الإلكترونيات",
  "description": "Great deals on electronics",
  "category_id": 1,
  "price": 99.99,
  "discount": 50,
  "start_date": "2024-01-01",
  "end_date": "2024-12-31"
}
```

### Checkout
```http
POST /api/orders/checkout
Authorization: Bearer {token}
Content-Type: application/json

{
  "offer_id": 1,
  "quantity": 1,
  "payment_method": "wallet"
}
```

### Get Wallet Balance (Merchant)
```http
GET /api/merchant/financial/wallet
Authorization: Bearer {token}
```

---

## Response Helpers

### JavaScript (Axios)
```javascript
// Set auth header
api.defaults.headers.common['Authorization'] = `Bearer ${token}`;

// Handle response
const response = await api.get('/api/admin/wallet');
const data = response.data.data;
```

### PHP (Guzzle)
```php
$client = new \GuzzleHttp\Client(['base_uri' => 'https://api.ofroo.com/']);

$response = $client->get('/api/offers', [
    'headers' => ['Authorization' => 'Bearer ' . $token]
]);

$data = json_decode($response->getBody())->data;
```

---

## Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

---

## Pagination

All list endpoints support pagination:

```
GET /api/admin/merchants?page=1&per_page=15
GET /api/admin/offers?page=2&per_page=50
```

Response includes:
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

---

## Filtering Examples

```javascript
// Filter by status
GET /api/admin/offers?status=active

// Filter by date range
GET /api/admin/orders?from_date=2024-01-01&to_date=2024-12-31

// Search
GET /api/admin/merchants?search=company

// Multiple filters
GET /api/admin/wallet/transactions?wallet_type=merchant&type=credit&from_date=2024-01-01
```

---

## Webhooks (Future)

Configure webhooks in settings for:

- `order.created` - New order placed
- `order.paid` - Payment confirmed
- `order.cancelled` - Order cancelled
- `coupon.activated` - Coupon used
- `withdrawal.requested` - New withdrawal
- `withdrawal.approved` - Withdrawal approved

---

## Quick Tips

1. **Always check `meta.total`** for pagination
2. **Use `per_page=100`** max for exports
3. **Date format**: `YYYY-MM-DD` (ISO 8601)
4. **Amounts**: Always decimal, e.g., `99.99`
5. **IDs**: Returned as strings in resources
