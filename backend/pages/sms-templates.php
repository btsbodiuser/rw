<?php
$pageTitle = 'SMS загвар';
$db = getDB();

// ── AJAX: save a template ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !verifyCSRFToken($data['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'CSRF']); exit;
    }
    $key  = trim($data['key']  ?? '');
    $body = trim($data['body'] ?? '');
    if (!$key || !$body) { echo json_encode(['error' => 'Хоосон байна']); exit; }

    $check = $db->prepare("SELECT id FROM sms_templates WHERE `key` = ?");
    $check->execute([$key]);
    if (!$check->fetch()) { echo json_encode(['error' => 'Загвар олдсонгүй']); exit; }

    $db->prepare("UPDATE sms_templates SET body = ?, updated_by = ?, updated_at = NOW() WHERE `key` = ?")
       ->execute([$body, $currentAdmin['id'] ?? null, $key]);

    echo json_encode(['success' => true]);
    exit;
}

$templates = $db->query("SELECT * FROM sms_templates ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <p class="text-sm text-gray-500">Засварласны дараа шинэ мессеж бүрт автоматаар ашиглагдана.</p>
</div>

<div class="space-y-4" x-data="{ editing: null }">
    <?php foreach ($templates as $tpl):
        $vars = array_filter(array_map('trim', explode(',', $tpl['variables'] ?? '')));
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-start justify-between px-5 py-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-semibold text-gray-900"><?= e($tpl['name']) ?></span>
                    <code class="px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded text-xs"><?= e($tpl['key']) ?></code>
                    <?php if ($vars): ?>
                        <?php foreach ($vars as $v): ?>
                            <span class="px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-xs font-mono"><?= e($v) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- View mode -->
                <div x-show="editing !== '<?= e($tpl['key']) ?>'" class="mt-2 text-sm text-gray-600 bg-gray-50 rounded-lg px-3 py-2 font-mono leading-relaxed">
                    <?= nl2br(e($tpl['body'])) ?>
                </div>

                <!-- Edit mode -->
                <div x-show="editing === '<?= e($tpl['key']) ?>'" x-cloak class="mt-2">
                    <?php if ($vars): ?>
                    <p class="text-xs text-blue-600 mb-1.5">
                        Ашиглах боломжтой: <?= implode(', ', array_map(fn($v) => '<code class="bg-blue-50 px-1 rounded">'.$v.'</code>', $vars)) ?>
                    </p>
                    <?php endif; ?>
                    <textarea id="body_<?= e($tpl['key']) ?>"
                              class="w-full px-3 py-2 border border-blue-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-400 outline-none resize-none"
                              rows="3"><?= e($tpl['body']) ?></textarea>
                    <div class="flex items-center gap-2 mt-2">
                        <button onclick="saveTpl('<?= e($tpl['key']) ?>')"
                                class="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                            Хадгалах
                        </button>
                        <button @click="editing = null"
                                class="px-4 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200">
                            Болих
                        </button>
                        <span class="save-msg_<?= e($tpl['key']) ?> hidden text-xs text-green-600 ml-1">✓ Хадгалагдлаа</span>
                    </div>
                </div>
            </div>

            <div class="ml-4 flex-shrink-0">
                <div x-show="editing !== '<?= e($tpl['key']) ?>'">
                    <button @click="editing = '<?= e($tpl['key']) ?>'"
                            class="px-3 py-1.5 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                        Засах
                    </button>
                </div>
            </div>
        </div>

        <?php if ($tpl['updated_at']): ?>
        <div class="px-5 py-2 bg-gray-50/60 border-t border-gray-50 text-xs text-gray-400">
            Сүүлд засварласан: <?= date('Y-m-d H:i', strtotime($tpl['updated_at'])) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<script>
const csrfToken = '<?= generateCSRFToken() ?>';

async function saveTpl(key) {
    const body = document.getElementById('body_' + key).value.trim();
    if (!body) return;

    const btn = event.currentTarget;
    btn.disabled = true; btn.textContent = 'Хадгалж байна...';

    try {
        const res  = await fetch('index.php?page=sms-templates&action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken, key, body }),
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert('Алдаа: ' + (data.error || ''));
        }
    } catch (e) {
        alert('Серверийн алдаа: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = 'Хадгалах';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
