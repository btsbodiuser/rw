# Inventory Arrival System — Development Notes

**Date:** 2026-06-16 / 2026-06-17  
**Project:** GuzeelZgene Admin Panel (PHP/MySQL)  
**Branch:** main

---

## Problem

The original cargo batch system was too rigid: marking a batch "arrived" instantly changed ALL order statuses. In reality:
- Products arrive partially (some colors/sizes missing)
- Quantities received are often less than ordered
- Products from multiple batches can arrive together

---

## Solution: Decouple Physical Arrival from Cargo Batch

Added a separate **Ирэлт бүртгэл** (Inventory Arrival) layer with FIFO order fulfillment.

### Core Design Decisions
- All items in an order must arrive before it is considered ready (no partial delivery per order)
- SMS notification only when a customer's specific items are all confirmed received
- "Cargo Batch" = financial/ordering grouping (unchanged)
- "Arrival" = physical receipt event (new)
- FIFO: oldest orders matched first when inventory arrives

---

## Database Changes (Migration 051)

```sql
-- New batch status between shipping and arrived
ALTER TABLE cargo_batches 
  MODIFY COLUMN status ENUM('open','closed','shipping','receiving','arrived') NOT NULL DEFAULT 'open';

-- Physical receipt events
CREATE TABLE inventory_arrivals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cargo_batch_id INT NULL,
    arrival_date DATE NOT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch (cargo_batch_id),
    INDEX idx_date (arrival_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Line items per arrival
CREATE TABLE inventory_arrival_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    arrival_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NULL,
    quantity_received INT NOT NULL DEFAULT 1,
    quantity_matched INT NOT NULL DEFAULT 0,
    INDEX idx_arrival (arrival_id),
    INDEX idx_product_variant (product_id, variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link fulfilled order_items to the arrival that fulfilled them
ALTER TABLE order_items 
  ADD COLUMN arrival_item_id INT NULL AFTER cargo_status,
  ADD INDEX idx_arrival_item (arrival_item_id);
```

---

## Files Changed

### `backend/migrate.php`
- Added migration `051_inventory_arrivals`

### `backend/includes/functions.php`
- `batchStatusLabel()` — added `receiving` status (orange)
- New function `processArrivalFIFO(PDO $db, int $arrivalId): array`

### `backend/pages/cargo-batch-form.php`
- Status transitions: `shipping → receiving` (not directly to arrived)
- `receiving → arrived` to close out
- SMS endpoints now allow both `receiving` and `arrived` statuses

### `backend/pages/cargo-batches.php`
- Added `arrived_count` subquery
- New `receiving` status icon/color (orange)
- "Ирэлт бүртгэх" button links to arrival form with `?batch_id=X`
- Shows "📦 Ирсэн: X / Y" progress during receiving/arrived

### `backend/pages/inventory-arrivals.php` *(new)*
- Unmatched stock panel (amber, collapsible via Alpine.js)
- Per-arrival list with stats and "тохироогүй" badges

### `backend/pages/inventory-arrival-form.php` *(new)*
- **View mode** (`?id=X`): arrival detail, fulfilled orders list
- **Create mode**: batch-based arrival logging
  - Select batch → auto-load all pending items from that batch
  - Table: Product | Variant | Ordered | Already arrived | Still waiting | **[Qty input]**
  - Qty defaults to "still waiting"; manager sets to 0 for items not arrived
  - "+ Нэмэлт бараа нэмэх" for extra items not in the batch
  - AJAX endpoints: `batch_items`, `search_products`, `get_variants`, `save`
  - Server-side: date validation, deduplication, FIFO processing in transaction

### `backend/pages/batch-handout.php` *(rewritten)*
- Distinguishes `receiving` batches (partial) from `arrived` (complete)
- 3-part progress bar: handed out / ready / waiting
- 4 tabs: Бэлэн / Хүлээгдэж буй / Олгосон / Бүгд
- Waiting orders: yellow border, disabled handout button
- Server-side guard: blocks handout of orders not yet `cargo_arrived`

### `backend/includes/sidebar.php`
- Added `inventory-arrivals` under Борлуулалт group

### `backend/index.php` + `backend/includes/auth.php`
- Registered `inventory-arrivals`, `inventory-arrival-form`, `batch-handout` as protected pages

---

## FIFO Logic (`processArrivalFIFO`)

```
For each arrival item (product+variant):
  1. SELECT pending order_items FOR UPDATE (oldest order first)
  2. For each pending item:
     - If remaining stock < item quantity → continue (skip, try smaller orders)
     - If enough → mark cargo_status='arrived', link arrival_item_id
     - Decrement remaining stock
     - If remaining == 0 → break
  3. After loop: recalcOrderCargoStatus() once per unique order_id
  4. Return list of orders now fully ready (cargo_arrived)
```

`SELECT ... FOR UPDATE` prevents race conditions with concurrent arrivals.

---

## Batch Status Flow

```
open → closed → shipping → receiving → arrived
                              ↑
                    (ирэлт бүртгэх here)
```

- `shipping` → manager marks "Хүлээн авч байна" → status becomes `receiving`
- While `receiving`: managers log arrivals, hand out ready orders, send SMS to ready customers
- `receiving` → "Дуусгах" → status becomes `arrived`

---

## SMS Behavior

- SMS only sent to customers whose orders are fully `cargo_arrived`
- Available for both `receiving` and `arrived` batch statuses
- Waiting orders (items not yet arrived) do NOT receive SMS

---

## Bugs Fixed

| Bug | Fix |
|-----|-----|
| SMS blocked for `receiving` batches | Changed `!== 'arrived'` to `!in_array($status, ['receiving','arrived'])` |
| Race condition in FIFO matching | Added `FOR UPDATE` to pending items SELECT |
| `$bn` undefined when batch_id is NULL | Initialize `$bn = null` before conditional block |
| `break` skipping smaller downstream orders | Changed to `continue`; added `if ($remaining === 0) break` |
| `recalcOrderCargoStatus` called N times in loop | Collect unique order IDs, call once after loop |
| Order links using `order_number` instead of `id` | Fixed query to include `o.id`, use `(int)$fo['id']` in href |
| `formatDate()` showing `00:00` on DATE columns | Replaced with `date('Y-m-d', strtotime(...))` |
| Missing date validation on arrival save | Added `DateTime::createFromFormat('Y-m-d', ...)` check |
| Duplicate product+variant rows double-matching | Server-side deduplication: merge quantities by `product_id_variant_id` key |
| Double-submit on save button | Button permanently disabled on success; re-enabled only on error |
| "хүлээгдэж буй" misleading label for surplus stock | Changed to "тохироогүй" (unmatched) |

---

## Arrival Form — Batch Items Dropdown

Shows batches with status `shipping` or `receiving` only.  
(`open`/`closed` = not shipped yet; `arrived` = fully closed)
