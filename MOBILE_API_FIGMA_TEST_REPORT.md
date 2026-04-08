# OFROO Mobile User API — Test Report (Figma Postman Collection)

**Date:** 2026-04-08  
**Collection:** `OFROO - Mobile User API (Figma Design Complete).postman_collection.json`  
**Base URL tested:** `http://127.0.0.1:8000`  
**Method:** Automated run via `api/scripts/run-figma-postman-collection.mjs` (bootstrap user registered, Bearer token applied to protected routes). Requests skipped by design: **Logout**, **Delete Account**, **Upload Avatar** (multipart file).

---

## 1. Summary

| Outcome | Count |
|--------|------:|
| HTTP 200 | 47 |
| HTTP 201 | 1 |
| HTTP 400 | 3 |
| HTTP 404 | 25 |
| HTTP 405 | 1 |
| HTTP 422 | 7 |
| HTTP 429 | 4 |
| HTTP 500 | 3 |
| Client error (empty URL in collection) | 1 |
| Skipped (destructive / file upload) | 3 |

Almost all responses from Laravel routes were **`Content-Type: application/json`** with parseable JSON. The common mobile error **“invalid datatype” / decode failures** isunlikely to be caused by HTML or non-JSON bodies for the routes above; it more often matches **strict model decoding** when the API returns **numeric identifiers as strings** in some fields while other fields use real JSON numbers.

---

## 2. JSON shape: string vs number (likely “invalid datatype” on mobile)

Static analysis flagged responses where fields whose names look like IDs (`*_id`, `id` in nested objects, `order_index`, pagination-style keys) are JSON **strings** containing digits, e.g. `"24"` instead of `24`.

**Observed on successful `200` responses for:**

- **Get All Offers (Home Feed)** and variants (location, nearby)
- **Get All Categories**, **Get Category Details**
- (and related list/detail payloads that share the same serializers)

**Typical pattern:**

- Top-level offer **`id`** is a JSON **number** (e.g. `96`).
- **`merchant_id`**, **`category_id`**, and nested **`coupons[].offer_id`** are often JSON **strings** (e.g. `"24"`, `"96"`).

Clients generated with strict typing (Swift `Codable`, Kotlin serialization, Dart `json_serializable` with `int` fields) will throw **type mismatch** unless models use `String`/`dynamic` or custom converters.

**Recommendation:** Normalize all ID fields to integers (or consistently strings) in API Resources / transformers before shipping mobile builds.

---

## 3. Missing or mismatched routes (HTTP 404)

These requests exist in Postman but returned **`Route not found`** JSON (or equivalent) in this environment:

| Area | Request names (short) |
|------|-------------------------|
| Social auth | Login with Google, Login with Apple, Register with Google, Register with Apple |
| OTP | Resend OTP |
| Password | Request Password Recovery (Email/Phone), Reset Password |
| Content | Get Home Feed (`GET /api/mobile/home`) |
| Cart | Update Cart Item Quantity (`PUT`), Remove from Cart (`DELETE`) — paths used `:id` resolved to `1` for smoke test |
| Payments | Get Available Payment Methods |
| Orders | Get Order Details, Get Order Coupons, Cancel Order |
| Wallet | Get Coupon Details |
| Referral | Get Referral Link, Get Referral Statistics |
| App info | Get App Policy, Get About App, Get Help & Support Info |
| Notifications | Mark Notification as Read, Delete Notification |
| Reviews | Create Review |

**Note:** Some of these paths may exist under different verbs or prefixes in `routes/mobile.php`; the report reflects **exact Postman URLs** as run.

---

## 4. Server errors (HTTP 500)

| Request | Issue (from response body) |
|---------|----------------------------|
| **Search Offers** | SQL error: unknown column **`title_ar`** in `WHERE` |
| **Get WhatsApp Contact** | Undefined method **`OfferController::whatsappContact()`** |
| **Get Ticket Details** | SQL error: unknown column **`ticket_attachments.support_ticket_id`** |

These need backend fixes (schema vs query, controller method, migration / relation column).

---

## 5. Validation / business errors (4xx, not JSON shape bugs)

- **422 — Login with Email / Phone:** Postman sample emails/phones failed validation rules (“selected … is invalid”), not a response-type issue.
- **422 — Checkout variants:** `payment_method` invalid + **`cart_id` required**; cart add had returned **400** (offer/coupon constraints), so checkout bodies did not match a valid cart flow.
- **422 — Create Support Ticket:** validation on required fields for the posted sample body.
- **400 — Add to Cart / Add Coupon:** expected business rules (offer unavailable / coupon not for offer).
- **400 — Redeem Loyalty Points:** “Insufficient points” for test user.
- **429 — OTP endpoints:** **Too Many Attempts** after sequential hammering of throttled routes (`throttle` on `mobile.php`).

---

## 6. Other

- **HTTP 405:** One endpoint returned Method Not Allowed for the verb used in Postman (verify allowed methods in `mobile.php`).
- **Collection hygiene:** Two items named **“New Request”** have **empty URLs**; the runner failed with *Failed to parse URL*. Remove or complete them in Postman.
- **Security:** If you re-run the script, captured responses can contain **Bearer tokens**. Do not commit raw machine output to git.

---

## 7. How to reproduce

```text
cd api
node scripts/run-figma-postman-collection.mjs
```

Ensure `php artisan serve` (or your stack) is up on the chosen base URL and the mobile routes are registered.

---

## 8. Conclusion

- **Wire format:** Responses tested were overwhelmingly **valid JSON** with `application/json`.
- **Mobile “invalid datatype”:** Strong evidence of **inconsistent numeric types** for ID-like fields (numbers in one place, strings in others). Align serialization to one convention.
- **Functional gaps:** Many Figma Postman paths are **404** or **500** on this server; mobile cannot rely on them until routes are implemented and search/ticket/whatsapp issues are fixed.
