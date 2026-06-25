<?php
// Expects: $p — product row with: id, slug, name, name_mn, type, price, original_price, image, stock, category_name
$_img     = fixImageUrl($p['image'] ?? null);
$_name    = htmlspecialchars($p['name_mn'] ?: ($p['name'] ?? ''));
$_slug    = htmlspecialchars($p['slug'] ?? '');
$_price   = (float)($p['price'] ?? 0);
$_oldP    = !empty($p['original_price']) ? (float)$p['original_price'] : null;
$_stock   = $p['stock'] ?? null;
$_type    = $p['type'] ?? 'ready';
$_pUrl    = url('product/' . ($p['slug'] ?? ''));
$_pId     = (int)($p['id'] ?? 0);
$_disc    = ($_oldP && $_oldP > $_price) ? round((1 - $_price / $_oldP) * 100) : 0;
?>
<div class="card-product">
    <div class="card-product_wrapper style-line-radius">
        <a href="<?= $_pUrl ?>" class="product-img">
            <img class="lazyload img-product" src="<?= $_img ?>" data-src="<?= $_img ?>" alt="<?= $_name ?>">
            <img class="lazyload img-hover" src="<?= $_img ?>" data-src="<?= $_img ?>" alt="<?= $_name ?>">
        </a>
        <?php if ($_disc > 0 || $_type === 'preorder' || $_stock === 0): ?>
        <ul class="product-badge_list">
            <?php if ($_disc > 0): ?><li class="product-badge_item h6 on-sale">-<?= $_disc ?>%</li><?php endif; ?>
            <?php if ($_type === 'preorder'): ?><li class="product-badge_item h6" style="background:#f97316;">Pre-order</li><?php endif; ?>
            <?php if ($_stock === 0): ?><li class="product-badge_item h6 sold-out">Sold out</li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <ul class="product-action_list">
            <li>
                <a href="#shoppingCart" data-bs-toggle="offcanvas"
                   class="hover-tooltip tooltip-left box-icon btn-add-to-cart"
                   data-product-id="<?= $_pId ?>" data-variant-id="0"
                   <?= ($_stock === 0) ? 'style="pointer-events:none;opacity:.5;"' : '' ?>>
                    <span class="icon icon-shopping-cart-simple"></span>
                    <span class="tooltip">Сагсанд нэмэх</span>
                </a>
            </li>
            <li>
                <a href="<?= $_pUrl ?>" class="hover-tooltip tooltip-left box-icon">
                    <span class="icon icon-view"></span>
                    <span class="tooltip">Дэлгэрэнгүй</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="card-product_info">
        <a href="<?= $_pUrl ?>" class="name-product h4 link"><?= $_name ?></a>
        <div class="price-wrap">
            <?php if ($_oldP && $_oldP > $_price): ?>
                <span class="price-old h6 fw-normal"><?= formatPrice($_oldP) ?></span>
                <span class="price-new h6"><?= formatPrice($_price) ?></span>
            <?php else: ?>
                <span class="price-new h6"><?= formatPrice($_price) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
