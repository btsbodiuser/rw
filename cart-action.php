<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? 'add');

// Return mini-cart HTML fragment
if ($action === 'mini') {
    header('Content-Type: text/html');
    $items = $_SESSION['cart'] ?? [];
    if (empty($items)) {
        echo '<div class="box-text_empty type-shop_cart">
            <div class="shop-empty_top">
                <span class="icon"><i class="icon-shopping-cart-simple"></i></span>
                <h3 class="text-emp fw-normal">Сагс хоосон байна</h3>
            </div>
            <div class="shop-empty_bot">
                <a href="' . url('shop.php') . '" class="tf-btn animate-btn">Дэлгүүр үзэх</a>
            </div>
        </div>';
    } else {
        foreach ($items as $item) {
            $img  = htmlspecialchars(fixImageUrl($item['image'] ?? null));
            $name = htmlspecialchars($item['name_mn'] ?? $item['name'] ?? '');
            $slug = htmlspecialchars($item['slug'] ?? '');
            $price = formatPrice((float)$item['price']);
            $qty   = (int)$item['qty'];
            $pid   = (int)$item['product_id'];
            $vid   = (int)($item['variant_id'] ?? 0);
            echo "<div class=\"tf-mini-cart-item\" data-product-id=\"$pid\" data-variant-id=\"$vid\">
                <div class=\"tf-mini-cart-image\">
                    <a href=\"" . url("product/$slug") . "\"><img src=\"$img\" alt=\"$name\"></a>
                </div>
                <div class=\"tf-mini-cart-info\">
                    <h6 class=\"title\"><a href=\"" . url("product/$slug") . "\" class=\"link text-line-clamp-1\">$name</a></h6>
                    <div class=\"d-flex justify-content-between align-items-center\">
                        <div class=\"h6 fw-semibold\">
                            <span class=\"number\">{$qty}x</span>
                            <span class=\"price text-primary\">$price</span>
                        </div>
                        <button class=\"icon link icon-close btn-remove-cart\" style=\"border:none;background:none;cursor:pointer;\"
                            data-product-id=\"$pid\" data-variant-id=\"$vid\"></button>
                    </div>
                </div>
            </div>";
        }
    }
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$productId  = (int)($body['product_id'] ?? 0);
$variantId  = (int)($body['variant_id'] ?? 0);
$qty        = max(1, (int)($body['qty'] ?? 1));

if (!$productId) {
    echo json_encode(['success' => false, 'error' => 'Invalid product']);
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($action === 'add') {
    // Load product from DB
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, slug, name, name_mn, price, image FROM products WHERE id = ? AND show_in_store = 1 LIMIT 1");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch();
        if (!$prod) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            exit;
        }
        $price = (float)$prod['price'];
        // Check variant price override
        if ($variantId) {
            $vs = $db->prepare("SELECT price_override FROM product_variants WHERE id = ? AND product_id = ?");
            $vs->execute([$variantId, $productId]);
            $variant = $vs->fetch();
            if ($variant && $variant['price_override'] !== null) {
                $price = (float)$variant['price_override'];
            }
        }
        // Find existing cart item
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $productId && ($item['variant_id'] ?? 0) == $variantId) {
                $item['qty'] += $qty;
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $_SESSION['cart'][] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'qty'        => $qty,
                'price'      => $price,
                'name'       => $prod['name'],
                'name_mn'    => $prod['name_mn'],
                'slug'       => $prod['slug'],
                'image'      => $prod['image'],
            ];
        }
        echo json_encode(['success' => true, 'count' => cartCount(), 'total' => cartTotal()]);
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
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $productId && ($item['variant_id'] ?? 0) == $variantId) {
            if ($qty <= 0) {
                // will remove below
            } else {
                $item['qty'] = $qty;
            }
            break;
        }
    }
    unset($item);
    if ($qty <= 0) {
        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function($item) use ($productId, $variantId) {
            return !($item['product_id'] == $productId && ($item['variant_id'] ?? 0) == $variantId);
        }));
    }
    echo json_encode(['success' => true, 'count' => cartCount(), 'total' => cartTotal()]);
    exit;
}

if ($action === 'clear') {
    $_SESSION['cart'] = [];
    echo json_encode(['success' => true, 'count' => 0, 'total' => 0]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
