# Runners World — System Introduction

---

## What is Runners World?

**Runners World** is a full-featured e-commerce platform specializing in Brand products for the Mongolian market. It provides an end-to-end solution covering online shopping, in-store point-of-sale, order management, cargo logistics, delivery tracking, and payment processing — all within a single integrated system.

🌐 **Live URL:** [https://runnersworld.mn](https://runnersworld.mn)

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | React 19 + TypeScript (Vite) |
| **UI Framework** | Tailwind CSS + shadcn/ui (48 components) + Material UI |
| **Backend** | PHP 8 (REST API + Admin Panel) |
| **Database** | MySQL (21 tables, utf8mb4, PDO) |
| **Payment** | QPay (QR code + bank deep links) |
| **SMS** | CallPro OTP verification |
| **PWA** | Installable, offline-ready, standalone mode |
| **Timezone** | Asia/Ulaanbaatar (UTC+8) |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                    USERS                            │
│  Customers │ Admins │ POS Cashiers │ Delivery Drivers│
└──────┬──────────┬───────────┬────────────┬──────────┘
       │          │           │            │
       ▼          ▼           ▼            ▼
┌─────────────┐ ┌──────────────┐ ┌────────────────┐
│  React SPA  │ │  PHP Admin   │ │  Driver Panel  │
│  (PWA)      │ │  Panel       │ │  (Token-based) │
│  Customer   │ │  /backend/   │ │  /driver/:token│
│  Storefront │ │              │ │                │
└──────┬──────┘ └──────┬───────┘ └───────┬────────┘
       │               │                 │
       ▼               ▼                 ▼
┌─────────────────────────────────────────────────────┐
│              PHP REST API (/api/)                    │
│  Products · Orders · Auth · Payment · Settings      │
└──────────────────────┬──────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────┐
│                   MySQL Database                     │
│   21 tables · PDO · utf8mb4 · Asia/Ulaanbaatar     │
└─────────────────────────────────────────────────────┘
```

---

## Core Modules

### 1. 🛒 Online Store (Customer-Facing)
- Product catalog with categories, shops, search, and filters
- Persistent shopping cart (localStorage)
- Checkout with delivery or pickup options
- Phone-based registration with SMS OTP
- Saved addresses and order history
- PWA — installable on mobile, works offline

### 2. 💳 Point of Sale (POS)
- In-store sales terminal with barcode scanning
- Cash, card, and bank transfer payments
- 10% VAT calculation
- Daily sales history and revenue breakdown

### 3. 📦 Order Management
- **10-step status workflow:**
  ```
  pending → confirmed → cargo_shipping → cargo_arrived →
  ready_pickup → delivering → delivered → picked_up → completed
                                                    ↘ cancelled
  ```
- Online + POS order types
- Delivery vs Pickup fulfillment
- Public order tracking (no login required)
- Auto-cancel unpaid orders (cron job)
- Auto-confirm paid orders

### 4. 🚢 Cargo Management (Pre-orders from World)
- Cargo batch lifecycle: `open → closed → shipping → arrived`
- Batch-level status propagation to linked orders
- Weight-based cargo fee calculation

### 5. 🚗 Delivery Management
- Driver registry with phone and status
- Order → Driver assignment
- Delivery tracking: `assigned → picked_up → delivered / failed`
- Dedicated driver panel (token-based access, no login)
- Daily delivery dashboard

### 6. 💰 Payment Integration
- **QPay** — QR code generation + bank app deep links
- Automatic payment verification (polling + webhook callback)
- Cash and card support for POS
- Bank reconciliation — CSV import, auto-match QPay transactions

### 7. 📊 Reporting & Analytics
- Sales reports: daily revenue, hourly distribution, POS vs online
- Product reports: best sellers, category/shop performance, low stock alerts
- Customer reports: top spenders, new customer trends
- Cargo batch reports
- CSV export for all reports

### 8. ⚙️ System Administration
- **Roles:** Super Admin, Admin, POS Cashier
- Dynamic site settings (fees, branding, homepage, contact info, integrations)
- Audit log with IP, action details, and timestamps
- One-click installation wizard + incremental database migrations

---

## Customer-Facing Pages (React SPA)

| Page | Path | Description |
|------|------|-------------|
| Home | `/` | Featured products, categories, promotions |
| All Items | `/all-items` | Full product catalog with filters |
| Category | `/category/:id` | Products filtered by category |
| Shop | `/shop/:id` | Products filtered by shop |
| Product Detail | `/product/:id` | Full product info, images, add to cart |
| Cart | `/cart` | Shopping cart review |
| Checkout | `/checkout` | Order placement (🔒 login required) |
| Login | `/login` | Phone + OTP authentication |
| Profile | `/profile` | Account, addresses, order history (🔒) |
| Order Tracking | `/order-tracking` | Public order status lookup |
| Product Entry | `/product-entry` | Vendor product submission (🔒) |
| Driver Panel | `/driver/:token` | Delivery driver interface |
| Contact | `/contact` | Store contact information |
| Delivery Info | `/delivery` | Delivery areas and policies |
| FAQ | `/faq` | Frequently asked questions |

---

## Admin Panel (PHP Backend)

| Module | Features |
|--------|----------|
| **Dashboard** | Sales overview, order stats, quick actions |
| **Products** | CRUD, bulk import (Excel/CSV), media library, bilingual (MN/EN) |
| **Orders** | Status management, detail view, creation, bulk import |
| **Customers** | Profiles, analytics (total orders, spent, avg value) |
| **Shops** | Multi-shop management |
| **Categories** | Hierarchical category tree |
| **Deliveries** | Driver management, assignment, batch handout |
| **Cargo Batches** | Pre-order batch lifecycle management |
| **POS** | Point-of-sale terminal + sales history |
| **Reports** | Sales, products, customers, cargo — with CSV export |
| **Districts** | Ulaanbaatar delivery zones (6 central districts + khoroos) |
| **Media** | Centralized image/file library |
| **Users** | Admin user management with roles |
| **Settings** | Site-wide configuration |
| **Audit Log** | System activity tracking |
| **Reconciliation** | Bank statement import and payment matching |

---

## Database Schema (21 Tables)

```
├── Users & Auth
│   ├── admins              — Admin users (Super Admin, Admin, Cashier)
│   ├── customers           — Customer accounts (phone-based)
│   ├── customer_sessions   — Active login sessions
│   ├── customer_addresses  — Saved delivery addresses
│   └── otp_codes           — SMS verification codes
│
├── Catalog
│   ├── shops               — Seller/brand shops
│   ├── categories          — Product categories
│   ├── shop_categories     — Shop ↔ Category mapping
│   ├── products            — Product listings (bilingual)
│   └── media               — Uploaded images and files
│
├── Orders & Delivery
│   ├── orders              — All orders (online + POS)
│   ├── order_items         — Line items per order
│   ├── delivery_drivers    — Registered drivers
│   └── deliveries          — Delivery assignments and tracking
│
├── Cargo
│   └── cargo_batches       — Pre-order batch management
│
├── Geography
│   ├── districts           — Ulaanbaatar districts
│   └── khoroos             — Sub-districts
│
├── Finance
│   ├── bank_statement_imports — Uploaded bank CSVs
│   └── bank_transactions     — Parsed transactions for matching
│
└── System
    ├── settings            — Dynamic key-value configuration
    ├── audit_log           — Action audit trail
    └── migrations          — Database version tracking
```

---

## API Endpoints

| Group | Endpoints |
|-------|-----------|
| **Products** | `GET /api/products.php` · `GET /api/product.php` · `GET /api/my-products.php` |
| **Catalog** | `GET /api/categories.php` · `GET /api/shops.php` |
| **Geography** | `GET /api/districts.php` |
| **Settings** | `GET /api/settings.php` |
| **Orders** | `POST /api/orders.php` · `GET /api/order-status.php` · `GET /api/customer-orders.php` |
| **Addresses** | `GET/POST/DELETE /api/addresses.php` |
| **Payment** | `POST /api/qpay.php` · `GET /api/qpay-callback.php` |
| **Delivery** | `GET /api/driver-deliveries.php` |
| **Media** | `POST /api/media-upload.php` |
| **Product Entry** | `POST /api/product-entry.php` |
| **Auth** | `check-phone` · `send-otp` · `verify-otp` · `register` · `login` · `me` · `logout` · `reset-password` |

---

## Key Business Features

- **Bilingual Support** — Product names and descriptions in Mongolian and English
- **Pre-order Model** — Customers order Brand products before they arrive via cargo shipments
- **Ready Stock** — Immediately available products sold via storefront or POS
- **Delivery Coverage** — 6 central districts of Ulaanbaatar with khoroo-level granularity
- **QPay Integration** — Mongolia's leading digital payment platform
- **SMS OTP** — Secure phone-based authentication via CallPro
- **PWA** — Mobile-first, installable, offline-capable progressive web app

---

## Deployment

| Include | Exclude |
|---------|---------|
| `dist/` (built frontend) | `src/` (source code) |
| `backend/` (PHP) | `node_modules/` |
| `.htaccess` | `package*.json` |
| `og.php` (social sharing) | `vite.config.ts` |
| | `index.html` (dev only) |

---

*Runners World — Brand Products, Delivered to Mongolia* 🇰🇷→🇲🇳
