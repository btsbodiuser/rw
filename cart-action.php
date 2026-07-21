<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');

// Read body once and cache it
$_rawBody = file_get_contents('php://input');
$_jsonBody = json_decode($_rawBody, true) ?? [];
$action = $_GET['action'] ?? ($_jsonBody['action'] ?? 'add');

// Return mini-cart JSON (items HTML + totals)
if ($action === 'mini') {
    $items     = $_SESSION['cart'] ?? [];
    $freeThresh = (float)s('free_delivery_threshold', 0);
    $total     = cartTotal();
    $count     = cartCount();
    $remaining = max(0, $freeThresh - $total);
    $progress  = ($freeThresh > 0 && $total < $freeThresh) ? min(100, round($total / $freeThresh * 100)) : 100;

    ob_start();
    if (empty($items)) {
        echo '<div class="box-text_empty type-shop_cart">
            <div class="shop-empty_top">
                <span class="icon"><i class="icon-shopping-cart-simple"></i></span>
                <h3 class="text-emp fw-normal">Сагс хоосон байна</h3>
                <p class="h6 text-main">Та одоогоор ямар ч бараа нэмээгүй байна</p>
            </div>
            <div class="shop-empty_bot">
                <a href="' . url('shop.php') . '" class="tf-btn animate-btn">Дэлгүүр үзэх</a>
                <a href="' . url() . '" class="tf-btn style-line">Нүүр хуудас</a>
            </div>
        </div>';
    } else {
        foreach ($items as $item) {
            $img   = htmlspecialchars(fixImageUrl($item['image'] ?? null));
            $name  = htmlspecialchars($item['name_mn'] ?? $item['name'] ?? '');
            $slug  = htmlspecialchars($item['slug'] ?? '');
            $price = formatPrice((float)$item['price']);
            $qty   = (int)$item['qty'];
            $pid   = (int)$item['product_id'];
            $vid   = (int)($item['variant_id'] ?? 0);
            $varLabel = htmlspecialchars($item['variant_label'] ?? '');
            $catLabel = htmlspecialchars($item['category_name'] ?? '');
            $catHtml  = $catLabel ? "<div class=\"text-small text-main-2 sub\">$catLabel</div>" : '';
            $varHtml  = $varLabel
                ? "<div class=\"size\"><div class=\"text-small text-main-2 sub\">$varLabel</div></div>"
                : '';
            echo "<div class=\"tf-mini-cart-item file-delete\">
                <div class=\"tf-mini-cart-image\">
                    <a href=\"" . url("product/$slug") . "\"><img src=\"$img\" alt=\"$name\"></a>
                </div>
                <div class=\"tf-mini-cart-info\">
                    $catHtml
                    <h6 class=\"title\">
                        <a href=\"" . url("product/$slug") . "\" class=\"link text-line-clamp-1\">$name</a>
                    </h6>
                    $varHtml
                    <div class=\"d-flex justify-content-between align-items-center\">
                        <div class=\"h6 fw-semibold\">
                            <span class=\"number\">{$qty}x</span>
                            <span class=\"price text-primary tf-mini-card-price\">$price</span>
                        </div>
                        <i class=\"icon link icon-close remove btn-remove-cart\" style=\"cursor:pointer;\"
                           data-product-id=\"$pid\" data-variant-id=\"$vid\"></i>
                    </div>
                </div>
            </div>";
        }
    }
    $itemsHtml = ob_get_clean();

    echo json_encode([
        'items_html' => $itemsHtml,
        'empty'      => empty($items),
        'count'      => $count,
        'total'      => formatPrice($total),
        'remaining'  => formatPrice($remaining),
        'progress'   => $progress,
    ]);
    exit;
}

$body       = $_jsonBody;
$productId  = (int)($body['product_id'] ?? 0);
$variantId  = (int)($body['variant_id'] ?? 0);
$qty        = max(1, (int)($body['qty'] ?? 1));

if (!$productId) {
    echo json_encode(['success' => false, 'error' => 'Invalid product']);
    exit;
}

// Effective per-unit price for a given quantity (highest matching tier wins).
// Tiers must be sorted ASC by min_qty. Mirrors backend/api/orders.php's
// effectiveTierPrice() so cart, checkout and admin orders agree on price.
function effectiveTierPrice(float $basePrice, array $tiers, int $qty): float {
    $price = $basePrice;
    foreach ($tiers as $t) {
        if ($qty >= $t['min_qty']) {
            $price = (float)$t['unit_price'];
        } else {
            break;
        }
    }
    return $price;
}

// Price tiers apply only to products without variants.
function getPriceTiers(PDO $db, int $productId): array {
    $stmt = $db->prepare("SELECT min_qty, unit_price FROM product_price_tiers WHERE product_id = ? ORDER BY min_qty ASC");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($action === 'add') {
    // Load product from DB
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT p.id, p.slug, p.name, p.name_mn, p.price, p.image, p.stock, p.type,
                                     c.name_mn AS cat_mn, c.name AS cat_name
                              FROM products p
                              LEFT JOIN categories c ON c.id = p.category_id
                              WHERE p.id = ? AND p.show_in_store = 1 LIMIT 1");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch();
        if (!$prod) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            exit;
        }
        $price        = (float)$prod['price'];
        $catName      = $prod['cat_mn'] ?: ($prod['cat_name'] ?? '');
        $variantLabel = '';
        $isPreorder   = ($prod['type'] ?? '') === 'preorder';

        // If no variant specified, auto-pick first active variant
        if (!$variantId) {
            $auto = $db->prepare("SELECT id FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
            $auto->execute([$productId]);
            $autoRow = $auto->fetch();
            if ($autoRow) $variantId = (int)$autoRow['id'];
        }

        // Get variant details (price override + stock + label)
        $variantStock = null;
        if ($variantId) {
            $vs = $db->prepare("SELECT pv.price_override, pv.stock,
                                       pc.name AS color_name, pc.name_mn AS color_mn,
                                       ps.name AS size_name
                                FROM product_variants pv
                                LEFT JOIN product_colors pc ON pc.id = pv.color_id
                                LEFT JOIN product_sizes  ps ON ps.id  = pv.size_id
                                WHERE pv.id = ? AND pv.product_id = ?");
            $vs->execute([$variantId, $productId]);
            $variant = $vs->fetch();
            if ($variant) {
                if ($variant['price_override'] !== null) {
                    $price = (float)$variant['price_override'];
                }
                $variantStock = (int)$variant['stock'];
                $colorName = $variant['color_mn'] ?: $variant['color_name'] ?? '';
                $sizeName  = $variant['size_name'] ?? '';
                $parts     = array_filter([$colorName, $sizeName]);
                $variantLabel = implode(' / ', $parts);
            }
        }

        // Bulk/volume pricing — only applies to products without variants.
        $basePrice = $price;
        if (!$variantId) {
            $tiers = getPriceTiers($db, $productId);
            if ($tiers) $price = effectiveTierPrice($basePrice, $tiers, $qty);
        }

        // Stock validation (skip for preorder)
        if (!$isPreorder) {
            $available = $variantStock !== null ? $variantStock : (int)$prod['stock'];
            $inCart    = 0;
            foreach ($_SESSION['cart'] as $ci) {
                if ($ci['product_id'] == $productId && ($ci['variant_id'] ?? 0) == $variantId) {
                    $inCart = (int)$ci['qty'];
                    break;
                }
            }
            if ($available <= 0) {
                echo json_encode(['success' => false, 'error' => 'Энэ бүтээгдэхүүн дууссан байна.']);
                exit;
            }
            if ($inCart + $qty > $available) {
                $canAdd = max(0, $available - $inCart);
                echo json_encode(['success' => false, 'error' => "Үлдэгдэл {$available} ширхэг. Та сагсанд {$inCart} нэмсэн байна, нэмж {$canAdd} л нэмэх боломжтой."]);
                exit;
            }
        }

        // Merge with existing cart item or push new. Bulk-tier price is
        // re-evaluated against the combined quantity, since merging can
        // cross a tier threshold that the just-added quantity alone didn't.
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $productId && ($item['variant_id'] ?? 0) == $variantId) {
                $item['qty'] += $qty;
                if (!$variantId && !empty($tiers)) {
                    $item['price'] = effectiveTierPrice($basePrice, $tiers, $item['qty']);
                }
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $_SESSION['cart'][] = [
                'product_id'    => $productId,
                'variant_id'    => $variantId,
                'qty'           => $qty,
                'price'         => $price,
                'name'          => $prod['name'],
                'name_mn'       => $prod['name_mn'],
                'slug'          => $prod['slug'],
                'image'         => $prod['image'],
                'category_name' => $catName,
                'variant_label' => $variantLabel,
            ];
        }
        echo json_encode([
            'success'      => true,
            'count'        => cartCount(),
            'total'        => cartTotal(),
            'product_name' => $prod['name_mn'] ?: $prod['name'],
            'product_img'  => fixImageUrl($prod['image']),
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'remove') {
    $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function($item) use ($productId, $variantId) {
        return !($item['product_id'] == $productId && ($item['variant_id'] ?? 0) == $variantId);
    }));
    echo json_encode(['success' => true, 'count' => cartCount(), 'total' => cartTotal()]);
    exit;
}

if ($action === 'update') {
    // Cap qty at available stock (unless preorder)
    if ($qty > 0) {
        try {
            $db = getDB();
            $p  = $db->prepare("SELECT stock, type, price FROM products WHERE id = ? LIMIT 1");
            $p->execute([$productId]);
            $prow = $p->fetch();
            if ($prow && $prow['type'] !== 'preorder') {
                $available = (int)$prow['stock'];
                if ($variantId) {
                    $vp = $db->prepare("SELECT stock FROM product_variants WHERE id = ? AND product_id = ?");
                    $vp->execute([$variantId, $productId]);
                    $vrow = $vp->fetch();
                    if ($vrow) $available = (int)$vrow['stock'];
                }
                if ($qty > $available) {
                    echo json_encode(['success' => false, 'error' => "Үлдэгдэл {$available} ширхэг л байна."]);
                    exit;
                }
            }
            // Bulk/volume pricing — only applies to products without variants.
            if ($prow && !$variantId) {
                $tiers = getPriceTiers($db, $productId);
                if ($tiers) {
                    foreach ($_SESSION['cart'] as &$item) {
                        if ($item['product_id'] == $productId && ($item['variant_id'] ?? 0) == $variantId) {
                            $item['price'] = effectiveTierPrice((float)$prow['price'], $tiers, $qty);
                            break;
                        }
                    }
                    unset($item);
                }
            }
        } catch (Throwable $e) {}
    }

    $updatedPrice = null;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $productId && ($item['variant_id'] ?? 0) == $variantId) {
            if ($qty > 0) $item['qty'] = $qty;
            $updatedPrice = (float)$item['price'];
            break;
        }
    }
    unset($item);
    if ($qty <= 0) {
        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function($item) use ($productId, $variantId) {
            return !($item['product_id'] == $productId && ($item['variant_id'] ?? 0) == $variantId);
        }));
    }
    echo json_encode(['success' => true, 'count' => cartCount(), 'total' => cartTotal(), 'price' => $updatedPrice]);
    exit;
}

if ($action === 'clear') {
    $_SESSION['cart'] = [];
    echo json_encode(['success' => true, 'count' => 0, 'total' => 0]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
