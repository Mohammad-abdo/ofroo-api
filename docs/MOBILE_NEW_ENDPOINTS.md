# OFROO Mobile API — New & Updated Endpoints

All endpoints documented here are **additive** (new) or **explicitly updated on
request**. Every previously existing response contract is preserved — no
response shape of a pre-existing endpoint was changed unless that endpoint was
explicitly listed as "Fix" in the original task.

Base URL: `https://<host>/api/mobile`
All authenticated endpoints use `Authorization: Bearer <sanctum_token>`.
All dates are ISO-8601 UTC (e.g. `2026-04-19T12:00:00+00:00`).
All `image` fields are fully-qualified absolute URLs (or empty string).

---

## 1) Checkout → QR Generation + Coupon Order

### `POST /api/mobile/checkout/coupons` *(new, auth required)*
Also aliased as `POST /api/mobile/orders/checkout/coupons` for consistency with
the existing orders namespace.

**Request body**
```json
{
  "user_id": 12,
  "coupon_ids": [101, 102, 103],
  "payment_method": "cash",   // optional: cash|card|none (default: cash)
  "notes": "Deliver after 5pm" // optional
}
```

**Constraints**
- `user_id` must match the authenticated user (otherwise `403`).
- All `coupon_ids` must belong to the **same merchant** (otherwise `422`).
- No coupon can be expired (`422`).
- `card` payments are blocked when the electronic-payments feature flag is off.

**Success — `201 Created`**
```json
{
  "message": "Order created successfully",
  "data": {
    "order": {
      "id": 987,
      "user_id": 12,
      "merchant_id": 5,
      "total_amount": 135.00,
      "payment_method": "cash",
      "payment_status": "pending",
      "created_at": "2026-04-19T12:30:00+00:00"
    },
    "coupons": [
      {
        "entitlement_id": 5543,
        "coupon": { /* standard CouponResource shape */ },
        "redeem_token": "W-ABCDEFG...",
        "qr_code_base64": "data:image/png;base64,iVBORw0KGgo...",
        "usage_limit": 1,
        "remaining_uses": 1,
        "status": "pending"
      }
    ],
    "payment": {
      "method": "cash",
      "status": "pending",
      "amount": 135.00,
      "currency": "SAR"
    },
    "qr_code": {
      "payload": "{\"type\":\"order\",\"order_id\":987,\"user_id\":12,\"token\":\"W-...\"}",
      "base64": "data:image/png;base64,iVBORw0KGgo...",
      "format": "png"
    },
    "shareable_link": "https://app.ofroo.com/orders/987",
    "deep_link": "ofroo://orders/987?token=W-ABCDEFG..."
  }
}
```

**Error examples**
- `403` – `user_id` mismatch or electronic payments disabled.
- `422` – validation failure, coupon expired, or multi-merchant cart.

### `GET /api/mobile/orders?coupon_status=...` *(updated, auth required)*
`index` is **unchanged** when `coupon_status` is omitted (backward compatible).
When provided, `coupon_status` filters the paginated result to orders that
contain at least one `CouponEntitlement` in the given bucket:

| `coupon_status` | Arabic | Matches `couponEntitlements` where |
|---|---|---|
| `valid` | صالح | `status = active` AND `times_used = 0` AND `remaining > 0` |
| `expired` | منتهي | `status IN (expired, exhausted)` |
| `inactive` | غير مفعل | `status = pending` |
| `activated` | تم تفعيله | `status = active` AND `times_used > 0` |

Response shape is identical to the existing paginated `OrderResource` list
(`data`, `meta.current_page`, `meta.last_page`, `meta.per_page`, `meta.total`).

---

## 2) Share Offer With Friends

### `GET /api/mobile/offers/{offer_id}/share` *(new, public)*
Optional query: `?language=ar|en`.

**Response**
```json
{
  "data": {
    "offer": {
      "id": 77,
      "title": "خصم 30% على البيتزا",
      "title_ar": "خصم 30% على البيتزا",
      "title_en": "30% off on pizza",
      "description": "عرض ساري حتى نهاية الشهر",
      "price": 45.0,
      "discount": 30.0,
      "image": "https://cdn.example.com/storage/offers/77/cover.jpg",
      "images": ["https://cdn.example.com/storage/offers/77/cover.jpg"],
      "merchant": {
        "id": 5,
        "company_name": "Pizza Palace",
        "logo_url": "https://cdn.example.com/storage/merchants/5/logo.png"
      }
    },
    "share": {
      "text": "شاهد هذا العرض على OFROO: خصم 30% على البيتزا",
      "app_link": "ofroo://offers/77",
      "deep_link": "ofroo://offers/77",
      "web_link": "https://app.ofroo.com/offers/77",
      "universal_link": "https://app.ofroo.com/offers/77",
      "platforms": [
        { "platform": "whatsapp", "share_url": "https://wa.me/?text=..." },
        { "platform": "facebook", "share_url": "https://www.facebook.com/sharer/sharer.php?u=..." },
        { "platform": "snapchat", "share_url": "https://www.snapchat.com/scan?attachmentUrl=..." },
        { "platform": "tiktok",   "share_url": "https://www.tiktok.com/upload?lang=en&url=..." }
      ]
    }
  }
}
```

---

## 3) Share App via Social Media

### `GET /api/mobile/app/share` *(new, public)*
Reads admin-managed keys from the `settings` table:
`play_store_url`, `app_store_url`, `app_landing_url`,
`app_share_message_ar`, `app_share_message_en`.

**Response**
```json
{
  "data": {
    "app_link": "https://app.ofroo.com",
    "android_url": "https://play.google.com/store/apps/details?id=com.ofroo.app",
    "ios_url": "https://apps.apple.com/app/id000000000",
    "message_ar": "حمّل تطبيق OFROO للحصول على أفضل العروض والكوبونات",
    "message_en": "Download the OFROO app for the best offers and coupons",
    "platforms": [
      { "platform": "whatsapp", "share_url": "https://wa.me/?text=...",                                  "icon": "https://host/storage/images/share/whatsapp.png" },
      { "platform": "facebook", "share_url": "https://www.facebook.com/sharer/sharer.php?u=...",          "icon": "https://host/storage/images/share/facebook.png" },
      { "platform": "snapchat", "share_url": "https://www.snapchat.com/scan?attachmentUrl=...",           "icon": "https://host/storage/images/share/snapchat.png" },
      { "platform": "tiktok",   "share_url": "https://www.tiktok.com/upload?lang=en&url=...",             "icon": "https://host/storage/images/share/tiktok.png" }
    ]
  }
}
```

---

## 4) Help & Support

### `GET /api/mobile/support` *(new, public)*
Reads from the `settings` table — keys:
`support_email`, `support_whatsapp` (fallback: `support_phone`).

**Response**
```json
{
  "data": {
    "email": "support@ofroo.com",
    "whatsapp_number": "+966555555555",
    "whatsapp_link": "https://wa.me/966555555555"
  }
}
```

Fields never `null` — an empty string is returned when a key is not yet
populated by the admin so the mobile app can render safely.

---

## 5) About App & Social Media Links

### `GET /api/mobile/app/about` *(new, public)*
Optional query: `?language=ar|en`.
Reads from `settings` keys: `app_description_ar`, `app_description_en`
(fallbacks: legacy `static_about_*`). Social URLs are read from the
existing `social_links` table (managed by the admin dashboard).

**Response**
```json
{
  "data": {
    "description": "...",
    "description_ar": "...",
    "description_en": "...",
    "app_version": "1.0.0",
    "social_links": [
      { "platform": "facebook",  "url": "https://facebook.com/ofroo",  "icon": "https://host/storage/images/social/facebook.png" },
      { "platform": "instagram", "url": "https://instagram.com/ofroo", "icon": "https://host/storage/images/social/instagram.png" },
      { "platform": "tiktok",    "url": "https://tiktok.com/@ofroo",   "icon": "https://host/storage/images/social/tiktok.png" }
    ]
  }
}
```

Only platforms with a non-empty `url` are returned.

---

## 6) Privacy Policy (Mobile Only)

### `GET /api/mobile/app/policy` *(new, public — mobile prefix only)*
Optional query: `?language=ar|en`.
Backed by a **new** table `app_policies` (migration
`2026_04_19_120000_create_app_policies_table`).

| Column          | Type           |
|-----------------|----------------|
| `id`            | `bigint` PK    |
| `title_ar`      | `string` null  |
| `title_en`      | `string` null  |
| `description_ar`| `text` null    |
| `description_en`| `text` null    |
| `order_index`   | `uint` default 0 |
| `is_active`     | `bool` default true |

Transparent fallback: if the table is empty, the endpoint returns a single
item built from the legacy `static_privacy_ar` / `static_privacy_en`
settings — so mobile clients never see an empty response during migration.

**Response**
```json
{
  "data": [
    {
      "id": 1,
      "title": "سياسة الخصوصية",
      "title_ar": "سياسة الخصوصية",
      "title_en": "Privacy Policy",
      "description": "نص السياسة...",
      "description_ar": "نص السياسة...",
      "description_en": "Policy text..."
    }
  ]
}
```

---

## 7) Delete Account (Mobile Only)

### `DELETE /api/mobile/user/account` *(updated, auth required)*
Already existed with mandatory `password`. Updated so `password` is now
**optional** — the Sanctum bearer token is accepted as proof of ownership,
enabling a one-tap delete UX. When `password` is provided it is still
verified (backward compatible).

Behaviour: anonymises PII (`name`, `email`, `phone`), deletes avatar,
revokes all tokens, and deletes the User row (soft-delete applies if the
model uses `SoftDeletes`).

**Request body** (both accepted)
```json
{}
```
```json
{ "password": "current-password" }
```

**Response**
```json
{ "message": "Account deleted successfully" }
```

**Errors**
- `422` – `password` provided and incorrect (or validation error).

---

## 8) Mobile Search (Fixed)

### `GET /api/mobile/search?q=...` *(updated, auth required)*
Previously returned offers only. Per the task spec the mobile-only route
now returns a unified, paginated feed across offers + coupons + categories.
**The non-mobile `/api/search` web endpoint is unchanged** — both the route
and its `OfferController@search` method still return `OfferResource` data.

Supported params:
- `q` or `search` (string)
- `page`, `per_page` (1–50, default 15)

Arabic input works as-is — the SQL `LIKE` operand is kept verbatim (UTF-8),
no `strtolower`/transliteration is applied, so Arabic diacritics match.

**Response**
```json
{
  "data": [
    { "id": 77,  "title": "خصم 30% على البيتزا", "image": "https://host/.../cover.jpg", "type": "offer" },
    { "id": 210, "title": "كوبون بيتزا",         "image": "https://host/.../coupon.jpg","type": "coupon" },
    { "id": 4,   "title": "المطاعم",             "image": "https://host/.../cat.jpg",   "type": "category" }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 3,
    "q": "بيتزا",
    "counts": { "offers": 1, "coupons": 1, "categories": 1 }
  }
}
```

Each item is strictly `{ id: int, title: string, image: string, type: "offer"|"coupon"|"category" }`.
Offers respect the existing `mobilePubliclyAvailable` scope so unavailable
offers are never returned to mobile users.

---

## 9) Offer Push Notifications (Fixed)

The FCM payload builder in `App\Services\NotificationService` now always
carries the three fields the mobile app needs for a rich notification:

- `offer_id` *(string, as required by FCM data payload spec)*
- `title`
- `image` — full absolute URL (via `ApiMediaUrl::publicAbsolute()`)

Additional data keys included for Flutter routing:
- `click_action = FLUTTER_NOTIFICATION_CLICK`
- `route = /offers/{id}`
- `type = offer`

Android / iOS notification objects carry `image` in both
`notification.image` and `apns.fcm_options.image` so the image is
displayed before the app even opens.

**Public API (server-side usage)**
```php
app(\App\Services\NotificationService::class)
    ->sendOfferPushNotification($offer, $userIds, $customTitle = null, $customBody = null);
```

Requires `FCM_SERVER_KEY` in env or `services.fcm.server_key` config.
When the key is missing the service logs and no-ops (never throws),
so creating/updating an offer is never blocked by push delivery.

---

---

## 10) Admin Dashboard Endpoints (CMS for the mobile content)

All admin routes below are protected by `auth:sanctum` + `admin` middleware
and live under the existing `/api/admin` prefix.

### 10.1 Privacy Policy sections — `GET/POST/PUT/DELETE /api/admin/app-policies`

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/admin/app-policies?q=&is_active=&per_page=` | Paginated list (admin sees ALL, including inactive) |
| `GET` | `/api/admin/app-policies/{id}` | Single policy section |
| `POST` | `/api/admin/app-policies` | Create |
| `PUT` | `/api/admin/app-policies/{id}` | Update |
| `DELETE` | `/api/admin/app-policies/{id}` | Delete |
| `PUT` | `/api/admin/app-policies/order` | Bulk reorder |

**Payload (`store`/`update`)**
```json
{
  "title_ar": "سياسة الخصوصية",
  "title_en": "Privacy Policy",
  "description_ar": "نص السياسة...",
  "description_en": "Policy text...",
  "order_index": 0,
  "is_active": true
}
```

**Item shape returned**
```json
{
  "id": 1,
  "title_ar": "...", "title_en": "...",
  "description_ar": "...", "description_en": "...",
  "order_index": 0,
  "is_active": true,
  "created_at": "2026-04-19T12:00:00+00:00",
  "updated_at": "2026-04-19T12:00:00+00:00"
}
```

**Reorder body** (`PUT /api/admin/app-policies/order`)
```json
{
  "order": [
    { "id": 3, "order_index": 0 },
    { "id": 7, "order_index": 1 },
    { "id": 5, "order_index": 2 }
  ]
}
```

### 10.2 App settings — `GET/PUT /api/admin/settings`

`GET /api/admin/settings` is unchanged (returns all `settings` + overlaid
`app_coupon_settings` + `social_links`).

`PUT /api/admin/settings` now additionally accepts the following keys
(all optional, persisted to the `settings` table, read by the mobile app):

| Key | Mobile endpoint that reads it |
|---|---|
| `support_email` | `GET /api/mobile/support` |
| `support_whatsapp` | `GET /api/mobile/support` |
| `support_phone` *(fallback)* | `GET /api/mobile/support` |
| `app_description_ar` / `app_description_en` | `GET /api/mobile/app/about` |
| `app_version` | `GET /api/mobile/app/about` |
| `play_store_url` | `GET /api/mobile/app/share` |
| `app_store_url` | `GET /api/mobile/app/share` |
| `app_landing_url` | `GET /api/mobile/app/share` + share-offer |
| `app_share_message_ar` / `app_share_message_en` | `GET /api/mobile/app/share` |
| `app_deep_link_scheme` *(default `ofroo`)* | share-offer + checkout/coupons |
| `app_universal_link_base` | `GET /api/mobile/offers/{id}/share` |
| `currency` *(default `SAR`)* | `POST /api/mobile/checkout/coupons` response |

Social-platform URLs (`facebook_url`, `instagram_url`, `tiktok_url`,
`snapchat_url`, `whatsapp_url`, `telegram_url`, `twitter_url`,
`youtube_url`) continue to flow through the existing `social_links`
table — nothing new required there, they already appear in `GET /api/mobile/app/about`.

**Example `PUT /api/admin/settings` (flat object form)**
```json
{
  "support_email": "support@ofroo.com",
  "support_whatsapp": "+966555555555",
  "app_description_ar": "تطبيق OFROO للعروض والكوبونات",
  "app_description_en": "OFROO — deals and coupons app",
  "play_store_url": "https://play.google.com/store/apps/details?id=com.ofroo.app",
  "app_store_url": "https://apps.apple.com/app/id000000000",
  "app_landing_url": "https://app.ofroo.com",
  "app_share_message_ar": "حمّل تطبيق OFROO للحصول على أفضل العروض والكوبونات",
  "app_share_message_en": "Download the OFROO app for the best offers and coupons",
  "app_deep_link_scheme": "ofroo",
  "currency": "SAR"
}
```

**Alternative `PUT /api/admin/settings` (array form — already supported)**
```json
{
  "settings": [
    { "key": "support_email", "value": "support@ofroo.com" },
    { "key": "currency", "value": "SAR" }
  ]
}
```

### 10.3 Orders — `GET /api/admin/orders?coupon_status=...`

`GET /api/admin/orders` gains the same `coupon_status` filter as mobile
(`valid | expired | inactive | activated`). Response shape is unchanged
when the param is omitted (fully backward compatible).

| Filter | Matches at least one `CouponEntitlement` where |
|---|---|
| `valid` | `status=active` AND `times_used=0` AND `remaining>0` |
| `expired` | `status IN (expired, exhausted)` |
| `inactive` | `status=pending` |
| `activated` | `status=active` AND `times_used>0` |

### 10.4 Push notifications (server-side usage)

Admin-side action buttons that trigger "notify users of new offer" should
call the new service method instead of building FCM payloads by hand:

```php
app(\App\Services\NotificationService::class)
    ->sendOfferPushNotification($offer, $userIds);
```

FCM credentials are read from `config('services.fcm.server_key')` or
`FCM_SERVER_KEY` env. Missing credentials are logged and the call no-ops,
so offer creation is never blocked by transient push issues.

---

## Summary of Files Changed

**New**
- `database/migrations/2026_04_19_120000_create_app_policies_table.php`
- `app/Models/AppPolicy.php`
- `app/Services/QrCodeService.php`
- `app/Http/Controllers/Api/AppContentController.php` *(mobile)*
- `app/Http/Controllers/Api/AdminAppPolicyController.php` *(admin CRUD for policies)*
- `docs/MOBILE_NEW_ENDPOINTS.md` *(this file)*

**Updated (backward-compatible unless listed in task 7/8/9)**
- `app/Http/Controllers/Api/OrderController.php`
  - Added `checkoutCoupons()`.
  - Added optional `coupon_status` filter to `index()`.
- `app/Http/Controllers/Api/OfferController.php`
  - Added `searchMobile()`; original `search()` untouched.
- `app/Http/Controllers/Api/UserController.php`
  - `deleteAccount()` now accepts optional password (explicit task requirement).
- `app/Http/Controllers/Api/AdminController.php`
  - `updateSettings()` validator extended with new mobile-CMS keys.
  - `getOrders()` gains optional `coupon_status` filter.
- `app/Services/NotificationService.php`
  - Real FCM HTTP payload + `sendOfferPushNotification()`.
- `routes/mobile.php`
  - Wired the new mobile routes. Mobile `/search` now points at `searchMobile`.
- `routes/api.php`
  - Wired `/api/admin/app-policies` CRUD + reorder.
  - `/api/search` (web) remains on the original `search` method.
