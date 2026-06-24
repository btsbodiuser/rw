<?php
$pageTitle = 'Ачааны багц';
$db = getDB();

$batches = $db->query("SELECT cb.*,
    (SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi WHERE oi.cargo_batch_id = cb.id) as order_count,
    (SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.cargo_batch_id = cb.id AND o.payment_status = 'paid') as paid_count,
    (SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.cargo_batch_id = cb.id AND o.payment_status != 'paid') as unpaid_count,
    (SELECT COALESCE(SUM(o2.total), 0) FROM orders o2 WHERE o2.id IN (SELECT DISTINCT oi2.order_id FROM order_items oi2 WHERE oi2.cargo_batch_id = cb.id) AND o2.payment_status = 'paid') as total_revenue,
    (SELECT COUNT(DISTINCT o3.customer_phone) FROM orders o3 JOIN order_items oi3 ON oi3.order_id = o3.id WHERE oi3.cargo_batch_id = cb.id AND o3.payment_status = 'paid' AND o3.customer_phone IS NOT NULL AND o3.customer_phone != '' AND o3.status = 'cargo_arrived') as sms_count,
    (SELECT COUNT(DISTINCT oi4.order_id) FROM order_items oi4 JOIN orders o4 ON oi4.order_id = o4.id WHERE oi4.cargo_batch_id = cb.id AND o4.status = 'cargo_arrived' AND o4.payment_status = 'paid') as arrived_count
    FROM cargo_batches cb ORDER BY cb.created_at DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <p class="text-sm text-gray-500"><?= count($batches) ?> багц</p>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="document.getElementById('testSmsPanel').classList.toggle('hidden')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 text-sm font-medium">
            📱 SMS тест
        </button>
        <a href="index.php?page=cargo-batch-form" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Шинэ багц
        </a>
    </div>
</div>

<!-- Test SMS Panel -->
<div id="testSmsPanel" class="hidden bg-white rounded-xl shadow-sm border border-orange-200 p-5 mb-6">
    <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">📱 SMS тест илгээх</h3>
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <input type="text" id="testSmsPhone" maxlength="8" inputmode="numeric" placeholder="Утасны дугаар (8 орон)"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 outline-none">
        </div>
        <button onclick="sendTestSms()" id="testSmsBtn"
                class="px-6 py-2.5 bg-orange-500 text-white rounded-lg text-sm font-medium hover:bg-orange-600 transition-colors disabled:opacity-50">
            Тест илгээх
        </button>
    </div>
    <div id="testSmsResult" class="hidden mt-3 p-3 rounded-lg text-sm"></div>
</div>

<!-- Info -->
<div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-800">
    <strong>Хэрхэн ажилладаг:</strong> Ачааны багц үүсгэж хугацаа оруулна → хэрэглэгчид урьдчилсан захиалга өгнө → багцыг хаана → Солонгосоос тээвэрлэнэ → ирсэн гэж тэмдэглэнэ → захиалгуудыг түгээнэ.
</div>

<div class="space-y-4">
    <?php if (empty($batches)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
            Ачааны багц байхгүй. Эхний багцаа үүсгэж урьдчилсан захиалга хүлээн аваарай.
        </div>
    <?php endif; ?>

    <?php foreach ($batches as $batch): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                <?php
                $iconBg  = match($batch['status']) {
                    'open'      => 'bg-green-100',
                    'shipping'  => 'bg-blue-100',
                    'receiving' => 'bg-orange-100',
                    'arrived'   => 'bg-purple-100',
                    default     => 'bg-gray-100',
                };
                ?>
                <div class="w-12 h-12 rounded-xl flex items-center justify-center <?= $iconBg ?>">
                    <?php if ($batch['status'] === 'open'): ?>
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <?php elseif ($batch['status'] === 'shipping'): ?>
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <?php elseif ($batch['status'] === 'receiving'): ?>
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H7m12 0l-4-4m4 4l-4 4M3 12a9 9 0 1118 0 9 9 0 01-18 0z"/></svg>
                    <?php elseif ($batch['status'] === 'arrived'): ?>
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <?php else: ?>
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900"><?= e($batch['name']) ?></h3>
                    <div class="flex flex-wrap items-center gap-3 mt-1 text-sm text-gray-500">
                        <span>Хугацаа: <strong><?= formatDate($batch['due_date']) ?></strong></span>
                        <span>Тариф: <strong><?= formatPrice($batch['cargo_rate_per_kg']) ?>/кг</strong></span>
                        <span>Захиалга: <strong><?= $batch['order_count'] ?></strong> (<span class="text-green-600"><?= $batch['paid_count'] ?> төлсөн</span>, <span class="text-yellow-600"><?= $batch['unpaid_count'] ?> төлөөгүй</span>)</span>
                        <span>Орлого: <strong><?= formatPrice($batch['total_revenue']) ?></strong></span>
                        <?php if (in_array($batch['status'], ['receiving', 'arrived'])): ?>
                        <span>📦 Ирсэн: <strong class="text-green-600"><?= $batch['arrived_count'] ?></strong> / <?= $batch['paid_count'] ?></span>
                        <?php endif; ?>
                        <?php if ($batch['sms_count'] > 0): ?>
                        <span>📱 Мэдэгдэл: <strong><?= $batch['sms_count'] ?> хүн</strong></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($batch['notes']): ?>
                        <p class="text-xs text-gray-400 mt-1"><?= e($batch['notes']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <?= batchStatusLabel($batch['status']) ?>

                <!-- Status Actions -->
                <?php if ($batch['status'] === 'open'): ?>
                    <form method="POST" action="index.php?page=cargo-batch-form&id=<?= $batch['id'] ?>&action=status" class="inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="new_status" value="closed">
                        <button type="submit" onclick="return confirm('Энэ багцыг хаах уу? Урьдчилсан захиалга авахаа болино.')"
                                class="px-3 py-1.5 bg-yellow-100 text-yellow-800 rounded-lg text-xs font-medium hover:bg-yellow-200">
                            Багц хаах
                        </button>
                    </form>
                <?php elseif ($batch['status'] === 'closed'): ?>
                    <form method="POST" action="index.php?page=cargo-batch-form&id=<?= $batch['id'] ?>&action=status" class="inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="new_status" value="shipping">
                        <button type="submit" class="px-3 py-1.5 bg-blue-100 text-blue-800 rounded-lg text-xs font-medium hover:bg-blue-200">
                            Тээвэрлэж буй
                        </button>
                    </form>
                <?php elseif ($batch['status'] === 'shipping'): ?>
                    <form method="POST" action="index.php?page=cargo-batch-form&id=<?= $batch['id'] ?>&action=status" class="inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="new_status" value="receiving">
                        <button type="submit" class="px-3 py-1.5 bg-orange-100 text-orange-800 rounded-lg text-xs font-medium hover:bg-orange-200">
                            Хүлээн авч байна
                        </button>
                    </form>
                <?php elseif ($batch['status'] === 'receiving'): ?>
                    <a href="index.php?page=inventory-arrival-form&batch_id=<?= $batch['id'] ?>"
                       class="px-3 py-1.5 bg-orange-500 text-white rounded-lg text-xs font-medium hover:bg-orange-600">
                        + Ирэлт бүртгэх
                    </a>
                    <form method="POST" action="index.php?page=cargo-batch-form&id=<?= $batch['id'] ?>&action=status" class="inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="new_status" value="arrived">
                        <button type="submit" onclick="return confirm('Бүх захиалга биелэгдсэн үү? Багцыг дууссан гэж тэмдэглэх үү?')"
                                class="px-3 py-1.5 bg-purple-100 text-purple-800 rounded-lg text-xs font-medium hover:bg-purple-200">
                            Дуусгах
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (in_array($batch['status'], ['receiving', 'arrived']) && $batch['sms_count'] > 0): ?>
                    <?php if (!empty($batch['sms_sent_at'])): ?>
                        <span class="px-3 py-1.5 text-xs text-green-600 font-medium" title="<?= date('Y.m.d H:i', strtotime($batch['sms_sent_at'])) ?>">
                            ✅ Мэдэгдэл илгээсэн
                        </span>
                        <button type="button" onclick="startSmsSend(<?= (int)$batch['id'] ?>, <?= (int)$batch['sms_count'] ?>)"
                                class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-200">
                            📱 Дахин илгээх
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="startSmsSend(<?= (int)$batch['id'] ?>, <?= (int)$batch['sms_count'] ?>)"
                                class="px-3 py-1.5 bg-green-100 text-green-800 rounded-lg text-xs font-medium hover:bg-green-200">
                            📱 Мэдэгдэл илгээх (<?= $batch['sms_count'] ?>)
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (in_array($batch['status'], ['receiving', 'arrived']) && $batch['arrived_count'] > 0): ?>
                    <a href="index.php?page=batch-handout&id=<?= $batch['id'] ?>" class="px-3 py-1.5 bg-indigo-100 text-indigo-800 rounded-lg text-xs font-medium hover:bg-indigo-200">
                        📦 Түгээлт
                    </a>
                <?php endif; ?>

                <a href="index.php?page=cargo-batch-form&id=<?= $batch['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600" title="Дэлгэрэнгүй">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </a>
                <a href="index.php?page=cargo-batch-form&id=<?= $batch['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600" title="Засах">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- SMS Progress Modal -->
<div id="smsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100">
            <h3 class="font-bold text-gray-900 text-lg flex items-center gap-2">
                📋 SMS жагсаалтад нэмэх
            </h3>
        </div>
        <div class="px-6 py-5">
            <!-- Before start -->
            <div id="smsConfirm">
                <p class="text-sm text-gray-600 mb-4">
                    <span id="smsRecipientCount" class="font-bold text-gray-900"></span> хүний мэдэгдлийг SMS жагсаалтад нэмэхэд бэлэн.
                </p>
                <div class="flex gap-3">
                    <button onclick="executSmsSend()" class="flex-1 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                        Жагсаалтад нэмэх
                    </button>
                    <button onclick="closeSmsMmodal()" class="flex-1 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                        Болих
                    </button>
                </div>
            </div>

            <!-- Progress -->
            <div id="smsProgress" class="hidden">
                <div class="mb-4">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600">Жагсаалтад нэмж байна...</span>
                        <span class="font-bold text-gray-900"><span id="smsSent">0</span> / <span id="smsTotal">0</span></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div id="smsBar" class="h-full bg-gradient-to-r from-blue-500 to-green-500 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
                <div class="flex gap-4 text-sm">
                    <span class="text-green-600">✓ <span id="smsOk">0</span> нэмэгдлээ</span>
                    <span class="text-red-600">✕ <span id="smsFail">0</span> алдаатай</span>
                </div>
                <div id="smsCurrentPhone" class="text-xs text-gray-400 mt-2"></div>
            </div>

            <!-- Done -->
            <div id="smsDone" class="hidden text-center">
                <div id="smsDoneIcon" class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-2xl"></div>
                <p id="smsDoneText" class="font-bold text-lg mb-1"></p>
                <p id="smsDoneDetail" class="text-sm text-gray-500 mb-4"></p>
                <div id="smsErrorLog" class="hidden mb-4 text-left">
                    <p class="text-xs font-medium text-red-600 mb-1">Алдааны дэлгэрэнгүй:</p>
                    <pre class="text-xs bg-red-50 text-red-700 p-3 rounded-lg max-h-32 overflow-y-auto whitespace-pre-wrap"></pre>
                </div>
                <button onclick="closeSmsMmodal(); location.reload();" class="w-full py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    Болсон
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= generateCSRFToken() ?>';
let smsBatchId = null;
let smsRecipients = [];
let smsAborted = false;
let smsErrors = [];

async function sendTestSms() {
    const phone = document.getElementById('testSmsPhone').value.replace(/\D/g, '');
    const btn = document.getElementById('testSmsBtn');
    const result = document.getElementById('testSmsResult');

    if (phone.length !== 8) {
        result.className = 'mt-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';
        result.textContent = 'Утас 8 оронтой байх ёстой';
        result.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Илгээж байна...';
    result.className = 'mt-3 p-3 rounded-lg text-sm bg-gray-50 text-gray-600';
    result.textContent = '📤 ' + phone + ' руу илгээж байна...';
    result.classList.remove('hidden');

    try {
        const res = await fetch('index.php?page=cargo-batch-form&id=0&action=test_sms', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken, phone: phone }),
        });
        const data = await res.json();

        if (data.success) {
            result.className = 'mt-3 p-3 rounded-lg text-sm bg-green-50 text-green-700';
            result.innerHTML = `✅ ${data.message}<br><span class="text-xs text-green-500">HTTP ${data.http_code} | Response: ${data.response || 'OK'}</span>`;
        } else {
            result.className = 'mt-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';
            let detail = data.error || 'Unknown error';
            if (data.response) detail += '\nResponse: ' + data.response;
            if (data.details) detail += '\n' + JSON.stringify(data.details);
            result.innerHTML = `❌ ${detail.replace(/\n/g, '<br>')}`;
        }
    } catch (e) {
        result.className = 'mt-3 p-3 rounded-lg text-sm bg-red-50 text-red-700';
        result.textContent = '❌ Серверийн алдаа: ' + e.message;
    }

    btn.disabled = false;
    btn.textContent = 'Тест илгээх';
}

async function startSmsSend(batchId, expectedCount) {
    smsBatchId = batchId;
    smsAborted = false;

    // Show modal in loading state
    document.getElementById('smsModal').classList.remove('hidden');
    document.getElementById('smsConfirm').classList.remove('hidden');
    document.getElementById('smsProgress').classList.add('hidden');
    document.getElementById('smsDone').classList.add('hidden');
    document.getElementById('smsRecipientCount').textContent = '...';

    try {
        const res = await fetch(`index.php?page=cargo-batch-form&id=${batchId}&action=sms_recipients`);
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseErr) {
            alert('Сервер JSON биш хариу буцаалаа:\n' + text.substring(0, 300));
            closeSmsMmodal();
            return;
        }
        if (data.error) {
            alert(data.error);
            closeSmsMmodal();
            return;
        }
        smsRecipients = data.recipients;
        document.getElementById('smsRecipientCount').textContent = data.total;
        if (data.total === 0) {
            alert('Мэдэгдэл илгээх хүн олдсонгүй (төлбөр төлсөн, цуцлаагүй захиалга).');
            closeSmsMmodal();
        }
    } catch (e) {
        alert('Серверийн алдаа: ' + e.message);
        closeSmsMmodal();
    }
}

async function executSmsSend() {
    document.getElementById('smsConfirm').classList.add('hidden');
    document.getElementById('smsProgress').classList.remove('hidden');

    const total = smsRecipients.length;
    let sent = 0, ok = 0, fail = 0;
    smsErrors = [];

    document.getElementById('smsTotal').textContent = total;
    document.getElementById('smsSent').textContent = '0';
    document.getElementById('smsOk').textContent = '0';
    document.getElementById('smsFail').textContent = '0';
    document.getElementById('smsBar').style.width = '0%';

    for (const r of smsRecipients) {
        if (smsAborted) break;

        document.getElementById('smsCurrentPhone').textContent = `📋 ${r.phone} жагсаалтад нэмж байна...`;

        try {
            const res = await fetch(`index.php?page=cargo-batch-form&id=${smsBatchId}&action=send_single_sms`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: csrfToken, phone: r.phone, text: r.text }),
            });
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseErr) {
                fail++;
                smsErrors.push(`${r.phone}: Сервер JSON биш хариу: ${text.substring(0, 100)}`);
                sent++;
                continue;
            }
            if (data.success) {
                ok++;
            } else {
                fail++;
                smsErrors.push(`${r.phone}: ${data.error || data.response || 'HTTP ' + (data.http_code || res.status)}`);
            }
        } catch (e) {
            fail++;
            smsErrors.push(`${r.phone}: ${e.message}`);
        }

        sent++;
        document.getElementById('smsSent').textContent = sent;
        document.getElementById('smsOk').textContent = ok;
        document.getElementById('smsFail').textContent = fail;
        document.getElementById('smsBar').style.width = Math.round((sent / total) * 100) + '%';
    }

    document.getElementById('smsCurrentPhone').textContent = '';

    // Mark batch as sent
    try {
        await fetch(`index.php?page=cargo-batch-form&id=${smsBatchId}&action=mark_sms_sent`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken, sent: ok, failed: fail }),
        });
    } catch (e) {}

    // Show done
    document.getElementById('smsProgress').classList.add('hidden');
    document.getElementById('smsDone').classList.remove('hidden');

    const icon = document.getElementById('smsDoneIcon');
    const text = document.getElementById('smsDoneText');
    const detail = document.getElementById('smsDoneDetail');

    if (fail === 0) {
        icon.className = 'w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-2xl bg-green-100';
        icon.textContent = '📋';
        text.textContent = 'Жагсаалтад нэмэгдлээ!';
        detail.innerHTML = `${ok} мэдэгдэл SMS жагсаалтад нэмэгдлээ.<br><a href="index.php?page=sms-queue" class="text-blue-600 underline text-sm">SMS жагсаалт руу →</a>`;
    } else if (ok === 0) {
        icon.className = 'w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-2xl bg-red-100';
        icon.textContent = '❌';
        text.textContent = 'Алдаа гарлаа';
        detail.textContent = `${fail} мэдэгдэл нэмэгдсэнгүй. Дугаарыг шалгана уу.`;
    } else {
        icon.className = 'w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-2xl bg-yellow-100';
        icon.textContent = '⚠️';
        text.textContent = 'Зарим нь алдаатай';
        detail.innerHTML = `${ok} нэмэгдлээ, ${fail} алдаатай.<br><a href="index.php?page=sms-queue" class="text-blue-600 underline text-sm">SMS жагсаалт руу →</a>`;
    }

    // Show error log if any
    const errLog = document.getElementById('smsErrorLog');
    if (smsErrors.length > 0) {
        errLog.classList.remove('hidden');
        errLog.querySelector('pre').textContent = smsErrors.join('\n');
    } else {
        errLog.classList.add('hidden');
    }
}

function closeSmsMmodal() {
    smsAborted = true;
    document.getElementById('smsModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
