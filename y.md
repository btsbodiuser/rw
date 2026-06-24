Here's the plan:

Data model

New table product_price_tiers (id, product_id, min_qty, unit_price, with UNIQUE(product_id, min_qty), FK CASCADE). Tiers sorted by min_qty ASC; effective price = highest tier where qty >= min_qty, fallback to products.price.
Backend

Migration 047 to create table.
backend/api/products.php + product.php: include price_tiers array in responses.
backend/pages/product-form.php: Alpine repeater for tier rows (add/remove, min_qty + unit_price), persists on save.
backend/api/orders.php: recompute unit price server-side using tier logic — never trust client price.
Frontend

types.ts: priceTiers?: { minQty: number; unitPrice: number }[] on Product.
helpers.ts: getEffectivePrice(product, qty) and formatPriceTiers(tiers) for display.
ProductCard: small chip like "3+ авбал хямд" if tiers exist (no exact prices, just hint).
ProductDetailPage: show a tier table (qty range → unit price), and the displayed price/total updates live as the user picks quantity. Add-to-cart records the price at that quantity? — see Q1 below.
CartPage + AppContext.cartTotal: per-line price uses getEffectivePrice(product, item.quantity) so changing qty in cart re-prices live.
Three small decisions before I code

Variant price_override interaction: if a product has both per-variant price_override and tier pricing, do tiers ignore the override and apply to base products.price only? Or tiers should also override variant price? My recommendation: tiers apply only to products without variants (or where variant has no override) — keeps the math simple and predictable. Tiers + variant overrides + custom UI is a lot of complexity for an edge case.

Cart re-pricing: when user adds 3 to cart at "3+" tier price, then removes 1 in cart (now qty=2), should price snap back to single-unit price? Recommendation: yes, always recompute from current qty — matches what the user sees on the cart page.

Tier UI on detail page: small inline table ("1 ширхэг — 10,000₮ / 3+ ширхэг — 9,000₮ / 10+ ширхэг — 8,000₮") under the price, or just dynamic price that updates as you click quantity +/−? My take: both — show the static table so the user sees the deal upfront, and the live total reflects current qty.

Confirm these three and I'll start the migration + admin form.