<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Сагс';
require_once __DIR__ . '/includes/header.php';

$cartItems = $_SESSION['cart'] ?? [];
$subtotal  = cartTotal();

// ── Cargo fee (preorder items assigned to an open cargo batch) ─────────────
// Mirrors backend/api/orders.php's authoritative per-batch calculation
// (weight_kg * batch's cargo_rate_per_kg * qty) rather than a flat setting,
// since the real fee charged at order time depends on which open batch a
// product is assigned to.
$cargoInfo        = []; // product_id => ['fee' => float, 'hide' => bool]
$visibleCargoFee  = 0.0;
$hasHiddenCargo   = false;
if (!empty($cartItems)) {
    $db = getDB();
    $productIds = array_values(array_unique(array_map(fn($i) => (int)$i['product_id'], $cartItems)));
    $ph = implode(',', array_fill(0, count($productIds), '?'));
    $pStmt = $db->prepare("SELECT id, type, weight_kg, cargo_batch_id, hide_cargo_fee FROM products WHERE id IN ($ph)");
    $pStmt->execute($productIds);
    $productMeta = [];
    foreach ($pStmt->fetchAll() as $row) {
        $productMeta[(int)$row['id']] = $row;
    }

    $batchIds = array_values(array_unique(array_filter(array_column($productMeta, 'cargo_batch_id'))));
    $batchRates = [];
    if (!empty($batchIds)) {
        $bph = implode(',', array_fill(0, count($batchIds), '?'));
        $bStmt = $db->prepare("SELECT id, cargo_rate_per_kg FROM cargo_batches WHERE id IN ($bph) AND status = 'open'");
        $bStmt->execute($batchIds);
        foreach ($bStmt->fetchAll() as $b) {
            $batchRates[(int)$b['id']] = (float)$b['cargo_rate_per_kg'];
        }
    }

    foreach ($cartItems as $item) {
        $pid  = (int)$item['product_id'];
        $qty  = (int)$item['qty'];
        $meta = $productMeta[$pid] ?? null;
        $fee  = 0.0;
        $hide = false;
        if ($meta && $meta['type'] === 'preorder' && (float)$meta['weight_kg'] > 0 && $meta['cargo_batch_id'] && isset($batchRates[(int)$meta['cargo_batch_id']])) {
            $rate = $batchRates[(int)$meta['cargo_batch_id']];
            $fee  = round((float)$meta['weight_kg'] * $rate * $qty, 2);
            $hide = !empty($meta['hide_cargo_fee']);
            if ($hide) {
                $hasHiddenCargo = true;
            } else {
                $visibleCargoFee += $fee;
            }
        }
        $cargoInfo[$pid] = ['fee' => $fee, 'hide' => $hide, 'weight_kg' => $meta ? (float)$meta['weight_kg'] : 0, 'rate' => ($meta && $meta['cargo_batch_id']) ? ($batchRates[(int)$meta['cargo_batch_id']] ?? 0) : 0];
    }
}
$grandTotal = $subtotal + $visibleCargoFee;
?>

        <!-- Page Title -->
        <section class="s-page-title">
            <div class="container">
                <div class="content">
                    <h1 class="title-page">Худалдааны сагс</h1>
                    <ul class="breadcrumbs-page">
                        <li><a href="<?= url() ?>" class="h6 link">Нүүр</a></li>
                        <li class="d-flex"><i class="icon icon-caret-right"></i></li>
                        <li><h6 class="current-page fw-normal">Сагс</h6></li>
                    </ul>
                </div>
            </div>
        </section>
        <!-- /Page Title -->

        <!-- View Cart -->
        <div class="flat-spacing">
            <div class="container">
                <?php if (empty($cartItems)): ?>
                <div class="row justify-content-center">
                    <div class="col-md-6 text-center py-5">
                        <div class="box-text_empty type-shop_cart">
                            <div class="shop-empty_top">
                                <span class="icon" style="font-size:3rem;"><i class="icon icon-shopping-cart-simple"></i></span>
                                <h3 class="text-emp fw-normal mt-3">Таны сагс хоосон байна</h3>
                                <p class="h6 text-main mt-2">Та одоогоор ямар ч бараа нэмээгүй байна</p>
                            </div>
                            <div class="shop-empty_bot mt-4">
                                <a href="<?= url('shop.php') ?>" class="tf-btn animate-btn">
                                    Дэлгүүр үзэх <i class="icon icon-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <!-- Left: Cart Table -->
                    <div class="col-xxl-9 col-xl-8">
                        <form id="cart-form">
                            <table class="tf-table-page-cart">
                                <thead>
                                    <tr>
                                        <th class="h6">Бараа</th>
                                        <th class="h6">Үнэ</th>
                                        <th class="h6">Тоо</th>
                                        <th class="h6">Нийт</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="cart-tbody">
                                    <?php foreach ($cartItems as $item):
                                        $pid       = (int)$item['product_id'];
                                        $vid       = (int)($item['variant_id'] ?? 0);
                                        $qty       = (int)$item['qty'];
                                        $price     = (float)$item['price'];
                                        $lineTotal = $price * $qty;
                                        $name      = htmlspecialchars($item['name_mn'] ?? $item['name'] ?? '');
                                        $slug      = htmlspecialchars($item['slug'] ?? '');
                                        $imgSrc    = htmlspecialchars(fixImageUrl($item['image'] ?? null));
                                        $label     = htmlspecialchars($item['variant_label'] ?? '');
                                        $cargo     = $cargoInfo[$pid] ?? ['fee' => 0, 'hide' => false, 'weight_kg' => 0, 'rate' => 0];
                                    ?>
                                    <tr class="tf-cart_item file-delete"
                                        data-product-id="<?= $pid ?>"
                                        data-variant-id="<?= $vid ?>">
                                        <td>
                                            <div class="cart_product">
                                                <a href="<?= url('product/' . $slug) ?>" class="img-prd">
                                                    <img src="<?= $imgSrc ?>" alt="<?= $name ?>">
                                                </a>
                                                <div class="infor-prd">
                                                    <h6 class="prd_name">
                                                        <a href="<?= url('product/' . $slug) ?>" class="link"><?= $name ?></a>
                                                    </h6>
                                                    <?php if ($label): ?>
                                                    <div class="prd_select text-small"><?= $label ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="cart_price h6 cart-item-price" data-cart-title="Үнэ">
                                            <?= formatPrice($price) ?>
                                            <?php if ($cargo['weight_kg'] > 0 && $cargo['rate'] > 0): ?>
                                            <div class="text-small each-cargo-note" style="color:#ea580c;margin-top:4px;">
                                                <?= $cargo['hide'] ? 'Ачаа ирэхэд төлнө' : '+ ачааны ' . formatPrice($cargo['fee']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cart_quantity" data-cart-title="Тоо">
                                            <div class="wg-quantity">
                                                <button class="btn-quantity minus-quantity" type="button"
                                                    data-product-id="<?= $pid ?>" data-variant-id="<?= $vid ?>">
                                                    <i class="icon-minus fs-14"></i>
                                                </button>
                                                <input class="quantity-product"
                                                    type="number"
                                                    name="qty[<?= $pid ?>_<?= $vid ?>]"
                                                    value="<?= $qty ?>"
                                                    min="1"
                                                    data-product-id="<?= $pid ?>"
                                                    data-variant-id="<?= $vid ?>"
                                                    data-price="<?= $price ?>"
                                                    data-cargo-weight="<?= $cargo['weight_kg'] ?>"
                                                    data-cargo-rate="<?= $cargo['rate'] ?>"
                                                    data-cargo-hide="<?= $cargo['hide'] ? 1 : 0 ?>">
                                                <button class="btn-quantity plus-quantity" type="button"
                                                    data-product-id="<?= $pid ?>" data-variant-id="<?= $vid ?>">
                                                    <i class="icon-plus fs-14"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="cart_total h6 cart-item-subtotal" data-cart-title="Нийт">
                                            <?= formatPrice($lineTotal) ?>
                                        </td>
                                        <td class="cart_remove remove link" data-cart-title="Устгах">
                                            <button type="button" class="btn-cart-remove"
                                                style="border:none;background:none;cursor:pointer;padding:0;"
                                                data-product-id="<?= $pid ?>"
                                                data-variant-id="<?= $vid ?>"
                                                title="Устгах">
                                                <i class="icon icon-close"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                    </div>

                    <!-- Right: Order Summary -->
                    <div class="col-xxl-3 col-xl-4">
                        <div class="fl-sidebar-cart bg-white-smoke sticky-top">
                            <div class="box-order-summary">
                                <h4 class="title fw-semibold">Захиалгын дүн</h4>

                                <div class="subtotal h6 text-button d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold">Нийт дүн</h6>
                                    <span class="total" id="cart-subtotal"><?= formatPrice($subtotal) ?></span>
                                </div>

                                <div class="ship d-flex justify-content-between align-items-center" id="cart-cargo-row" style="padding:12px 0;border-bottom:1px solid #eee;<?= $visibleCargoFee > 0 ? '' : 'display:none;' ?>">
                                    <h6 class="fw-bold" style="color:#ea580c;">Ачааны төлбөр</h6>
                                    <span class="h6" id="cart-cargo-fee" style="color:#ea580c;"><?= formatPrice($visibleCargoFee) ?></span>
                                </div>
                                <?php if ($hasHiddenCargo): ?>
                                <div class="ship d-flex justify-content-between align-items-center" style="padding:12px 0;border-bottom:1px solid #eee;">
                                    <h6 class="fw-bold" style="color:#ea580c;">Ачааны төлбөр *</h6>
                                    <span class="h6 text-main" style="color:#ea580c;">Ачаа ирэхэд төлнө</span>
                                </div>
                                <?php endif; ?>

                                <div class="ship d-flex justify-content-between align-items-center" style="padding:12px 0;border-bottom:1px solid #eee;">
                                    <h6 class="fw-bold">Хүргэлт</h6>
                                    <span class="h6 text-main">Тооцоолох</span>
                                </div>

                                <h5 class="total-order d-flex justify-content-between align-items-center">
                                    <span>Нийт</span>
                                    <span class="total" id="cart-total"><?= formatPrice($grandTotal) ?></span>
                                </h5>

                                <div class="list-ver" style="margin-top:16px;">
                                    <a href="<?= url('checkout.php') ?>" class="tf-btn w-100 animate-btn">
                                        Захиалга хийх <i class="icon icon-arrow-right"></i>
                                    </a>
                                    <a href="<?= url('shop.php') ?>" class="tf-btn btn-white animate-btn animate-dark w-100" style="margin-top:8px;">
                                        Худалдаа үргэлжлүүлэх <i class="icon icon-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- /View Cart -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
    const BASE = <?= json_encode(getBaseUrl()) ?>;

    function fmtPrice(num) {
        return Number(num).toLocaleString('mn-MN') + '₮';
    }

    function rowCargoFee(row) {
        const inp    = row.querySelector('.quantity-product');
        const weight = parseFloat(inp.dataset.cargoWeight) || 0;
        const rate   = parseFloat(inp.dataset.cargoRate) || 0;
        const hide   = inp.dataset.cargoHide === '1';
        const qty    = parseInt(inp.value) || 1;
        if (weight <= 0 || rate <= 0) return { fee: 0, hide: false };
        return { fee: weight * rate * qty, hide };
    }

    function recalcRow(row) {
        const inp   = row.querySelector('.quantity-product');
        const price = parseFloat(inp.dataset.price) || 0;
        const qty   = parseInt(inp.value) || 1;
        const cell  = row.querySelector('.cart-item-subtotal');
        if (cell) cell.textContent = fmtPrice(price * qty);

        const note = row.querySelector('.each-cargo-note');
        if (note) {
            const { fee, hide } = rowCargoFee(row);
            note.textContent = hide ? 'Ачаа ирэхэд төлнө' : ('+ ачааны ' + fmtPrice(fee));
        }
    }

    function recalcTotal() {
        let sum = 0;
        let cargoSum = 0;
        document.querySelectorAll('#cart-tbody tr.tf-cart_item').forEach(row => {
            const inp   = row.querySelector('.quantity-product');
            const price = parseFloat(inp.dataset.price) || 0;
            const qty   = parseInt(inp.value) || 1;
            sum += price * qty;
            const { fee, hide } = rowCargoFee(row);
            if (!hide) cargoSum += fee;
        });
        const sub  = document.getElementById('cart-subtotal');
        const tot  = document.getElementById('cart-total');
        const cargoRow = document.getElementById('cart-cargo-row');
        const cargoEl  = document.getElementById('cart-cargo-fee');
        if (sub) sub.textContent = fmtPrice(sum);
        if (tot) tot.textContent = fmtPrice(sum + cargoSum);
        if (cargoEl) cargoEl.textContent = fmtPrice(cargoSum);
        if (cargoRow) cargoRow.style.display = cargoSum > 0 ? '' : 'none';
    }

    function updateQty(row, newQty) {
        const inp       = row.querySelector('.quantity-product');
        const productId = inp.dataset.productId;
        const variantId = inp.dataset.variantId || 0;

        fetch(BASE + 'cart-action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update', product_id: productId, variant_id: variantId, qty: newQty })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                inp.value = newQty;
                if (data.price !== null && data.price !== undefined) inp.dataset.price = data.price;
                recalcRow(row);
                recalcTotal();
                updateCartBadge(data.count);
            }
        });
    }

    // Qty input change
    document.addEventListener('change', function (e) {
        const inp = e.target.closest('.quantity-product');
        if (!inp) return;
        const row = inp.closest('tr.tf-cart_item');
        if (!row) return;
        const newQty = Math.max(1, parseInt(inp.value) || 1);
        inp.value = newQty;
        updateQty(row, newQty);
    });

    // Plus / Minus buttons
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-quantity');
        if (!btn) return;
        const row = btn.closest('tr.tf-cart_item');
        if (!row) return;
        const inp    = row.querySelector('.quantity-product');
        let   qty    = parseInt(inp.value) || 1;
        const isMinus = btn.classList.contains('minus-quantity');
        if (isMinus) {
            if (qty <= 1) return;
            qty -= 1;
        } else {
            qty += 1;
        }
        updateQty(row, qty);
    });

    // Remove button
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-cart-remove');
        if (!btn) return;
        const row       = btn.closest('tr.tf-cart_item');
        const productId = btn.dataset.productId;
        const variantId = btn.dataset.variantId || 0;

        fetch(BASE + 'cart-action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove', product_id: productId, variant_id: variantId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (row) row.remove();
                recalcTotal();
                updateCartBadge(data.count);

                // Show empty state if no rows left
                const tbody = document.getElementById('cart-tbody');
                if (tbody && tbody.querySelectorAll('tr.tf-cart_item').length === 0) {
                    location.reload();
                }
            }
        });
    });

    function updateCartBadge(count) {
        document.querySelectorAll('#cart-count-badge, #cart-count-toolbar').forEach(el => {
            el.textContent = count;
        });
    }
})();
</script>
