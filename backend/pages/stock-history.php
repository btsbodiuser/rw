<?php
$db = getDB();
$productId = (int)($_GET['product_id'] ?? 0);
if (!$productId) { header('Location: index.php?page=products'); exit; }

$product = $db->prepare("SELECT id, name, barcode, stock, type, has_variants FROM products WHERE id = ? AND is_active = 1");
$product->execute([$productId]);
$product = $product->fetch();
if (!$product) { header('Location: index.php?page=products'); exit; }

// Load variants for the per-variant summary box.
$variants = [];
if ($product['has_variants']) {
    $vStmt = $db->prepare("
        SELECT pv.id, pv.stock,
               TRIM(CONCAT(COALESCE(pc.name_mn, ''),
                           CASE WHEN pc.name_mn IS NOT NULL AND ps.name IS NOT NULL THEN ' / ' ELSE '' END,
                           COALESCE(ps.name, ''))) AS label
        FROM product_variants pv
        LEFT JOIN product_colors pc ON pv.color_id = pc.id
        LEFT JOIN product_sizes ps ON pv.size_id = ps.id
        WHERE pv.product_id = ? AND pv.is_active = 1
        ORDER BY pc.name_mn, ps.name
    ");
    $vStmt->execute([$productId]);
    foreach ($vStmt->fetchAll() as $v) {
        $variants[(int)$v['id']] = [
            'label'    => $v['label'] ?: '—',
            'current'  => $v['stock'] === null ? null : (int)$v['stock'],
            'sold'     => 0,
            'restored' => 0,
        ];
    }
}

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$params = [$productId];
$dateWhere = '';
if ($dateFrom) { $dateWhere .= " AND DATE(sm.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $dateWhere .= " AND DATE(sm.created_at) <= ?"; $params[] = $dateTo; }

// All ledger entries for this product, newest first.
$rows = $db->prepare("
    SELECT
        sm.id, sm.variant_id, sm.delta, sm.balance_after, sm.reason,
        sm.order_id, sm.actor_type, sm.actor_id, sm.note, sm.created_at,
        o.order_number, o.order_type,
        a.name AS actor_name,
        TRIM(CONCAT(COALESCE(pc.name_mn, ''),
                    CASE WHEN pc.name_mn IS NOT NULL AND ps.name IS NOT NULL THEN ' / ' ELSE '' END,
                    COALESCE(ps.name, ''))) AS variant_label
    FROM stock_movements sm
    LEFT JOIN orders o ON o.id = sm.order_id
    LEFT JOIN admins a ON a.id = sm.actor_id AND sm.actor_type = 'admin'
    LEFT JOIN product_variants pv ON pv.id = sm.variant_id
    LEFT JOIN product_colors  pc ON pc.id = pv.color_id
    LEFT JOIN product_sizes   ps ON ps.id = pv.size_id
    WHERE sm.product_id = ? $dateWhere
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT 500
");
$rows->execute($params);
$movements = $rows->fetchAll();

// Aggregate stats: walk backward from current product stock to derive initial.
$initialStock = $product['stock'] === null ? null : (int)$product['stock'];
$totalSold = 0;
$totalRestored = 0;
foreach ($movements as $m) {
    $delta = (int)$m['delta'];
    if ($delta < 0) $totalSold += -$delta; else $totalRestored += $delta;
    if ($initialStock !== null) $initialStock -= $delta;

    // Per-variant tally.
    $vid = (int)($m['variant_id'] ?? 0);
    if ($vid && isset($variants[$vid])) {
        if ($delta < 0) $variants[$vid]['sold'] += -$delta;
        else            $variants[$vid]['restored'] += $delta;
    }
}

// Friendly reason labels.
$reasonLabels = [
    'order_sale'         => ['POS борлуулалт', 'bg-purple-50 text-purple-700'],
    'pos_sale'           => ['POS борлуулалт', 'bg-purple-50 text-purple-700'],
    'order_cancel'       => ['Захиалга цуцлагдсан', 'bg-yellow-50 text-yellow-700'],
    'pos_cancel'         => ['POS цуцлагдсан', 'bg-yellow-50 text-yellow-700'],
    'order_uncancel'     => ['Цуцлал буцаасан', 'bg-orange-50 text-orange-700'],
    'order_edit_restore' => ['Засвар (буцаасан)', 'bg-blue-50 text-blue-700'],
    'order_edit_deduct'  => ['Засвар (хассан)', 'bg-blue-50 text-blue-700'],
    'pos_edit_restore'   => ['POS засвар (буцаасан)', 'bg-blue-50 text-blue-700'],
    'pos_edit_deduct'    => ['POS засвар (хассан)', 'bg-blue-50 text-blue-700'],
    'import'             => ['Импортоос', 'bg-cyan-50 text-cyan-700'],
    'backfill_sale'      => ['Архив (борлуулалт)', 'bg-gray-50 text-gray-600'],
    'backfill_cancel'    => ['Архив (цуцлал)', 'bg-gray-50 text-gray-600'],
    'manual_adjust'      => ['Гар тохиргоо', 'bg-red-50 text-red-700'],
];

// Map order_sale to a customer/online label when not POS.
$labelFor = function ($m) use ($reasonLabels) {
    $reason = $m['reason'];
    if ($reason === 'order_sale' && $m['order_type'] !== 'pos') {
        if ($m['actor_type'] === 'admin') return ['Админ захиалга', 'bg-orange-50 text-orange-700'];
        return ['Онлайн захиалга', 'bg-blue-50 text-blue-700'];
    }
    return $reasonLabels[$reason] ?? [$reason, 'bg-gray-50 text-gray-700'];
};

$pageTitle = 'Нөөцийн түүх — ' . $product['name'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="index.php?page=products" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">Нөөцийн түүх</h1>
            <p class="text-sm text-gray-500"><?= e($product['name']) ?><?= $product['barcode'] ? ' · ' . e($product['barcode']) : '' ?></p>
        </div>
        <div class="ml-auto flex items-start gap-6">
            <?php if ($initialStock !== null && !empty($movements)): ?>
            <div class="text-right">
                <p class="text-xs text-gray-400">Эхний нөөц</p>
                <p class="text-2xl font-bold text-gray-600"><?= number_format($initialStock) ?></p>
            </div>
            <?php endif; ?>
            <div class="text-right">
                <p class="text-xs text-gray-400">Одоогийн нөөц</p>
                <?php if ($product['stock'] === null): ?>
                    <p class="text-2xl font-bold text-gray-400" title="Хязгааргүй">∞</p>
                <?php else: ?>
                    <p class="text-2xl font-bold <?= $product['stock'] == 0 ? 'text-red-600' : ($product['stock'] <= 5 ? 'text-yellow-600' : 'text-green-600') ?>">
                        <?= number_format($product['stock']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form method="get" class="flex flex-wrap items-end gap-3 mb-6 bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
        <input type="hidden" name="page" value="stock-history">
        <input type="hidden" name="product_id" value="<?= $productId ?>">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Эхлэх огноо</label>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Дуусах огноо</label>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Хайх</button>
        <?php if ($dateFrom || $dateTo): ?>
            <a href="index.php?page=stock-history&product_id=<?= $productId ?>" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Цэвэрлэх</a>
        <?php endif; ?>
    </form>

    <?php if ($product['has_variants'] && !empty($variants)): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Хувилбар тус бүрээр</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white border-b border-gray-50">
                    <tr>
                        <th class="px-5 py-2 text-left text-xs font-medium text-gray-500">Хувилбар</th>
                        <th class="px-5 py-2 text-center text-xs font-medium text-gray-500">Заарагдсан</th>
                        <th class="px-5 py-2 text-center text-xs font-medium text-gray-500">Сэргэсэн</th>
                        <th class="px-5 py-2 text-center text-xs font-medium text-gray-500">Одоогийн</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($variants as $v): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2.5 text-gray-700"><?= e($v['label']) ?></td>
                        <td class="px-5 py-2.5 text-center text-red-600"><?= $v['sold'] > 0 ? '-' . number_format($v['sold']) : '0' ?></td>
                        <td class="px-5 py-2.5 text-center text-green-600"><?= $v['restored'] > 0 ? '+' . number_format($v['restored']) : '0' ?></td>
                        <td class="px-5 py-2.5 text-center font-semibold <?= $v['current'] === null ? 'text-gray-400' : ($v['current'] == 0 ? 'text-red-600' : ($v['current'] <= 5 ? 'text-yellow-600' : 'text-gray-900')) ?>">
                            <?= $v['current'] === null ? '∞' : number_format($v['current']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($movements)): ?>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <p>Мэдээлэл байхгүй</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Огноо / Цаг</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Захиалга / Тэмдэглэл</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Шалтгаан</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Өөрчлөлт</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Нөөц</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($movements as $m):
                            [$label, $labelClass] = $labelFor($m);
                            $delta = (int)$m['delta'];
                            $isPOS = $m['order_type'] === 'pos';
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-gray-600">
                                <?= date('Y-m-d', strtotime($m['created_at'])) ?>
                                <span class="text-gray-400 text-xs ml-1"><?= date('H:i', strtotime($m['created_at'])) ?></span>
                            </td>
                            <td class="px-5 py-3">
                                <?php if ($m['order_number']):
                                    $detailPage = $isPOS ? 'pos-history' : 'order-detail';
                                    $detailParam = $isPOS ? 'highlight' : 'id';
                                ?>
                                    <a href="index.php?page=<?= $detailPage ?>&<?= $detailParam ?>=<?= $m['order_id'] ?>"
                                       class="text-blue-600 hover:underline font-medium"><?= e($m['order_number']) ?></a>
                                <?php elseif ($m['note']): ?>
                                    <span class="text-gray-700"><?= e($m['note']) ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                                <?php if ($m['variant_label']): ?>
                                    <span class="ml-1 text-xs text-purple-600">(<?= e($m['variant_label']) ?>)</span>
                                <?php endif; ?>
                                <?php if ($m['actor_name']): ?>
                                    <span class="ml-1 text-xs text-gray-400">· <?= e($m['actor_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $labelClass ?>">
                                    <?= $label ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center font-semibold">
                                <span class="<?= $delta > 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $delta > 0 ? '+' : '' ?><?= $delta ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center text-gray-700 font-medium">
                                <?= $m['balance_after'] !== null ? number_format((int)$m['balance_after']) : '<span class="text-gray-300">—</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 text-xs text-gray-400">
                Нийт <?= count($movements) ?> хөдөлгөөн · Хасагдсан: <?= number_format($totalSold) ?>, Сэргэсэн: <?= number_format($totalRestored) ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
