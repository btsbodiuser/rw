<?php
$pageTitle = 'Жолооч';
$db = getDB();

$drivers = $db->query("SELECT dd.*, 
    (SELECT COUNT(*) FROM deliveries d WHERE d.driver_id = dd.id AND d.status IN ('assigned','picked_up')) as active_deliveries,
    (SELECT COUNT(*) FROM deliveries d WHERE d.driver_id = dd.id AND d.status = 'delivered') as completed_deliveries,
    (SELECT COUNT(*) FROM deliveries d WHERE d.driver_id = dd.id) as total_deliveries
    FROM delivery_drivers dd ORDER BY dd.is_active DESC, dd.name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <p class="text-sm text-gray-500">Нийт <?= count($drivers) ?> жолооч</p>
    </div>
    <a href="index.php?page=driver-form" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Жолооч нэмэх
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Нэр</th>
                    <th class="px-5 py-3 text-left">Утас</th>
                    <th class="px-5 py-3 text-center">Идэвхтэй</th>
                    <th class="px-5 py-3 text-center">Одоогийн</th>
                    <th class="px-5 py-3 text-center">Дууссан</th>
                    <th class="px-5 py-3 text-center">Нийт</th>
                    <th class="px-5 py-3 text-center">Төлөв</th>
                    <th class="px-5 py-3 text-center">Линк</th>
                    <th class="px-5 py-3 text-right">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($drivers as $driver): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-gray-900"><?= e($driver['name']) ?></td>
                    <td class="px-5 py-3 text-gray-600"><?= e($driver['phone']) ?></td>
                    <td class="px-5 py-3 text-center">
                        <?php if ($driver['active_deliveries'] > 0): ?>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium"><?= $driver['active_deliveries'] ?></span>
                        <?php else: ?>
                            <span class="text-gray-400">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-center text-green-600 font-medium"><?= $driver['completed_deliveries'] ?></td>
                    <td class="px-5 py-3 text-center text-gray-500"><?= $driver['total_deliveries'] ?></td>
                    <td class="px-5 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $driver['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                            <?= $driver['is_active'] ? 'Идэвхтэй' : 'Идэвхгүй' ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <?php if (!empty($driver['access_token'])): ?>
                        <?php
                            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                            $basePath = rtrim(str_replace('/backend', '', dirname($_SERVER['SCRIPT_NAME'])), '/');
                            $driverLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/driver/' . $driver['access_token'];
                        ?>
                        <button onclick="navigator.clipboard.writeText('<?= e($driverLink) ?>'); this.textContent='Хууллаа!'; setTimeout(() => this.textContent='Линк хуулах', 2000)" class="px-2 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100">Линк хуулах</button>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">Токен үүсээгүй</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="index.php?page=driver-form&id=<?= $driver['id'] ?>" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <a href="index.php?page=driver-delete&id=<?= $driver['id'] ?>&token=<?= generateCSRFToken() ?>" onclick="return confirm('Энэ жолоочийг устгах уу?')" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($drivers)): ?>
                <tr><td colspan="9" class="px-5 py-8 text-center text-gray-400">Жолооч бүртгэгдээгүй байна</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
