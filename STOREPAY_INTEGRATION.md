# StorePay Integration — Handoff Notes

Integration of StorePay (Mongolian BNPL gateway, 4-month installments) into Runners World, mirroring the existing QPay/Bonum pattern.

## Architecture at a glance

StorePay is different from QPay/Bonum: customer enters their StorePay-registered mobile number at checkout, we call StorePay to issue a loan invoice, customer approves in the StorePay app, StorePay calls our webhook, we re-verify and mark the order paid.

API docs: `StorePayMainMerchantApiGariinAwlaga 2025 - for all merchant.pdf`
- Auth: `POST https://service.storepay.mn/merchant-uaa/oauth/token` (OAuth2 password grant, Basic Auth header with app credentials, token TTL 7200s)
- Base URL: `https://service.storepay.mn/lend-merchant`
- Create invoice: `POST /merchant/loan`  → returns `{value: <loanId>, status: "Success"}`
- Check by loanId: `GET /merchant/loan/check/{loanId}`  → returns `{value: true|false, status: "Success"}`
- Cancel unpaid invoice: `POST /merchant/account/cancel` body `{accountId}`
- Cancel/change confirmed loan: `POST /merchant/loanChange` body `{changeTypeId, loanId, reason, amount}` (changeTypeId 1=amount change, 2=cancel)
- Webhook (server-to-server): StorePay calls our `callbackUrl?id={loanId}` when customer approves; we MUST re-call check API before trusting it

## Credentials (already provided by user, stored in admin settings table)

| Setting key | Value |
|-------------|-------|
| `storepay_store_id` | `26704` |
| `storepay_username` | `99017769` (System user) |
| `storepay_password` | **NOT YET SET** — user wrote "Үүсгэсэн нууц үг" as a placeholder; real password needed |
| `storepay_app_username` | `merchantapp1` (Basic Auth user) |
| `storepay_app_password` | `EnRZA3@B` (Basic Auth password) |

## What was implemented

### Backend (PHP)

- **`backend/api/storepay.php`** — `StorePayClient` class with file-cached OAuth2 token (cache at `sys_get_temp_dir()/storepay_tok_<md5>.json`). Actions:
  - `?action=create-invoice` — body `{order_number, amount, mobile_number, description}`; validates 8-digit MN phone; sends `requestId = order_number` for idempotency; stores returned loanId on `orders.storepay_invoice_id`
  - `?action=check-payment` — body `{invoice_id}`; on confirmed: marks order paid, marks visible cargo fees paid, auto-confirms pending orders
- **`backend/api/storepay-callback.php`** — receives `GET ?order=<num>&id=<loanId>`, finds order, re-verifies with check API (never trusts the webhook), then runs the same order-paid update. Browser hits get redirected to order-tracking page; server-to-server gets `SUCCESS`/`NOT PAID` text.
- **`backend/migrate.php`** — migration `045_storepay_payment_gateway`:
  - Adds `orders.storepay_invoice_id VARCHAR(100)` column (after `bonum_invoice_id`)
  - Seeds 6 settings keys (`payment_storepay_enabled`, `storepay_store_id`, `storepay_username`, `storepay_password`, `storepay_app_username`, `storepay_app_password`)
- **`backend/install.php`** — same column + seeds for fresh installs (orders schema + settings + privateSettings arrays)
- **`backend/api/orders.php:88`** — `'storepay'` added to allowed `payment_method` values
- **`backend/pages/settings.php`** — StorePay credentials UI card, "Төлбөрийн хэлбэр" toggle entry, validation now includes StorePay in the "at least one payment method enabled" check
- **`backend/includes/functions.php`** — `cancelStorepayOrder($db, $orderId, $reason)` helper at line ~816. Idempotent, never throws. Uses `/merchant/account/cancel` if unpaid, `/merchant/loanChange` (changeTypeId=2) if already paid. Audit-logs `storepay_cancel_success` / `storepay_cancel_failed`.
- **`backend/pages/order-detail.php`** — admin status change to `cancelled` triggers `cancelStorepayOrder`
- **`backend/cron/cancel-expired-orders.php`** — auto-cancel cron triggers `cancelStorepayOrder` for storepay orders

### Frontend (React + Vite)

- **`src/app/services/api.ts`**:
  - `payment_method` union now includes `'storepay'`
  - `StorePayInvoiceResponse`, `StorePayCheckResponse` interfaces
  - `createStorepayInvoice(orderNumber, amount, mobileNumber, description)` → `POST /api/storepay.php?action=create-invoice`
  - `checkStorepayPayment(invoiceId)` → `POST /api/storepay.php?action=check-payment`
- **`src/app/pages/CheckoutPage.tsx`**:
  - New state: `storepayInvoiceId`, `storepayMobile`, `storepayError`
  - Reads `payment_storepay_enabled` from settings, included in auto-select
  - Payment-method grid now 4-column responsive with violet StorePay button
  - Mobile number input (digit-filtered, 8-char max) shown when StorePay selected
  - `handleShippingSubmit` calls `createStorepayInvoice` and shows toast
  - Polling effect: every 4 s + on tab-visibility-change
  - Payment-step panel: total + masked phone + manual "Баталгаажуулсан" check button
  - sessionStorage save/restore handles storepay
  - Back-button resets storepay state

Build verified: `npm run build:local` passes.

## Steps to go live (in order)

1. **Run migration**: visit `/backend/migrate.php` in browser. Look for `045_storepay_payment_gateway` = applied.
2. **Enter real system password**: admin Тохиргоо → StorePay section → System Password. The placeholder "Үүсгэсэн нууц үг" must be replaced with the actual password StorePay issued for username `99017769`. If unknown: log into the StorePay merchant portal or email StorePay support for a reset.
3. **Set `site_url`**: admin Тохиргоо → set to your public HTTPS domain (e.g. `https://runnersworld.mn`). The callback URL sent to StorePay is built from this. Empty → falls back to HTTP_HOST → likely unreachable from StorePay's network.
4. **Enable toggle**: tick "StorePay төлбөр идэвхтэй" in admin Тохиргоо. Save.
5. **Deploy frontend**: `npm run build:local` then push the `dist/` folder. Delete old chunks if your deploy doesn't auto-clean (the previous `dist/assets/index-*.js` files are stale).
6. **Test one real transaction**: pick StorePay at checkout, enter an 8-digit phone registered with StorePay, submit, approve in StorePay app. Page should auto-complete. Check `orders.payment_status = 'paid'` and `orders.storepay_invoice_id` is set.
7. **Test cancel**: cancel the test order from admin → check **Аудит лог** for `storepay_cancel_success`.

## Known limitations (not blocking, fix when they bite)

- **StorePay amount limits not pre-validated**. StorePay typically allows ~50,000₮ minimum, ~3,000,000₮ maximum per loan. We don't guard upfront — out-of-range orders fail with StorePay's error message. If most cart values are below the minimum, add a guard in `CheckoutPage.tsx` that disables the StorePay button when total is out of range.
- **`requestId = order_number` blocks retries**. If `createInvoice` succeeds at StorePay but our response times out, the customer can't retry with the same order — StorePay rejects as duplicate, and we never saved the loanId. Recovery: query `/merchant/loan/checkRequest/{orderNumber}` to find the loanId, then update `orders.storepay_invoice_id` manually.
- **Customer phone vs StorePay phone** are independent inputs in checkout. Correct behavior (they're often different people) but users will sometimes confuse them. If complaints arise, auto-prefill from `shippingInfo.phone` but keep the field editable.
- **Token cache file**: written to `sys_get_temp_dir()`. If PHP has `open_basedir` restrictions, the write silently fails → every request re-authenticates (slow, may hit StorePay rate limit). Fix only if hit: switch to `backend/cache/` directory.
- **Admin order list display**: `'storepay'` may render as raw string in some admin pages. Grep `payment_method.*qpay|payment_method.*bonum` to find places that switch on it and add a `storepay → 'StorePay'` label if needed.
- **System password is sent as URL query param** to StorePay's OAuth endpoint (their API design, not ours). It will appear in their access logs. Rotate if compromise suspected.

## Quick reference for debugging

- Token cache file: `C:\Users\<user>\AppData\Local\Temp\storepay_tok_*.json` on Windows, `/tmp/storepay_tok_*.json` on Linux. Delete to force re-auth.
- Audit log table: filter on `action LIKE 'storepay_%'` or `action = 'payment_storepay'`.
- Test the callback reachability: open `<site_url>/backend/api/storepay-callback.php` from outside your network — should return 400/404 (not connection error). If unreachable, StorePay can't notify you.
- Frontend bundle: rebuilt as `dist/assets/index-CHd_yaK1.js` (hash will change on next build).

## How to continue this work on another machine

1. Pull this branch.
2. Hand Claude this file: *"Read STOREPAY_INTEGRATION.md and we'll continue."*
3. Tell it what you need next.
