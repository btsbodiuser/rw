<?php
$pageTitle = 'Ирэлтийн бүртгэл';
$db = getDB();

// Unmatched stock: items that arrived but were not matched to any order
// Grouped by product+variant, summed across all arrivals
$unmatchedStock = $db->query("
    SELECT
        p.id   AS product_id,
        p.name_mn AS product_name,
        pv.id  AS variant_id,
        pc.name_mn AS color_name,
        ps.name    AS size_name,
        SUM(iai.quantity_received - iai.quantity_matched) AS unmatched_qty,
        MAX(ia.arrival_date) AS last_arrival_date
    FROM inventory_arrival_items iai
    JOIN inventory_arrivals ia ON ia.id = iai.arrival_id
    JOIN products p ON p.id = iai.product_id
    LEFT JOIN product_variants pv ON pv.id  = iai.variant_id
    LEFT JOIN product_colors   pc ON pc.id  = pv.color_id
    LEFT JOIN product_sizes    ps ON ps.id  = pv.size_id
    WHERE iai.quantity_received > iai.quantity_matched
    GROUP BY iai.product_id, iai.variant_id
    HAVING SUM(iai.quantity_received - iai.quantity_matched) > 0
    ORDER BY unmatched_qty DESC, p.name_mn ASC
")->fetchAll();

$totalUnmatchedUnits = array_sum(array_column($unmatchedStock, 'unmatched_qty'));

// Arrivals list
$arrivals = $db->query("
    SELECT ia.*,
        cb.name as batch_name,
        adm.name as created_by_name,
        (SELECT COUNT(*) FROM inventory_arrival_items WHERE arrival_id = ia.id) as item_count,
        (SELECT SUM(quantity_received) FROM inventory_arrival_items WHERE arrival_id = ia.id) as total_units,
        (SELECT SUM(quantity_matched) FROM inventory_arrival_items WHERE arrival_id = ia.id) as matched_units,
        (SELECT COUNT(DISTINCT oi.order_id)
         FROM order_items oi
         JOIN inventory_arrival_items iai ON iai.id = oi.arrival_item_id
         WHERE iai.arrival_id = ia.id) as fulfilled_orders
    FROM inventory_arrivals ia
    LEFT JOIN cargo_batches cb ON cb.id = ia.cargo_batch_id
    LEFT JOIN admins adm ON adm.id = ia.created_by
    ORDER BY ia.arrival_date DESC, ia.created_at DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <p class="text-sm text-gray-500"><?= count($arrivals) ?> ирэлт</p>
    </div>
    <a href="index.php?page=inventory-arrival-form" class="inline-flex items-center gap-2 px-4 py-2.5 bg-orange-500 text-white rounded-lg hover:bg-orange-600 text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Ирэлт бүртгэх
    </a>
</div>

<?php if (!empty($unmatchedStock)): ?>
<!-- ── Unmatched Stock Panel ── -->
<div class="bg-amber-50 border border-amber-200 rounded-xl mb-6" x-data="{ open: true }">
    <button type="button" @click="open = !open"
            class="w-full flex items-center justify-between px-5 py-4 text-left">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div>
                <p class="font-semibold text-amber-900">Тохироогүй бараа (агуулахын үлдэгдэл)</p>
                <p class="text-sm text-amber-700">
                    <?= count($unmatchedStock) ?> нэр төрөл · нийт
                    <strong><?= (int)$totalUnmatchedUnits ?> ширхэг</strong>
                    ирсэн боловч захиалгад тохироогүй байна
                </p>
            </div>
        </div>
        <svg class="w-5 h-5 text-amber-500 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-transition x-cloak class="border-t border-amber-200">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-amber-100/60 text-amber-800 text-xs uppercase tracking-wide">
                        <th class="text-left px-5 py-2.5">Бүтээгдэхүүн</th>
                        <th class="text-left px-3 py-2.5">Өнгө</th>
                        <th class="text-left px-3 py-2.5">Хэмжээ</th>
                        <th class="text-right px-3 py-2.5">Тохироогүй ширхэг</th>
                        <th class="text-right px-5 py-2.5">Сүүлд ирсэн</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-100">
                    <?php foreach ($unmatchedStock as $row): ?>
                    <tr class="hover:bg-amber-50/60">
                        <td class="px-5 py-3 font-medium text-gray-900">
                            <a href="index.php?page=product-form&id=<?= (int)$row['product_id'] ?>"
                               class="hover:text-blue-600 hover:underline">
                                <?= e($row['product_name']) ?>
                            </a>
                        </td>
                        <td class="px-3 py-3 text-gray-600">
                            <?= $row['color_name'] ? e($row['color_name']) : '<span class="text-gray-300">—</span>' ?>
                        </td>
                        <td class="px-3 py-3 text-gray-600">
                            <?= $row['size_name'] ? e($row['size_name']) : '<span class="text-gray-300">—</span>' ?>
                        </td>
                        <td class="px-3 py-3 text-right">
                            <span class="inline-flex items-center justify-center min-w-[2rem] px-2.5 py-1 bg-amber-200 text-amber-900 rounded-full text-xs font-bold">
                                <?= (int)$row['unmatched_qty'] ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right text-gray-400 text-xs">
                            <?= date('Y-m-d', strtotime($row['last_arrival_date'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-amber-100/40">
                        <td colspan="3" class="px-5 py-2.5 text-xs font-semibold text-amber-800">Нийт</td>
                        <td class="px-3 py-2.5 text-right text-xs font-bold text-amber-900"><?= (int)$totalUnmatchedUnits ?> ширхэг</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="px-5 py-3 bg-amber-50/50 border-t border-amber-100 text-xs text-amber-700">
            Эдгээр бараа захиалга байхгүй байсан тул тохироогүй үлдсэн. Шинэ захиалга орж ирэх үед ирэлт дахин бүртгэхэд автоматаар тохируулагдана.
        </div>
    </div>
</div>
<?php endif; ?>

<div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-800">
    <strong>Хэрхэн ажилладаг:</strong> Солонгосоос бараа ирэх бүрт ирэлт бүртгэнэ → бүтээгдэхүүн тус бүрийн ирсэн тоо хэмжээг оруулна → систем хамгийн эртний захиалгыг эхэлж биелүүлнэ (FIFO).
</div>

<?php if (empty($arrivals)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
    Ирэлтийн бүртгэл байхгүй байна. Солонгосоос бараа ирэх үед ирэлт бүртгэнэ үү.
</div>
<?php else: ?>
<div class="space-y-3">
    <?php foreach ($arrivals as $arrival):
        $unmatched = (int)$arrival['total_units'] - (int)$arrival['matched_units'];
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-orange-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H7m12 0l-4-4m4 4l-4 4M3 12a9 9 0 1118 0 9 9 0 01-18 0z"/></svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-gray-900"><?= date('Y-m-d', strtotime($arrival['arrival_date'])) ?></span>
                        <?php if ($arrival['batch_name']): ?>
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"><?= e($arrival['batch_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-1 text-sm text-gray-500">
                        <span>Нэр төрөл: <strong><?= (int)$arrival['item_count'] ?></strong></span>
                        <span>Нийт ширхэг: <strong><?= (int)$arrival['total_units'] ?></strong></span>
                        <span>Тохирсон: <strong class="text-green-600"><?= (int)$arrival['matched_units'] ?></strong></span>
                        <?php if ($unmatched > 0): ?>
                            <span>Тохироогүй: <strong class="text-amber-600"><?= $unmatched ?></strong></span>
                        <?php endif; ?>
                        <span>Захиалга биелэв: <strong class="text-green-600"><?= (int)$arrival['fulfilled_orders'] ?></strong></span>
                        <?php if ($arrival['created_by_name']): ?>
                            <span class="text-gray-400">· <?= e($arrival['created_by_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($arrival['notes']): ?>
                        <p class="text-xs text-gray-400 mt-1"><?= e($arrival['notes']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($unmatched > 0): ?>
                    <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-medium"><?= $unmatched ?> тохироогүй</span>
                <?php else: ?>
                    <span class="px-2.5 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Бүгд тохирсон</span>
                <?php endif; ?>
                <a href="index.php?page=inventory-arrival-form&id=<?= $arrival['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600" title="Дэлгэрэнгүй">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
