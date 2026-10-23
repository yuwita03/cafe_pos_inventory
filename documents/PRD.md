# PRD — Café POS V1

**Status:** Approved Scope — In Development

## 1. Overview

A web-based Café POS system for a single café, combining menu management, inventory, purchasing, cashier orders, kitchen operations, and QRIS payments.

**Goals:**

- Customers can order and pay through table QR.
- Cashiers can handle dine-in and takeaway orders.
- Paid orders are processed through one kitchen queue.
- Inventory is tracked automatically through sales and purchasing.
- Owners can monitor basic café operations.

**V1 Constraints:** Single branch, IDR currency, online-only, responsive web application.

## 2. User Roles


| Role     | Access                                             |
| -------- | -------------------------------------------------- |
| Owner    | Full management access                             |
| Cashier  | Create orders and handle payments                  |
| Kitchen  | View and process orders                            |
| Customer | Browse, order, pay, and track orders without login |


V1 uses fixed staff roles, not dynamic RBAC.

## 3. Core Features

### F01 — Menu Management

- Manage categories, products, variants, and options.
- Variants are optional for customers (e.g., Hot/Iced).
- Products without visible variants are selected directly.
- Support product pricing and availability.
- Preserve historical order prices.

### F02 — Inventory Management

Two inventory consumption methods:

**Recipe-Based**

- Products consume ingredients according to recipes.
- Different variants may have different recipes.
- Options may add ingredient consumption.
- Example: Americano consumes coffee beans.

**Direct Stock**

- Products consume stock directly.
- No recipe required.
- Example: Selling 2 croissants deducts 2 pcs.

Both methods support stock reservations, movements, manual adjustments, and low-stock warnings.

### F03 — Suppliers &amp; Purchasing

- Manage suppliers.
- Create, edit, and cancel purchase drafts.
- Record purchased items, quantities, and costs.
- Receiving increases inventory.
- Prevent duplicate receiving.
- V1 supports full receiving only.

### F04 — Cashier POS

- Create dine-in orders with tables.
- Create takeaway orders.
- Select products, variants, options, and quantities.
- Support cash and QRIS payments.
- Only paid orders enter the kitchen.

### F05 — Kitchen Display

- Display paid orders from all channels.
- Support order statuses:

`queued → preparing → ready → completed`

- Deduct reserved inventory once when preparation begins.

### F06 — Table QR Ordering

- Each table has a QR link to the customer menu.
- Customers order without accounts.
- System validates the table, order, and stock.
- Checkout reserves inventory and generates dynamic QRIS.
- Verified payments send orders to the kitchen.
- Customers track orders through a private link.
- Failed or expired unpaid orders release reservations.

### F07 — Staff Management

- Staff login/logout.
- Owner manages staff accounts.
- Fixed roles: Owner, Cashier, Kitchen.
- Backend authorization protects restricted operations.

### F08 — Dashboard

- Confirmed sales.
- Paid order counts.
- Best-selling products.
- Low-stock warnings.

## 4. Business Rules

### Products

- Customer-facing variants are optional.
- Products without visible variants use a default internal variant.
- Each sellable variant uses either Recipe-Based or Direct-Stock inventory.
- Prices are calculated on the server.
- Orders preserve product and pricing details at purchase time.

### Inventory

- Available stock = physical stock − active reservations.
- Stock is reserved before QRIS payment.
- Paid orders retain reservations until preparation.
- Physical stock decreases when preparation starts.
- Failed or expired unpaid orders release reservations.
- Stock cannot become negative or be deducted twice.
- Purchasing receiving increases stock only once.
- Manual stock adjustments require a reason.

### Payments

- Cash payments require cashier confirmation.
- QRIS payments require verified provider confirmation.
- Unpaid orders never enter the kitchen.
- Duplicate callbacks must not process payments or stock twice.
- Late or inconsistent payments require manual review.
- Refunds are handled externally, not through the POS.

### Security

- Staff management requires login and authorization.
- Customer ordering does not require login.
- Order tracking uses a private token.
- Sensitive operations are validated server-side.

## 5. Main Workflows

**Customer Order**

Scan Table QR → Select Menu → Reserve Stock → Pay QRIS → Kitchen Queue → Preparing (Deduct Stock) → Ready → Completed

**Cashier Order**

Select Menu → Choose Dine-in/Takeaway → Confirm Payment → Reserve Stock → Kitchen Queue → Preparing → Completed

**Purchasing**

Create Draft → Receive Items → Increase Stock → Record Movement

## 6. Out of Scope

- Homemade pastry production.
- Online pickup and delivery.
- Customer accounts and loyalty.
- Multi-branch management.
- Dynamic RBAC.
- Partial purchasing receiving.
- Automated refunds or in-app refund management.
- Advanced accounting and analytics.
- Offline POS.

**Pending decisions:** Taxes, service charges, receipt requirements, and production deployment details.

---

**V1 Success Criteria:** Customers and cashiers can complete paid orders, kitchen staff can process them, inventory remains accurate, purchasing updates stock correctly, and staff access is protected.
