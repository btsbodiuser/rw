# Runners World — System Overview

**Architecture:** PHP backend (admin panel + REST API) + React/TypeScript frontend (SPA) + MySQL. PWA enabled.

## Modules

### 1. E-Commerce (Online Store)
- Product catalog with categories, shops, search, filters, sorting
- Shopping cart (persistent, localStorage)
- Checkout with delivery/pickup options
- PWA — installable, works offline, native app feel

### 2. POS (Point of Sale)
- In-store sales terminal with barcode scanner
- Cash/card/transfer payment
- VAT calculation (10%)
- Daily sales history and revenue breakdown

### 3. Product Management
- Create/edit products (bilingual: Mongolian + English)
- Ready stock vs Pre-order product types
- Bulk import from Excel/CSV
- Central media library for images
- Stock tracking with low-stock alerts

### 4. Order Management
- 10-step status workflow: pending → confirmed → cargo_shipping → cargo_arrived → ready_pickup → delivering → delivered → picked_up → completed / cancelled
- Online + POS order types
- Delivery vs Pickup fulfillment
- Auto-cancel unpaid orders (cron job)
- Auto-confirm paid orders
- Public order tracking (no login needed)

### 5. Cargo (Pre-order) Management
- Cargo batch lifecycle: open → closed → shipping → arrived
- Batch-level status propagation to linked orders
- Weight-based cargo fee calculation

### 6. Delivery Management
- Driver registry (name, phone, active/inactive)
- Order → Driver assignment
- Delivery status: assigned → picked_up → delivered / failed
- Daily delivery stats dashboard

### 7. Payment
- QPay integration (QR code + bank app deep links)
- Automatic payment verification (polling + webhook)
- Cash and card support
- Bank reconciliation — import bank CSV, auto-match QPay transactions

### 8. Customer Management
- Phone-based registration with SMS OTP
- Customer profiles, saved addresses, order history
- Per-customer analytics (total orders, total spent, avg order value)

### 9. Geography
- Districts & Khoroos (sub-districts) management
- Delivery area: 6 Ulaanbaatar central districts

### 10. Reporting & Analytics
- Sales reports (daily revenue, hourly distribution, POS vs online)
- Product reports (best sellers, category/shop performance, low stock)
- Customer reports (top spenders, new customers)
- Cargo batch reports
- CSV export for all reports

### 11. System Administration
- Role-based access: Super Admin, Admin, POS Cashier
- Dynamic site settings (fees, branding, homepage, contact, integrations)
- Audit log (actions with IP, details, timestamps)
- One-click installation wizard + incremental migrations
- SMS (CallPro) and SMTP email integration

## Database (21 tables)
admins, shops, categories, shop_categories, products, media, cargo_batches, districts, khoroos, orders, order_items, customers, customer_sessions, customer_addresses, otp_codes, settings, audit_log, delivery_drivers, deliveries, bank_statement_imports, bank_transactions, migrations

## API Endpoints
- Products: GET /api/products.php, /api/product.php
- Catalog: GET /api/categories.php, /api/shops.php
- Geography: GET /api/districts.php
- Settings: GET /api/settings.php
- Orders: POST /api/orders.php, GET /api/order-status.php, /api/customer-orders.php
- Addresses: GET/POST/DELETE /api/addresses.php
- Payment: POST /api/qpay.php, GET /api/qpay-callback.php
- Auth: check-phone, send-otp, verify-otp, register, login, me, logout, reset-password

## Deployment
Copy to server: dist/, backend/, .htaccess, og.php
Do NOT copy: src/, public/, node_modules/, package*.json, vite.config.ts, postcss.config.mjs, index.html, .git/
