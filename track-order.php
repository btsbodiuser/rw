<?php
require_once __DIR__ . '/includes/config.php';

$page_title  = 'Захиалга шалгах — ' . s('site_name', 'Runners World');

$trackNumber = trim((string)($_GET['order_number'] ?? ''));
$trackOrder  = null;
$trackError  = '';
if ($trackNumber !== '') {
    $r = apiCall('GET', 'order-status.php?order_number=' . urlencode($trackNumber));
    if ($r['code'] === 200 && !empty($r['data']['order'])) {
        $trackOrder = $r['data']['order'];
    } else {
        $trackError = $r['data']['error'] ?? 'Захиалга олдсонгүй.';
    }
}

$extraStyles = <<<CSS
        /* ── Track Order ─────────────────────────────────── */
        .rw-track-card {
            background: var(--color-white, #fff);
            border: 1px solid var(--color-gray-100, #e5e5e5);
            border-radius: 12px;
            padding: 32px;
        }
        .rw-track-hint { color: var(--color-body, #737373); font-size: 14px; }
        .rw-track-input-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .rw-track-input-row .rbt-contact-input-field {
            flex: 1; min-width: 220px; height: 48px;
            border: 1px solid var(--color-gray-200, #e5e5e5);
            border-radius: 6px; padding: 0 14px;
        }
        .rw-track-input-row .rbt-btn { white-space: nowrap; }
        .rw-track-alert { border-radius: 6px; padding: 12px 16px; font-size: 14px; }
        .rw-track-alert-error { background: #fdecea; color: #b3261e; border: 1px solid #f5c2c0; }
        .rw-track-result-head {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 20px; border-bottom: 1px solid var(--color-gray-100, #e5e5e5);
            gap: 16px; flex-wrap: wrap;
        }
        .rw-track-label { font-size: 12px; color: var(--color-body, #737373); margin: 0; text-transform: uppercase; letter-spacing: 0.4px; }
        .rw-track-value { font-size: 14px; color: var(--color-heading, #1a1a1a); font-weight: 600; }
        .rw-track-status-badge { padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; background: var(--color-gray-light, #f5f5f5); color: var(--color-heading, #1a1a1a); }
        .rw-track-status-delivered,
        .rw-track-status-picked_up,
        .rw-track-status-completed { background: #e6f4ea; color: #137333; }
        .rw-track-status-cancelled { background: #fdecea; color: #b3261e; }
        .rw-track-status-delivering,
        .rw-track-status-cargo_shipping,
        .rw-track-status-cargo_arrived,
        .rw-track-status-ready_pickup { background: #fff4e5; color: #a26200; }
        .rw-track-flow { display: flex; justify-content: space-between; gap: 12px; margin: 32px 0 8px; position: relative; flex-wrap: wrap; }
        .rw-track-step { flex: 1; min-width: 90px; text-align: center; position: relative; }
        .rw-track-step-dot {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--color-gray-light, #f5f5f5); color: var(--color-body, #737373);
            font-weight: 700; display: inline-flex; align-items: center; justify-content: center;
            border: 2px solid var(--color-gray-200, #e5e5e5); position: relative; z-index: 2;
        }
        .rw-track-step.is-done .rw-track-step-dot { background: var(--color-primary, #215ADA); color: #fff; border-color: var(--color-primary, #215ADA); }
        .rw-track-step.is-now  .rw-track-step-dot { box-shadow: 0 0 0 4px rgba(33, 90, 218, 0.15); }
        .rw-track-step:not(:last-child)::after {
            content: ''; position: absolute; top: 18px; left: 50%; right: -50%; height: 2px;
            background: var(--color-gray-200, #e5e5e5); z-index: 1;
        }
        .rw-track-step.is-done:not(:last-child)::after { background: var(--color-primary, #215ADA); }
        .rw-track-step-label { margin-top: 8px; font-size: 12px; color: var(--color-body, #737373); }
        .rw-track-step.is-done .rw-track-step-label { color: var(--color-heading, #1a1a1a); font-weight: 600; }
        .rw-track-meta {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px; margin-top: 32px; padding: 20px 0;
            border-top: 1px solid var(--color-gray-100, #e5e5e5);
            border-bottom: 1px solid var(--color-gray-100, #e5e5e5);
        }
        .rw-track-meta-item { display: flex; flex-direction: column; gap: 4px; }
        .rw-track-items { margin-top: 24px; }
        .rw-track-item { display: flex; gap: 16px; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--color-gray-100, #e5e5e5); }
        .rw-track-item:last-child { border-bottom: 0; }
        .rw-track-item-img { width: 64px; height: 64px; object-fit: cover; border-radius: 6px; background: var(--color-gray-light, #f5f5f5); }
        .rw-track-item-img-placeholder { display: inline-flex; align-items: center; justify-content: center; color: var(--color-body, #737373); font-size: 22px; }
        .rw-track-item-info { flex: 1; min-width: 0; }
        .rw-track-item-name { margin: 0; font-weight: 600; color: var(--color-heading, #1a1a1a); }
        .rw-track-item-variant { margin: 2px 0 0; font-size: 12px; color: var(--color-body, #737373); }
        .rw-track-item-qty { margin: 2px 0 0; font-size: 13px; color: var(--color-body, #737373); }
        .rw-track-item-total { font-weight: 700; color: var(--color-heading, #1a1a1a); }
CSS;

require __DIR__ . '/includes/header.php';
?>

    <!-- TRACK ORDER BREADCRUMB -->
    <div class="rbt-breadcrumb-two rbt-bg-color-white pt--40 pb--20">
        <div class="container">
            <div class="rbt-breadcrumb-inner text-left">
                <ul class="rbt-breadcrumb-page-list justify-content-start mt--0">
                    <li class="rbt-breadcrumb-item"><a href="<?= h($urlHome) ?>">Нүүр</a></li>
                    <li class="rbt-breadcrumb-item"><span class="mr--8 ml--8">/</span></li>
                    <li class="rbt-breadcrumb-item active">Захиалга шалгах</li>
                </ul>
                <h1 class="title h3 mt--10">Захиалга шалгах</h1>
            </div>
        </div>
    </div>

    <!-- TRACK ORDER MAIN -->
    <div class="rbt-component-area rbt-section-gapBottom rbt-bg-color-gray-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-8">
                    <div class="rw-track-card">
                        <p class="rw-track-hint mb--16">Захиалгын дугаараа оруулж хүргэлт болон захиалгын статусаа шалгана уу.</p>
                        <form method="get" action="<?= h(url('track-order')) ?>" class="rw-track-form">
                            <div class="rw-track-input-row">
                                <input type="text" name="order_number" class="rbt-contact-input-field"
                                    placeholder="Жишээ: RW12345678"
                                    value="<?= h($trackNumber) ?>"
                                    required
                                    autocomplete="off">
                                <button type="submit" class="rbt-btn rbt-btn-md">
                                    <i class="fa-regular fa-magnifying-glass mr--4"></i> Шалгах
                                </button>
                            </div>
                        </form>

                        <?php if ($trackError): ?>
                        <div class="rw-track-alert rw-track-alert-error mt--24">
                            <i class="fa-regular fa-circle-exclamation mr--4"></i>
                            <?= h($trackError) ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($trackOrder):
                            $ord = $trackOrder;
                            $flow = $ord['status_flow'] ?? [];
                            $currentIdx = array_search($ord['status'], $flow, true);
                            $currentIdx = $currentIdx === false ? -1 : (int)$currentIdx;
                            $statusLabels = [
                                'pending' => 'Хүлээгдэж байна',
                                'confirmed' => 'Баталгаажсан',
                                'cargo_shipping' => 'Тээвэрлэж байна',
                                'cargo_arrived' => 'Монголд ирсэн',
                                'ready_pickup' => 'Авахад бэлэн',
                                'delivering' => 'Хүргэлтэнд гарсан',
                                'delivered' => 'Хүргэгдсэн',
                                'picked_up' => 'Авсан',
                                'completed' => 'Дууссан',
                                'cancelled' => 'Цуцлагдсан',
                            ];
                            $isCancelled = $ord['status'] === 'cancelled';
                        ?>
                        <div class="rw-track-result mt--32">
                            <div class="rw-track-result-head">
                                <div>
                                    <p class="rw-track-label">Захиалгын дугаар</p>
                                    <h2 class="rw-track-value h5 mb--0"><?= h($ord['order_number']) ?></h2>
                                </div>
                                <div class="rw-track-status-badge rw-track-status-<?= h($ord['status']) ?>">
                                    <?= h($ord['status_label']) ?>
                                </div>
                            </div>

                            <?php if (!empty($flow) && !$isCancelled): ?>
                            <div class="rw-track-flow">
                                <?php foreach ($flow as $i => $step):
                                    $isDone = $currentIdx >= 0 && $i <= $currentIdx;
                                    $isNow  = $currentIdx === $i;
                                ?>
                                <div class="rw-track-step <?= $isDone ? 'is-done' : '' ?> <?= $isNow ? 'is-now' : '' ?>">
                                    <div class="rw-track-step-dot"><?= $isDone ? '<i class="fa-solid fa-check"></i>' : ($i + 1) ?></div>
                                    <div class="rw-track-step-label"><?= h($statusLabels[$step] ?? $step) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="rw-track-meta">
                                <div class="rw-track-meta-item">
                                    <span class="rw-track-label">Захиалагч</span>
                                    <span class="rw-track-value"><?= h($ord['customer_name']) ?></span>
                                </div>
                                <div class="rw-track-meta-item">
                                    <span class="rw-track-label">Утас</span>
                                    <span class="rw-track-value"><?= h($ord['customer_phone']) ?></span>
                                </div>
                                <?php if (!empty($ord['district_mn']) || !empty($ord['district'])): ?>
                                <div class="rw-track-meta-item">
                                    <span class="rw-track-label">Дүүрэг</span>
                                    <span class="rw-track-value"><?= h($ord['district_mn'] ?: $ord['district']) ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="rw-track-meta-item">
                                    <span class="rw-track-label">Огноо</span>
                                    <span class="rw-track-value"><?= h(date('Y-m-d H:i', strtotime($ord['created_at']))) ?></span>
                                </div>
                                <div class="rw-track-meta-item">
                                    <span class="rw-track-label">Төлбөр</span>
                                    <span class="rw-track-value"><?= h(formatPrice($ord['total'])) ?></span>
                                </div>
                            </div>

                            <?php if (!empty($ord['items'])): ?>
                            <div class="rw-track-items">
                                <h3 class="h6 mb--16">Захиалсан бараа</h3>
                                <?php foreach ($ord['items'] as $item): ?>
                                <div class="rw-track-item">
                                    <?php if (!empty($item['image'])): ?>
                                    <img class="rw-track-item-img" src="<?= h($item['image']) ?>" alt="<?= h($item['product_name']) ?>">
                                    <?php else: ?>
                                    <div class="rw-track-item-img rw-track-item-img-placeholder"><i class="fa-regular fa-image"></i></div>
                                    <?php endif; ?>
                                    <div class="rw-track-item-info">
                                        <p class="rw-track-item-name"><?= h($item['product_name']) ?></p>
                                        <?php if (!empty($item['variant_label'])): ?>
                                        <p class="rw-track-item-variant"><?= h($item['variant_label']) ?></p>
                                        <?php endif; ?>
                                        <p class="rw-track-item-qty"><?= (int)$item['quantity'] ?> ×
                                            <?= h(formatPrice((float)$item['product_price'])) ?></p>
                                    </div>
                                    <div class="rw-track-item-total"><?= h(formatPrice((float)$item['line_total'] ?? ($item['product_price'] * $item['quantity']))) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
