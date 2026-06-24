# QPay V2 — Session / Token Notes

## Current state (as of 2026-05-24)

Working fine. No changes made. Token is fetched fresh on every request.

---

## How the current code works

Every call to `create-invoice` or `check-payment` in [backend/api/qpay.php](../backend/api/qpay.php):

1. Creates a new `QPayClient` instance (`$accessToken = null`)
2. Calls `authenticate()` → hits `POST /v2/auth/token` every single time
3. Uses the token for the actual API call

No caching, no refresh token — but it works because QPay does not enforce strict rate limits.

---

## What could be improved (if ever needed)

### 1. Token caching
QPay tokens are long-lived (the `expires_in` field is a **Unix timestamp**, not seconds duration).  
Cache the token in a temp file (same pattern as Bonum) and reuse it until it expires.

```php
// QPay token response — expires_in is a UNIX TIMESTAMP
{
    "token_type": "bearer",
    "access_token": "eyJ...",
    "expires_in": 1646967792,         // ← Unix timestamp, not seconds!
    "refresh_token": "eyJ...",
    "refresh_expires_in": 1646967792  // ← Unix timestamp, not seconds!
}

// Cache check:
if ($cached['expires_at'] > time() + 60) { /* reuse token */ }

// Save:
'expires_at' => $data['expires_in'] - 60  // use timestamp directly minus buffer
```

### 2. Refresh token
Refresh endpoint: `POST https://merchant.qpay.mn/v2/auth/refresh`  
Header: `Authorization: Bearer {refresh_token}`

Flow (same as Bonum):
```
loadCachedToken()
  ├─ accessToken valid?  → done
  ├─ refreshToken valid? → call /auth/refresh → done
  └─ fallback            → call /auth/token (full login)
```

### 3. Refactor callback
[backend/api/qpay-callback.php](../backend/api/qpay-callback.php) duplicates the auth + payment check logic with raw curl instead of using `QPayClient`.  
Fix: extract `QPayClient` to `backend/includes/QPayClient.php` and require it from both files.

---

## Why it was left as-is

- QPay has been working in production with no rate limit errors
- QPay does not return hard `429` errors like Bonum does
- Every request makes 2 HTTP calls (auth + action) — slightly slower but acceptable at current order volumes
- Fixes above are **nice-to-have**, not urgent

Upgrade if: QPay starts rate-limiting, or order volume grows significantly, or auth logic needs to change.
