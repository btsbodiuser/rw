<?php
require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('cart'));
    exit;
}

$action   = $_POST['action'] ?? '';
$redirect = (string)($_POST['redirect'] ?? '');
$base     = getBaseUrl();
if ($redirect === '' || (!str_starts_with($redirect, $base) && !str_starts_with($redirect, '/'))) {
    $redirect = url('cart');
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$db = getDB();

function cartLineKey(int $productId, ?int $variantId): string {
    return $productId . '-' . ($variantId ?? 0);
}

switch ($action) {
    case 'add': {
        $productId = (int)($_POST['product_id'] ?? 0);
        $variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
        $qty       = max(1, (int)($_POST['qty'] ?? 1));
        if ($productId <= 0) break;

        $stmt = $db->prepare("SELECT id, slug, name, name_mn, price, image, stock, has_variants FROM products WHERE id = ? AND is_active = 1 AND show_in_store = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) break;

        $price = (float)$product['price'];
        $stock = (int)$product['stock'];
        $colorName = '';
        $sizeName  = '';

        if (!empty($product['has_variants'])) {
            if (!$variantId) break; // variant products require a variant selection
            $vstmt = $db->prepare("
                SELECT v.id, v.stock, v.price_override,
                       co.name_mn AS color_name_mn, co.name AS color_name,
                       sz.name AS size_name
                FROM product_variants v
                LEFT JOIN product_colors co ON co.id = v.color_id
                LEFT JOIN product_sizes sz ON sz.id = v.size_id
                WHERE v.id = ? AND v.product_id = ? AND v.is_active = 1
            ");
            $vstmt->execute([$variantId, $productId]);
            $variant = $vstmt->fetch();
            if (!$variant) break;
            $stock = (int)$variant['stock'];
            if ($variant['price_override'] !== null) $price = (float)$variant['price_override'];
            $colorName = $variant['color_name_mn'] ?: ($variant['color_name'] ?: '');
            $sizeName  = $variant['size_name'] ?: '';
        } else {
            $variantId = null;
        }

        if ($stock <= 0) break; // sold out, nothing to add

        $key = cartLineKey($productId, $variantId);
        $existingQty = (int)($_SESSION['cart'][$key]['qty'] ?? 0);
        $newQty = min($existingQty + $qty, $stock);

        $_SESSION['cart'][$key] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'slug'       => $product['slug'],
            'name'       => $product['name_mn'] ?: $product['name'],
            'image'      => $product['image'],
            'price'      => $price,
            'qty'        => $newQty,
            'color'      => $colorName,
            'size'       => $sizeName,
        ];
        break;
    }

    case 'update': {
        $key = (string)($_POST['key'] ?? '');
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        if (isset($_SESSION['cart'][$key])) {
            // Re-check stock so quantity edits can't exceed what's available
            $line = $_SESSION['cart'][$key];
            $stock = null;
            if ($line['variant_id']) {
                $s = $db->prepare("SELECT stock FROM product_variants WHERE id = ?");
                $s->execute([$line['variant_id']]);
                $stock = $s->fetchColumn();
            } else {
                $s = $db->prepare("SELECT stock FROM products WHERE id = ?");
                $s->execute([$line['product_id']]);
                $stock = $s->fetchColumn();
            }
            if ($stock !== false && $stock !== null) {
                $qty = min($qty, max(1, (int)$stock));
            }
            $_SESSION['cart'][$key]['qty'] = $qty;
        }
        break;
    }

    case 'remove': {
        $key = (string)($_POST['key'] ?? '');
        unset($_SESSION['cart'][$key]);
        break;
    }

    case 'clear': {
        $_SESSION['cart'] = [];
        break;
    }
}

header('Location: ' . $redirect);
exit;
