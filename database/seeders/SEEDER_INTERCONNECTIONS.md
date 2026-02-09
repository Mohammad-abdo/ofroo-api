# Seeder Data Interconnections

## Overview
This document explains how all seeders are interconnected and the relationships between different data models.

## Seeder Execution Order

The seeders are executed in this order (as defined in `DatabaseSeeder.php`):

1. **RoleSeeder** - Creates user roles (admin, merchant, user)
2. **UserSeeder** - Creates users (admins and regular users)
3. **CategorySeeder** - Creates product categories
4. **MerchantSeeder** - Creates merchants and their store locations
5. **OfferSeeder** - Creates offers and coupon templates
6. **OrderSeeder** - Creates orders, payments, and order-based coupons
7. **CartSeeder** - Creates shopping carts
8. **FinancialSeeder** - Creates financial transactions, wallets, expenses, withdrawals
9. **ReviewSeeder** - Creates reviews linked to orders and offers
10. **LoyaltySeeder** - Creates loyalty points transactions
11. **SupportSeeder** - Creates support tickets
12. **CmsSeeder** - Creates CMS content
13. **SettingsSeeder** - Creates system settings
14. **WalletSeeder** - Creates wallet transactions
15. **ActivityLogSeeder** - Creates activity logs

---

## Data Relationships

### 1. Users & Roles
- **Users** → **Roles** (many-to-one)
  - Each user has one role (admin, merchant, user)
  - Created in: `UserSeeder`, `MerchantSeeder`

### 2. Merchants & Store Locations
- **Merchants** → **Users** (one-to-one)
  - Each merchant has one user account
  - Created in: `MerchantSeeder`
  
- **Store Locations** → **Merchants** (many-to-one)
  - Each merchant has at least one store location
  - Created in: `MerchantSeeder`

### 3. Offers & Coupons
- **Offers** → **Merchants** (many-to-one)
  - Each offer belongs to one merchant
  - Created in: `OfferSeeder`
  
- **Offers** → **Categories** (many-to-one)
  - Each offer belongs to one category
  - Created in: `OfferSeeder`
  
- **Offers** → **Store Locations** (many-to-one, nullable)
  - Each offer can be linked to a store location
  - Created in: `OfferSeeder`
  
- **Offers** → **Coupons** (one-to-one, template)
  - Each offer has one template coupon (created by merchant)
  - Template coupon has `order_id = null` (created before orders)
  - Created in: `OfferSeeder`

### 4. Orders & Payments
- **Orders** → **Users** (many-to-one)
  - Each order belongs to one user
  - Created in: `OrderSeeder`
  
- **Orders** → **Merchants** (many-to-one)
  - Each order belongs to one merchant
  - Created in: `OrderSeeder`
  
- **Order Items** → **Orders** (many-to-one)
  - Each order has one or more order items
  - Created in: `OrderSeeder`
  
- **Order Items** → **Offers** (many-to-one)
  - Each order item references one offer
  - Created in: `OrderSeeder`
  
- **Payments** → **Orders** (one-to-one)
  - Each paid order has one payment
  - Created in: `OrderSeeder` (only for paid orders)

### 5. Coupons (Order-Based)
- **Coupons** → **Orders** (many-to-one, nullable)
  - Template coupons: `order_id = null` (created by merchant)
  - Order coupons: `order_id` is set (created when order is paid)
  - Created in: `OfferSeeder` (templates), `OrderSeeder` (order-based)
  
- **Coupons** → **Offers** (many-to-one)
  - Each coupon references one offer
  - Created in: `OfferSeeder`, `OrderSeeder`
  
- **Coupons** → **Users** (many-to-one, nullable)
  - Order-based coupons have a user (the buyer)
  - Template coupons don't have a user
  - Created in: `OrderSeeder`
  
- **Coupons** → **Categories** (many-to-one)
  - Each coupon belongs to one category (same as offer's category)
  - Created in: `OfferSeeder`, `OrderSeeder`

### 6. Reviews
- **Reviews** → **Users** (many-to-one)
  - Each review is written by one user
  - Created in: `ReviewSeeder`
  
- **Reviews** → **Merchants** (many-to-one)
  - Each review is for one merchant
  - Created in: `ReviewSeeder`
  
- **Reviews** → **Orders** (many-to-one, nullable)
  - Reviews can be linked to orders (optional)
  - Created in: `ReviewSeeder`

### 7. Financial Transactions
- **Financial Transactions** → **Merchants** (many-to-one)
  - Each transaction belongs to one merchant
  - Created in: `FinancialSeeder`
  
- **Financial Transactions** → **Orders** (many-to-one, nullable)
  - Transactions can be linked to orders
  - Created in: `FinancialSeeder`
  
- **Financial Transactions** → **Payments** (many-to-one, nullable)
  - Transactions can be linked to payments
  - Created in: `FinancialSeeder`
  
- **Merchant Wallets** → **Merchants** (one-to-one)
  - Each merchant has one wallet
  - Created in: `FinancialSeeder`
  
- **Expenses** → **Merchants** (many-to-one)
  - Each expense belongs to one merchant
  - Created in: `FinancialSeeder`
  
- **Withdrawals** → **Merchants** (many-to-one)
  - Each withdrawal belongs to one merchant
  - Created in: `FinancialSeeder`

### 8. Loyalty Points
- **Loyalty Transactions** → **Users** (many-to-one)
  - Each transaction belongs to one user
  - Created in: `LoyaltySeeder`
  
- **Loyalty Transactions** → **Orders** (many-to-one, nullable)
  - Points can be earned from orders
  - Created in: `LoyaltySeeder`

### 9. Shopping Carts
- **Carts** → **Users** (many-to-one)
  - Each cart belongs to one user
  - Created in: `CartSeeder`
  
- **Cart Items** → **Carts** (many-to-one)
  - Each cart has one or more items
  - Created in: `CartSeeder`
  
- **Cart Items** → **Offers** (many-to-one)
  - Each cart item references one offer
  - Created in: `CartSeeder`

---

## Key Points

### Coupon Types
1. **Template Coupons** (created in `OfferSeeder`):
   - `order_id = null` (created by merchant before orders)
   - `user_id = null` (no buyer yet)
   - `status = 'active'` (template is active)
   - Used as a template to create actual coupons when orders are placed

2. **Order-Based Coupons** (created in `OrderSeeder`):
   - `order_id` is set (created when order is paid)
   - `user_id` is set (the buyer)
   - `status` can be: 'reserved', 'paid', 'activated', 'used', 'expired'
   - Created based on the offer's template coupon

### Data Flow
1. **Merchant creates offer** → Template coupon created (`order_id = null`)
2. **User places order** → Order created
3. **User pays order** → Payment created, order-based coupons created (`order_id` set)
4. **User uses coupon** → Coupon status updated to 'activated' or 'used'
5. **User reviews** → Review created (linked to order/merchant)

### Migration Fix
The migration `2025_11_22_153007_make_order_id_nullable_in_coupons_table` was fixed to handle rollback:
- During rollback, coupons with `order_id = null` are deleted (they're template coupons)
- Then `order_id` can be safely set to NOT NULL

---

## Verification Checklist

After running seeders, verify:

- [ ] All merchants have at least one store location
- [ ] All offers have a template coupon (`order_id = null`)
- [ ] All offers are linked to valid merchants and categories
- [ ] All orders have order items
- [ ] All paid orders have payments
- [ ] All order-based coupons have `order_id` set
- [ ] All order-based coupons have `user_id` set
- [ ] All reviews are linked to valid users and merchants
- [ ] All financial transactions are linked to valid merchants
- [ ] All merchants have wallets
- [ ] All loyalty transactions are linked to valid users

---

**Last Updated**: $(date)

