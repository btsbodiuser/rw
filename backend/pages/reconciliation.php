<?php
$pageTitle = 'Төлбөр тулгалт';
$db = getDB();

$QPAY_FEE_RATE = 0.01; // QPay takes 1% fee

// ── Cargo payment helpers ─────────────────────────────────────────
// Mirror of markCargoPaid() from api/cargo-payment.php — kept local
// to avoid pulling the whole gateway-client file into the admin page.
function markCargoPaymentPaid(PDO $db, int $cargoPaymentId): void {
    $cp = $db->prepare("SELECT order_id, status FROM cargo_payments WHERE id = ?");
    $cp->execute([$cargoPaymentId]);
    $row = $cp->fetch();
    if (!$row || $row['status'] === 'paid') return;
    $orderId = (int)$row['order_id'];

    $db->prepare("UPDATE cargo_payments SET status='paid', paid_at=NOW() WHERE id = ?")->execute([$cargoPaymentId]);

    $db->prepare("
        UPDATE order_items SET cargo_fee_paid = 1
        WHERE order_id = ? AND hide_cargo_fee = 1 AND cargo_fee > 0 AND cargo_fee_paid = 0
    ")->execute([$orderId]);

    $unpaid = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND cargo_fee > 0 AND cargo_fee_paid = 0");
    $unpaid->execute([$orderId]);
    $allPaid = (int)$unpaid->fetchColumn() === 0 ? 1 : 0;
    $db->prepare("UPDATE orders SET cargo_fee_paid = ? WHERE id = ?")->execute([$allPaid, $orderId]);
}

// Look up cargo_payment for a CARGO-<order_number> reference.
// Returns ['cargo_payment_id', 'order_id', 'expected_amount', 'match_status', 'status']
// or null if no candidate exists for the order/method at all.
function lookupCargoPayment(PDO $db, string $orderNumber, string $method, float $bankAmount, float $feeRate): ?array {
    $sql = "SELECT cp.id AS cp_id, cp.order_id, cp.amount, cp.status
            FROM cargo_payments cp
            JOIN orders o ON o.id = cp.order_id
            WHERE o.order_number = ? AND cp.payment_method = ?
            ORDER BY cp.status = 'pending' DESC, cp.created_at DESC
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$orderNumber, $method]);
    $row = $stmt->fetch();
    if (!$row) return null;

    $expected = $method === 'qpay'
        ? round((float)$row['amount'] * (1 - $feeRate), 2)
        : (float)$row['amount'];

    $matchStatus = abs($bankAmount - $expected) <= 1.0 ? 'matched' : 'amount_mismatch';

    return [
        'cargo_payment_id' => (int)$row['cp_id'],
        'order_id'         => (int)$row['order_id'],
        'expected_amount'  => $expected,
        'cargo_amount'     => (float)$row['amount'],
        'match_status'     => $matchStatus,
        'cp_status'        => $row['status'],
    ];
}

// ── Handle CSV/Excel Upload ──
$uploadMessage = '';
$uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bank_csv'])) {
    $file = $_FILES['bank_csv'];
    $statementType = $_POST['statement_type'] ?? 'khan_bank';

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'Файл байршуулахад алдаа гарлаа.';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $uploadError = 'Файлын хэмжээ 5MB-ээс хэтэрсэн байна.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($statementType === 'bonum') {
            if ($ext !== 'xlsx') {
                $uploadError = 'Боном хуулга зөвхөн Excel (.xlsx) файл байх ёстой.';
            } else {
                $result = importBonumStatement($db, $file['tmp_name'], $file['name'], $currentAdmin['id']);
                if ($result['success']) {
                    $uploadMessage = $result['message'];
                    header('Location: index.php?page=reconciliation&import_id=' . $result['import_id']);
                    exit;
                } else {
                    $uploadError = $result['message'];
                }
            }
        } else {
            if (!in_array($ext, ['csv', 'xlsx'])) {
                $uploadError = 'Зөвхөн CSV эсвэл Excel (.xlsx) файл оруулна уу.';
            } else {
                $result = importBankStatement($db, $file['tmp_name'], $file['name'], $QPAY_FEE_RATE, $currentAdmin['id'], $ext);
                if ($result['success']) {
                    $uploadMessage = $result['message'];
                    header('Location: index.php?page=reconciliation&import_id=' . $result['import_id']);
                    exit;
                } else {
                    $uploadError = $result['message'];
                }
            }
        }
    }
}

// ── AJAX: Search orders for manual matching ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search-orders') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); exit; }

    $stmt = $db->prepare("
        SELECT id, order_number, customer_name, customer_phone, total, payment_status, payment_method, status, created_at
        FROM orders
        WHERE order_number LIKE ? OR customer_phone LIKE ? OR customer_name LIKE ?
        ORDER BY created_at DESC LIMIT 10
    ");
    $like = "%{$q}%";
    $stmt->execute([$like, $like, $like]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── AJAX: Manual match / ignore / mark paid ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'manual-match') {
    header('Content-Type: application/json');

    $txId = (int)($_POST['transaction_id'] ?? 0);
    $action = $_POST['action_type'] ?? 'link'; // 'link', 'ignore', 'mark_paid'

    $tx = $db->prepare("SELECT * FROM bank_transactions WHERE id = ?");
    $tx->execute([$txId]);
    $tx = $tx->fetch();
    if (!$tx) { echo json_encode(['ok' => false, 'error' => 'Гүйлгээ олдсонгүй']); exit; }

    try {
        if ($action === 'link') {
            $orderNum = trim($_POST['order_number'] ?? '');
            $order = $db->prepare("SELECT id, order_number, total, payment_status FROM orders WHERE order_number = ?");
            $order->execute([$orderNum]);
            $order = $order->fetch();
            if (!$order) { echo json_encode(['ok' => false, 'error' => 'Захиалга олдсонгүй: ' . $orderNum]); exit; }

            $db->beginTransaction();
            $expectedAmount = (float)$order['total'];
            $matchStatus = abs((float)$tx['credit_amount'] - $expectedAmount) < 1 ? 'matched' : 'amount_mismatch';

            $db->prepare("UPDATE bank_transactions SET order_id = ?, order_number = ?, order_total = ?, expected_amount = ?, match_status = ?, match_method = 'manual' WHERE id = ?")
               ->execute([$order['id'], $order['order_number'], $order['total'], $expectedAmount, $matchStatus, $txId]);

            if ($matchStatus === 'matched' && $order['payment_status'] !== 'paid') {
                $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ?")->execute([$order['id']]);
            }

            $db->commit();
            echo json_encode(['ok' => true, 'match_status' => $matchStatus, 'order_number' => $order['order_number']]);

        } elseif ($action === 'ignore') {
            $db->prepare("UPDATE bank_transactions SET match_status = 'ignored', match_method = 'ignored' WHERE id = ?")->execute([$txId]);
            echo json_encode(['ok' => true]);

        } elseif ($action === 'mark_paid') {
            if (!$tx['order_id']) { echo json_encode(['ok' => false, 'error' => 'Захиалга холбоогүй']); exit; }
            $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'confirmed', confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ?")->execute([$tx['order_id']]);
            echo json_encode(['ok' => true]);

        } elseif ($action === 'mark_cargo_paid') {
            if (!$tx['cargo_payment_id']) { echo json_encode(['ok' => false, 'error' => 'Ачааны төлбөр холбоогүй']); exit; }
            $db->beginTransaction();
            markCargoPaymentPaid($db, (int)$tx['cargo_payment_id']);
            $db->prepare("UPDATE bank_transactions SET match_status = 'matched' WHERE id = ?")->execute([$txId]);
            $db->commit();
            echo json_encode(['ok' => true]);

        } elseif ($action === 'link_cargo') {
            $orderNum = trim($_POST['order_number'] ?? '');
            $method = $tx['is_bonum'] ? 'bonum' : 'qpay';
            $cargoMatch = lookupCargoPayment($db, $orderNum, $method, (float)$tx['credit_amount'], $method === 'qpay' ? 0.01 : 0.0);
            if (!$cargoMatch) {
                echo json_encode(['ok' => false, 'error' => 'Ачааны төлбөр олдсонгүй: ' . $orderNum]);
                exit;
            }
            $db->beginTransaction();
            $db->prepare("UPDATE bank_transactions
                SET cargo_payment_id = ?, order_id = ?, order_number = ?, order_total = ?, expected_amount = ?, match_status = ?, match_method = 'cargo_payment'
                WHERE id = ?")
               ->execute([
                   $cargoMatch['cargo_payment_id'], $cargoMatch['order_id'], 'CARGO-' . $orderNum,
                   $cargoMatch['cargo_amount'], $cargoMatch['expected_amount'],
                   $cargoMatch['match_status'], $txId
               ]);
            if ($cargoMatch['match_status'] === 'matched' && $cargoMatch['cp_status'] !== 'paid') {
                markCargoPaymentPaid($db, $cargoMatch['cargo_payment_id']);
            }
            $db->commit();
            echo json_encode(['ok' => true, 'match_status' => $cargoMatch['match_status']]);

        } else {
            echo json_encode(['ok' => false, 'error' => 'Буруу үйлдэл']);
        }
    } catch (Exception $ex) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
    }
    exit;
}

// ── Re-process unmatched transactions in an existing import ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'reprocess') {
    header('Content-Type: application/json');
    $importId = (int)($_POST['import_id'] ?? 0);
    if ($importId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Import ID шаардлагатай']);
        exit;
    }

    try {
        $orderPrefix = getSetting('order_number_prefix', 'NO');
        $orderPrefixRe = preg_quote($orderPrefix, '/');
        $windowMinutes = (int)getSetting('reconciliation_time_window_minutes', 30);

        // Only re-process transactions that haven't been manually handled
        $rows = $db->prepare("SELECT * FROM bank_transactions
            WHERE import_id = ?
              AND order_id IS NULL
              AND match_status IN ('unmatched','no_order','amount_mismatch')
              AND (match_method IS NULL OR match_method NOT IN ('manual','ignored'))");
        $rows->execute([$importId]);
        $txList = $rows->fetchAll();

        $newlyMatched = 0;
        $upd = $db->prepare("UPDATE bank_transactions SET order_id = ?, order_number = ?, order_total = ?, expected_amount = ?, match_status = ?, match_method = ? WHERE id = ?");
        $updCargo = $db->prepare("UPDATE bank_transactions SET cargo_payment_id = ?, order_id = ?, order_total = ?, expected_amount = ?, match_status = ?, match_method = 'cargo_payment' WHERE id = ?");

        foreach ($txList as $tx) {
            $description = $tx['description'] ?? '';
            $credit = (float)$tx['credit_amount'];
            $txDate = $tx['transaction_date'];
            $matched = false;

            // Transfer strategies
            if ((int)$tx['is_transfer'] === 1 && $credit > 0) {
                $descPhone = null;
                if (preg_match('/\b(\d{8})\b/', $description, $pm)) $descPhone = $pm[1];

                // Strategy 1: order# in description
                if (preg_match('/\b' . $orderPrefixRe . '[-\s]?(\d{6,8})\b/i', $description, $onm)) {
                    $possibleNum = $orderPrefix . str_pad($onm[1], 8, '0', STR_PAD_LEFT);
                    $oStmt = $db->prepare("SELECT id, order_number, total FROM orders WHERE order_number = ?");
                    $oStmt->execute([$possibleNum]);
                    if ($order = $oStmt->fetch()) {
                        $status = abs($credit - (float)$order['total']) < 1 ? 'matched' : 'amount_mismatch';
                        $upd->execute([$order['id'], $order['order_number'], $order['total'], $order['total'], $status, 'order_number', $tx['id']]);
                        if ($status === 'matched') { $newlyMatched++; $matched = true; }
                    }
                }

                // Strategy 2: exact amount on pending transfer
                if (!$matched) {
                    $amtStmt = $db->prepare("SELECT id, order_number, total, customer_phone FROM orders
                        WHERE payment_method = 'transfer' AND payment_status = 'pending' AND ABS(total - ?) < 1
                        ORDER BY created_at DESC LIMIT 10");
                    $amtStmt->execute([$credit]);
                    $candidates = $amtStmt->fetchAll();
                    if (count($candidates) === 1) {
                        $order = $candidates[0];
                        $upd->execute([$order['id'], $order['order_number'], $order['total'], $order['total'], 'matched', 'amount', $tx['id']]);
                        $newlyMatched++; $matched = true;
                    } elseif (count($candidates) > 1 && $descPhone) {
                        $phoneMatches = array_values(array_filter($candidates, fn($c) => $c['customer_phone'] === $descPhone));
                        if (count($phoneMatches) === 1) {
                            $order = $phoneMatches[0];
                            $upd->execute([$order['id'], $order['order_number'], $order['total'], $order['total'], 'matched', 'phone_amount', $tx['id']]);
                            $newlyMatched++; $matched = true;
                        }
                    }
                }

                // Strategy 3: phone + amount
                if (!$matched && $descPhone) {
                    $pStmt = $db->prepare("SELECT id, order_number, total FROM orders
                        WHERE customer_phone = ? AND payment_method = 'transfer' AND payment_status = 'pending'
                        ORDER BY created_at DESC LIMIT 5");
                    $pStmt->execute([$descPhone]);
                    $pc = $pStmt->fetchAll();
                    if (count($pc) === 1 && abs($credit - (float)$pc[0]['total']) < 1) {
                        $order = $pc[0];
                        $upd->execute([$order['id'], $order['order_number'], $order['total'], $order['total'], 'matched', 'phone', $tx['id']]);
                        $newlyMatched++; $matched = true;
                    }
                }

                // Strategy 4: time + amount window
                if (!$matched && $windowMinutes > 0) {
                    $tStmt = $db->prepare("SELECT id, order_number, total FROM orders
                        WHERE payment_method = 'transfer' AND payment_status = 'pending'
                          AND ABS(total - ?) < 1
                          AND created_at <= ?
                          AND created_at >= DATE_SUB(?, INTERVAL ? MINUTE)
                        LIMIT 2");
                    $tStmt->execute([$credit, $txDate, $txDate, $windowMinutes]);
                    $tc = $tStmt->fetchAll();
                    if (count($tc) === 1) {
                        $order = $tc[0];
                        $upd->execute([$order['id'], $order['order_number'], $order['total'], $order['total'], 'matched', 'time_amount', $tx['id']]);
                        $newlyMatched++; $matched = true;
                    }
                }
            }

            // QPay cargo re-match
            elseif ((int)$tx['is_qpay'] === 1 && preg_match('/qpay\s+\w+.*?CARGO-' . $orderPrefixRe . '(\d{8})/i', $description, $m)) {
                $rawOrderNum = $orderPrefix . $m[1];
                $cargoMatch = lookupCargoPayment($db, $rawOrderNum, 'qpay', $credit, 0.01);
                if ($cargoMatch) {
                    $updCargo->execute([
                        $cargoMatch['cargo_payment_id'], $cargoMatch['order_id'],
                        $cargoMatch['cargo_amount'], $cargoMatch['expected_amount'],
                        $cargoMatch['match_status'], $tx['id']
                    ]);
                    if ($cargoMatch['match_status'] === 'matched') {
                        if ($cargoMatch['cp_status'] !== 'paid') {
                            markCargoPaymentPaid($db, $cargoMatch['cargo_payment_id']);
                        }
                        $newlyMatched++;
                    }
                }
            }

            // QPay re-match (only Strategy 1: order# regex)
            elseif ((int)$tx['is_qpay'] === 1) {
                if (preg_match('/qpay\s+\w+.*?' . $orderPrefixRe . '(\d{8})/i', $description, $m)) {
                    $orderNum = $orderPrefix . $m[1];
                    $oStmt = $db->prepare("SELECT id, order_number, total FROM orders WHERE order_number = ?");
                    $oStmt->execute([$orderNum]);
                    if ($order = $oStmt->fetch()) {
                        $expected = $tx['expected_amount'] !== null ? (float)$tx['expected_amount'] : (float)$order['total'] * 0.99;
                        $status = abs($credit - $expected) <= 1.0 ? 'matched' : 'amount_mismatch';
                        $upd->execute([$order['id'], $order['order_number'], $order['total'], $expected, $status, 'qpay_auto', $tx['id']]);
                        if ($status === 'matched') $newlyMatched++;
                    }
                }
            }

            // Bonum cargo re-match
            elseif ((int)$tx['is_bonum'] === 1 && $credit > 0 && $tx['order_number'] && stripos($tx['order_number'], 'CARGO-') === 0) {
                $rawOrderNum = substr($tx['order_number'], 6);
                $cargoMatch = lookupCargoPayment($db, $rawOrderNum, 'bonum', $credit, 0.0);
                if ($cargoMatch) {
                    $updCargo->execute([
                        $cargoMatch['cargo_payment_id'], $cargoMatch['order_id'],
                        $cargoMatch['cargo_amount'], $cargoMatch['expected_amount'],
                        $cargoMatch['match_status'], $tx['id']
                    ]);
                    if ($cargoMatch['match_status'] === 'matched') {
                        if ($cargoMatch['cp_status'] !== 'paid') {
                            markCargoPaymentPaid($db, $cargoMatch['cargo_payment_id']);
                        }
                        $newlyMatched++;
                    }
                }
            }

            // Bonum re-match (direct order# lookup, no fee deduction)
            elseif ((int)$tx['is_bonum'] === 1 && $credit > 0) {
                if ($tx['order_number']) {
                    $oStmt = $db->prepare("SELECT id, order_number, total FROM orders WHERE order_number = ?");
                    $oStmt->execute([$tx['order_number']]);
                    if ($order = $oStmt->fetch()) {
                        $expected = (float)$order['total'];
                        $status = abs($credit - $expected) < 1 ? 'matched' : 'amount_mismatch';
                        $upd->execute([$order['id'], $order['order_number'], $order['total'], $expected, $status, 'order_number', $tx['id']]);
                        if ($status === 'matched') $newlyMatched++;
                    }
                }
            }
        }

        // Recompute import counters
        $stats = $db->prepare("SELECT
            COUNT(*) as total_tx,
            SUM(is_qpay) as qpay_tx,
            SUM(is_transfer) as transfer_tx,
            SUM(is_bonum) as bonum_tx,
            SUM(CASE WHEN is_qpay = 1 AND match_status = 'matched' THEN 1 ELSE 0 END) as qpay_matched,
            SUM(CASE WHEN is_qpay = 1 AND match_status != 'matched' THEN 1 ELSE 0 END) as qpay_unmatched,
            SUM(CASE WHEN is_transfer = 1 AND match_status = 'matched' THEN 1 ELSE 0 END) as transfer_matched,
            SUM(CASE WHEN is_transfer = 1 AND match_status != 'matched' THEN 1 ELSE 0 END) as transfer_unmatched,
            SUM(CASE WHEN is_bonum = 1 AND match_status = 'matched' THEN 1 ELSE 0 END) as bonum_matched,
            SUM(CASE WHEN is_bonum = 1 AND match_status != 'matched' THEN 1 ELSE 0 END) as bonum_unmatched
            FROM bank_transactions WHERE import_id = ?");
        $stats->execute([$importId]);
        $s = $stats->fetch();
        $db->prepare("UPDATE bank_statement_imports SET total_transactions = ?, qpay_transactions = ?, matched_transactions = ?, unmatched_transactions = ?, transfer_transactions = ?, transfer_matched = ?, transfer_unmatched = ?, bonum_transactions = ? WHERE id = ?")
           ->execute([(int)$s['total_tx'], (int)$s['qpay_tx'], (int)$s['qpay_matched'], (int)$s['qpay_unmatched'], (int)$s['transfer_tx'], (int)$s['transfer_matched'], (int)$s['transfer_unmatched'], (int)$s['bonum_tx'], $importId]);

        echo json_encode(['ok' => true, 'matched' => $newlyMatched, 'total' => count($txList)]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Import Bank Statement Function ──
function importBankStatement(PDO $db, string $filePath, string $filename, float $feeRate, int $adminId, string $fileType = 'csv'): array {
    
    // ── Parse rows from CSV or XLSX ──
    if ($fileType === 'xlsx') {
        require_once __DIR__ . '/../includes/xlsx-reader.php';
        try {
            $allRows = readXlsxFile($filePath);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Excel файл уншихад алдаа: ' . $e->getMessage()];
        }
        if (count($allRows) < 9) {
            return ['success' => false, 'message' => 'Excel файл хоосон эсвэл буруу формат байна.'];
        }
        // Build lines for header parsing (join cells with comma)
        $lines = array_map(function($row) { return implode(',', $row); }, $allRows);
    } else {
        $content = file_get_contents($filePath);
        // Detect encoding- handle UTF-8 BOM
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        $lines = explode("\n", str_replace("\r\n", "\n", $content));
        $allRows = null; // will use str_getcsv for CSV
    }
    
    if (count($lines) < 9) {
        return ['success' => false, 'message' => 'Файл хоосон эсвэл буруу формат байна.'];
    }
    
    // Parse header info
    $accountNumber = '';
    $periodFrom = null;
    $periodTo = null;
    
    // Row 4 (index 3) has account holder info and interval
    for ($i = 0; $i < min(8, count($lines)); $i++) {
        $line = $lines[$i];
        if (preg_match('/Интервал:\s*(\d{4}-\d{2}-\d{2})-(\d{4}-\d{2}-\d{2})/', $line, $m)) {
            $periodFrom = $m[1];
            $periodTo = $m[2];
        }
        if (preg_match('/Дансны дугаар:.*?(\d{10,})/', $line, $m)) {
            $accountNumber = $m[1];
        }
    }
    
    // Find the data header row (contains "Гүйлгээний огноо")
    $dataStartRow = -1;
    for ($i = 0; $i < min(15, count($lines)); $i++) {
        if (str_contains($lines[$i], 'Гүйлгээний огноо')) {
            $dataStartRow = $i + 1;
            break;
        }
    }
    
    if ($dataStartRow === -1) {
        return ['success' => false, 'message' => 'Толгой мэдээлэл олдсонгүй. Банкны хуулгын формат буруу байна.'];
    }

    $orderPrefix = getSetting('order_number_prefix', 'NO');
    $orderPrefixRe = preg_quote($orderPrefix, '/');

    // Create import record
    $db->beginTransaction();
    
    try {
        $stmt = $db->prepare("INSERT INTO bank_statement_imports (filename, account_number, period_from, period_to, imported_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$filename, $accountNumber, $periodFrom, $periodTo, $adminId]);
        $importId = $db->lastInsertId();
        
        $totalTx = 0;
        $qpayTx = 0;
        $matchedTx = 0;
        $unmatchedTx = 0;
        $transferTx = 0;
        $transferMatchedTx = 0;
        $transferUnmatchedTx = 0;
        
        $insertStmt = $db->prepare("INSERT INTO bank_transactions
            (import_id, transaction_date, credit_amount, debit_amount, description, counter_account, is_qpay, is_transfer, qpay_ref, order_number, qpay_charge, order_id, cargo_payment_id, match_status, match_method, order_total, expected_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        for ($i = $dataStartRow; $i < count($lines); $i++) {
            // Get fields from xlsx rows array or parse CSV line
            if ($allRows !== null) {
                $fields = $allRows[$i] ?? [];
                if (empty(array_filter($fields, fn($v) => $v !== ''))) continue;
            } else {
                $line = trim($lines[$i]);
                if (empty($line)) continue;
                // Parse CSV line (handle quoted fields)
                $fields = str_getcsv($line);
            }
            
            if (count($fields) < 7) continue;
            
            $txDate = trim($fields[0]);
            if ($fileType === 'xlsx') {
                $credit = abs((float)str_replace(',', '', $fields[3] ?? '0')); // Кредит гүйлгээ
                $debit  = abs((float)str_replace(',', '', $fields[4] ?? '0')); // Дебит гүйлгээ
            } else {
                $debit  = abs((float)str_replace(',', '', $fields[3] ?? '0'));
                $credit = abs((float)str_replace(',', '', $fields[4] ?? '0'));
            }
            $description = trim($fields[6] ?? '');
            $counterAccount = trim($fields[7] ?? '');
            
            // Skip summary rows at the end
            if (empty($txDate) || !preg_match('/^\d{4}-\d{2}-\d{2}/', $txDate)) continue;
            
            $totalTx++;
            
            // Initialize per-transaction vars
            $isQpay = 0;
            $isTransfer = 0;
            $qpayRef = null;
            $orderNumber = null;
            $qpayCharge = null;
            $orderId = null;
            $cargoPaymentId = null;
            $matchStatus = 'unmatched';
            $matchMethod = null;
            $orderTotal = null;
            $expectedAmount = null;
            
            // ── Cargo payment detection (must run BEFORE main QPay regex) ──
            // CARGO- prefix means this is a cargo fee payment.
            // Match against cargo_payments table (not orders).
            if (preg_match('/qpay\s+\w+.*?CARGO-' . $orderPrefixRe . '(\d{8})/i', $description, $m)) {
                $isQpay = 1;
                $rawOrderNumber = $orderPrefix . $m[1];
                $orderNumber = 'CARGO-' . $rawOrderNumber;
                $qpayTx++;
                if (preg_match('/qpay\s+(\w+)/i', $description, $refMatch)) {
                    $qpayRef = $refMatch[1];
                }
                if (preg_match('/CHARGE:\s*([\d,.]+)/i', $description, $chargeMatch)) {
                    $qpayCharge = (float)str_replace(',', '', $chargeMatch[1]);
                }

                $cargoMatch = lookupCargoPayment($db, $rawOrderNumber, 'qpay', $credit, $feeRate);
                if ($cargoMatch) {
                    $cargoPaymentId = $cargoMatch['cargo_payment_id'];
                    $orderId        = $cargoMatch['order_id'];
                    $orderTotal     = $cargoMatch['cargo_amount'];
                    $expectedAmount = $cargoMatch['expected_amount'];
                    $matchStatus    = $cargoMatch['match_status'];
                    $matchMethod    = 'cargo_payment';

                    if ($matchStatus === 'matched') {
                        if ($cargoMatch['cp_status'] !== 'paid') {
                            markCargoPaymentPaid($db, $cargoPaymentId);
                        }
                        $matchedTx++;
                    } else {
                        $unmatchedTx++;
                    }
                } else {
                    $matchStatus = 'no_order';
                    $matchMethod = 'cargo_payment';
                    $unmatchedTx++;
                }
            }
            // ── QPay detection: "qpay XXXXX, <PREFIX>XXXXXXXX, CHARGE: XXX.XX" ──
            elseif (preg_match('/qpay\s+\w+.*?' . $orderPrefixRe . '(\d{8})/i', $description, $m)) {
                $isQpay = 1;
                $orderNumber = $orderPrefix . $m[1];
                $qpayTx++;

                if (preg_match('/qpay\s+(\w+)/i', $description, $refMatch)) {
                    $qpayRef = $refMatch[1];
                }
                if (preg_match('/CHARGE:\s*([\d,.]+)/i', $description, $chargeMatch)) {
                    $qpayCharge = (float)str_replace(',', '', $chargeMatch[1]);
                }

                $orderStmt = $db->prepare("SELECT id, order_number, total, payment_status FROM orders WHERE order_number = ?");
                $orderStmt->execute([$orderNumber]);
                $order = $orderStmt->fetch();
                
                if ($order) {
                    $orderId = $order['id'];
                    $orderTotal = (float)$order['total'];
                    $expectedAmount = round($orderTotal * (1 - $feeRate), 2);
                    
                    if (abs($credit - $expectedAmount) <= 1.0) {
                        $matchStatus = 'matched';
                        $matchMethod = 'qpay_auto';
                        $matchedTx++;
                    } else {
                        $matchStatus = 'amount_mismatch';
                        $matchMethod = 'qpay_auto';
                        $unmatchedTx++;
                    }
                } else {
                    $matchStatus = 'no_order';
                    $matchMethod = 'qpay_auto';
                    $unmatchedTx++;
                }
            }
            // ── Bank transfer detection: non-QPay credit transactions ──
            elseif ($credit > 0) {
                $isTransfer = 1;
                $transferTx++;
                $descPhone = null;

                // Extract 8-digit phone from description
                if (preg_match('/\b(\d{8})\b/', $description, $pm)) {
                    $descPhone = $pm[1];
                }

                // Strategy 1: Find order number in description (<PREFIX> + digits)
                if (preg_match('/\b' . $orderPrefixRe . '[-\s]?(\d{6,8})\b/i', $description, $onm)) {
                    $possibleNum = $orderPrefix . str_pad($onm[1], 8, '0', STR_PAD_LEFT);
                    $oStmt = $db->prepare("SELECT id, order_number, total, payment_status FROM orders WHERE order_number = ?");
                    $oStmt->execute([$possibleNum]);
                    $order = $oStmt->fetch();

                    if ($order) {
                        $orderId = (int)$order['id'];
                        $orderNumber = $order['order_number'];
                        $orderTotal = (float)$order['total'];
                        $expectedAmount = $orderTotal;
                        $matchMethod = 'order_number';
                        if (abs($credit - $orderTotal) < 1) {
                            $matchStatus = 'matched';
                            $transferMatchedTx++;
                        } else {
                            $matchStatus = 'amount_mismatch';
                            $transferUnmatchedTx++;
                        }
                    } else {
                        $orderNumber = $possibleNum;
                        $matchStatus = 'no_order';
                        $matchMethod = 'order_number';
                        $transferUnmatchedTx++;
                    }
                }

                // Strategy 2: Exact amount match on pending transfer orders
                if ($matchMethod === null) {
                    $amtStmt = $db->prepare("
                        SELECT id, order_number, total, payment_status, customer_phone
                        FROM orders
                        WHERE payment_method = 'transfer' AND payment_status = 'pending'
                        AND ABS(total - ?) < 1
                        ORDER BY created_at DESC LIMIT 10
                    ");
                    $amtStmt->execute([$credit]);
                    $candidates = $amtStmt->fetchAll();

                    if (count($candidates) === 1) {
                        $order = $candidates[0];
                        $orderId = (int)$order['id'];
                        $orderNumber = $order['order_number'];
                        $orderTotal = (float)$order['total'];
                        $expectedAmount = $orderTotal;
                        $matchStatus = 'matched';
                        $matchMethod = 'amount';
                        $transferMatchedTx++;
                    } elseif (count($candidates) > 1 && $descPhone) {
                        // Narrow down by phone
                        $phoneMatches = array_values(array_filter($candidates, fn($c) => $c['customer_phone'] === $descPhone));
                        if (count($phoneMatches) === 1) {
                            $order = $phoneMatches[0];
                            $orderId = (int)$order['id'];
                            $orderNumber = $order['order_number'];
                            $orderTotal = (float)$order['total'];
                            $expectedAmount = $orderTotal;
                            $matchStatus = 'matched';
                            $matchMethod = 'phone_amount';
                            $transferMatchedTx++;
                        }
                        // else: matchMethod stays null → falls through to bottom
                    }
                    // else (multiple candidates, no phone): matchMethod stays null
                }

                // Strategy 3: Phone + amount match (if phone found but no match yet)
                if ($matchMethod === null && $descPhone) {
                    $phoneStmt = $db->prepare("
                        SELECT id, order_number, total, payment_status
                        FROM orders
                        WHERE customer_phone = ? AND payment_method = 'transfer' AND payment_status = 'pending'
                        ORDER BY created_at DESC LIMIT 5
                    ");
                    $phoneStmt->execute([$descPhone]);
                    $phoneCandidates = $phoneStmt->fetchAll();

                    if (count($phoneCandidates) === 1 && abs($credit - (float)$phoneCandidates[0]['total']) < 1) {
                        $order = $phoneCandidates[0];
                        $orderId = (int)$order['id'];
                        $orderNumber = $order['order_number'];
                        $orderTotal = (float)$order['total'];
                        $expectedAmount = $orderTotal;
                        $matchStatus = 'matched';
                        $matchMethod = 'phone';
                        $transferMatchedTx++;
                    }
                    // else: matchMethod stays null → falls through to Strategy 4
                }

                // Strategy 4: Time-window + amount match (no order#/phone in description)
                // Customer transferred ~minutes after creating the order; auto-match only if exactly one candidate.
                if ($matchMethod === null) {
                    $windowMinutes = (int)getSetting('reconciliation_time_window_minutes', 30);
                    if ($windowMinutes > 0) {
                        $timeStmt = $db->prepare("
                            SELECT id, order_number, total, payment_status
                            FROM orders
                            WHERE payment_method = 'transfer' AND payment_status = 'pending'
                              AND ABS(total - ?) < 1
                              AND created_at <= ?
                              AND created_at >= DATE_SUB(?, INTERVAL ? MINUTE)
                            LIMIT 2
                        ");
                        $timeStmt->execute([$credit, $txDate, $txDate, $windowMinutes]);
                        $timeCandidates = $timeStmt->fetchAll();

                        if (count($timeCandidates) === 1) {
                            $order = $timeCandidates[0];
                            $orderId = (int)$order['id'];
                            $orderNumber = $order['order_number'];
                            $orderTotal = (float)$order['total'];
                            $expectedAmount = $orderTotal;
                            $matchStatus = 'matched';
                            $matchMethod = 'time_amount';
                            $transferMatchedTx++;
                        }
                        // multiple candidates → leave for manual review
                    }
                }

                if ($matchMethod === null) {
                    $transferUnmatchedTx++;
                }
            }
            
            $insertStmt->execute([
                $importId, $txDate, $credit, $debit, $description, $counterAccount,
                $isQpay, $isTransfer, $qpayRef, $orderNumber, $qpayCharge, $orderId, $cargoPaymentId, $matchStatus, $matchMethod, $orderTotal, $expectedAmount
            ]);
        }

        // Update import summary
        $db->prepare("UPDATE bank_statement_imports SET total_transactions = ?, qpay_transactions = ?, matched_transactions = ?, unmatched_transactions = ?, transfer_transactions = ?, transfer_matched = ?, transfer_unmatched = ? WHERE id = ?")
           ->execute([$totalTx, $qpayTx, $matchedTx, $unmatchedTx, $transferTx, $transferMatchedTx, $transferUnmatchedTx, $importId]);
        
        $db->commit();
        
        $transferMsg = $transferTx > 0 ? ", {$transferTx} шилжүүлэг ({$transferMatchedTx} тохирсон)" : '';
        return [
            'success' => true,
            'import_id' => $importId,
            'message' => "Амжилттай импортлолоо: Нийт {$totalTx} гүйлгээ, {$qpayTx} QPay, {$matchedTx} тохирсон, {$unmatchedTx} зөрүүтэй{$transferMsg}"
        ];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Алдаа: ' . $e->getMessage()];
    }
}

// ── Import Bonum Statement Function ──
// Bonum XLSX columns (0-indexed):
//   0=Terminal ID, 1=Date, 2=Account name, 3=Account number,
//   4=Status (SETTLED|CANCELLED|EXPIRED), 5=Amount, 6=Merchant TX ID (order#),
//   7=Bonum TX ID, 8=Additional info
// Only SETTLED rows are imported. Amount matches order total 1:1 (no fee deduction).
function importBonumStatement(PDO $db, string $filePath, string $filename, int $adminId): array {
    require_once __DIR__ . '/../includes/xlsx-reader.php';
    try {
        $allRows = readXlsxFile($filePath);
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Excel файл уншихад алдаа: ' . $e->getMessage()];
    }

    if (count($allRows) < 2) {
        return ['success' => false, 'message' => 'Боном хуулга хоосон байна.'];
    }

    // Row 0 is header — basic column count check
    if (count($allRows[0]) < 7) {
        return ['success' => false, 'message' => 'Боном хуулгын баганын тоо хүрэхгүй байна. Зөв файл оруулна уу.'];
    }

    $terminalId = trim($allRows[1][0] ?? '');

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO bank_statement_imports (filename, source, account_number, imported_by) VALUES (?, 'bonum', ?, ?)");
        $stmt->execute([$filename, $terminalId, $adminId]);
        $importId = $db->lastInsertId();

        $totalTx      = 0;
        $matchedTx    = 0;
        $unmatchedTx  = 0;
        $minDate      = null;
        $maxDate      = null;

        $insertStmt = $db->prepare("INSERT INTO bank_transactions
            (import_id, transaction_date, credit_amount, debit_amount, description, counter_account,
             is_qpay, is_transfer, is_bonum, order_number, order_id, cargo_payment_id, match_status, match_method, order_total, expected_amount)
            VALUES (?, ?, ?, 0, ?, ?, 0, 0, 1, ?, ?, ?, ?, ?, ?, ?)");

        for ($i = 1; $i < count($allRows); $i++) {
            $row = $allRows[$i];
            if (empty(array_filter($row, fn($v) => $v !== ''))) continue;

            $status = trim($row[4] ?? '');
            if ($status !== 'SETTLED') continue;

            $txDateRaw = trim($row[1] ?? '');
            if (empty($txDateRaw)) continue;
            // "2026-05-13T21:09:41.176" → "2026-05-13 21:09:41"
            $txDate = substr(str_replace('T', ' ', $txDateRaw), 0, 19);

            $amount = abs((float)str_replace(',', '', $row[5] ?? '0'));
            if ($amount <= 0) continue;

            $merchantTxId   = trim($row[6] ?? ''); // = order number e.g. KM90014454
            $bonumTxId      = trim($row[7] ?? '');
            $additionalInfo = trim($row[8] ?? '');
            $accountName    = trim($row[2] ?? '');

            $dateOnly = substr($txDate, 0, 10);
            if ($minDate === null || $dateOnly < $minDate) $minDate = $dateOnly;
            if ($maxDate === null || $dateOnly > $maxDate) $maxDate = $dateOnly;

            $totalTx++;

            $orderNumber     = !empty($merchantTxId) ? $merchantTxId : null;
            $orderId         = null;
            $cargoPaymentId  = null;
            $matchStatus     = 'unmatched';
            $matchMethod     = null;
            $orderTotal      = null;
            $expectedAmount  = null;

            if ($orderNumber) {
                // Cargo payments use ref "CARGO-<order_number>" — match against cargo_payments table.
                if (stripos($orderNumber, 'CARGO-') === 0) {
                    $rawOrderNumber = substr($orderNumber, 6); // strip "CARGO-"
                    $cargoMatch = lookupCargoPayment($db, $rawOrderNumber, 'bonum', $amount, 0.0);
                    $matchMethod = 'cargo_payment';

                    if ($cargoMatch) {
                        $cargoPaymentId = $cargoMatch['cargo_payment_id'];
                        $orderId        = $cargoMatch['order_id'];
                        $orderTotal     = $cargoMatch['cargo_amount'];
                        $expectedAmount = $cargoMatch['expected_amount'];
                        $matchStatus    = $cargoMatch['match_status'];

                        if ($matchStatus === 'matched') {
                            if ($cargoMatch['cp_status'] !== 'paid') {
                                markCargoPaymentPaid($db, $cargoPaymentId);
                            }
                            $matchedTx++;
                        } else {
                            $unmatchedTx++;
                        }
                    } else {
                        $matchStatus = 'no_order';
                        $unmatchedTx++;
                    }
                } else {
                    $oStmt = $db->prepare("SELECT id, order_number, total FROM orders WHERE order_number = ?");
                    $oStmt->execute([$orderNumber]);
                    $order = $oStmt->fetch();

                    if ($order) {
                        $orderId        = (int)$order['id'];
                        $orderTotal     = (float)$order['total'];
                        $expectedAmount = $orderTotal; // Bonum: no fee — amount matches exactly
                        $matchMethod    = 'order_number';
                        if (abs($amount - $orderTotal) < 1) {
                            $matchStatus = 'matched';
                            $matchedTx++;
                        } else {
                            $matchStatus = 'amount_mismatch';
                            $unmatchedTx++;
                        }
                    } else {
                        $matchStatus = 'no_order';
                        $matchMethod = 'order_number';
                        $unmatchedTx++;
                    }
                }
            } else {
                $unmatchedTx++;
            }

            $description = $additionalInfo ?: ($bonumTxId ? "Bonum #{$bonumTxId}" : '');

            $insertStmt->execute([
                $importId, $txDate, $amount, $description, $accountName,
                $orderNumber, $orderId, $cargoPaymentId, $matchStatus, $matchMethod, $orderTotal, $expectedAmount
            ]);
        }

        $db->prepare("UPDATE bank_statement_imports
            SET total_transactions = ?, matched_transactions = ?, unmatched_transactions = ?,
                bonum_transactions = ?, period_from = ?, period_to = ?
            WHERE id = ?")
           ->execute([$totalTx, $matchedTx, $unmatchedTx, $totalTx, $minDate, $maxDate, $importId]);

        $db->commit();

        return [
            'success'   => true,
            'import_id' => $importId,
            'message'   => "Боном хуулга амжилттай: Нийт {$totalTx} SETTLED гүйлгээ, {$matchedTx} тохирсон, {$unmatchedTx} зөрүүтэй",
        ];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Алдаа: ' . $e->getMessage()];
    }
}

// ── Load data for display ──
$importId     = (int)($_GET['import_id'] ?? 0);
$filterStatus  = $_GET['match'] ?? '';
$filterPayment = $_GET['payment'] ?? '';
$filterSearch  = trim($_GET['q'] ?? '');

// List of imports
$imports = $db->query("SELECT * FROM bank_statement_imports ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Load import record first so we can determine valid tabs
$import       = null;
$transactions = [];
$summary      = [];
$transferSummary = [];
$bonumSummary    = [];

if ($importId) {
    $stmt = $db->prepare("SELECT * FROM bank_statement_imports WHERE id = ?");
    $stmt->execute([$importId]);
    $import = $stmt->fetch();
}

// Determine source and active tab
$importSource = $import ? ($import['source'] ?? 'khan_bank') : 'khan_bank';
$defaultTab   = $importSource === 'bonum' ? 'bonum' : 'qpay';
$validTabs    = $importSource === 'bonum' ? ['bonum'] : ['qpay', 'transfer'];
$activeTab    = in_array($_GET['tab'] ?? '', $validTabs) ? $_GET['tab'] : $defaultTab;

if ($import) {
    // Build WHERE based on active tab
    if ($activeTab === 'bonum') {
        $where = "bt.import_id = ? AND bt.is_bonum = 1";
    } elseif ($activeTab === 'transfer') {
        $where = "bt.import_id = ? AND bt.is_transfer = 1";
    } else {
        $where = "bt.import_id = ? AND bt.is_qpay = 1";
    }
    $params = [$importId];

    if ($filterStatus) {
        if ($filterStatus === 'ignored') {
            $where .= " AND bt.match_status = 'ignored'";
        } elseif ($filterStatus === 'cargo') {
            $where .= " AND bt.match_method = 'cargo_payment'";
        } else {
            $where .= " AND bt.match_status = ?";
            $params[] = $filterStatus;
        }
    }
    if ($filterPayment) {
        $where .= " AND o.payment_status = ?";
        $params[] = $filterPayment;
    }
    if ($filterSearch) {
        $where .= " AND (bt.order_number LIKE ? OR bt.description LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
        $params[] = "%{$filterSearch}%";
        $params[] = "%{$filterSearch}%";
        $params[] = "%{$filterSearch}%";
        $params[] = "%{$filterSearch}%";
    }

    $txStmt = $db->prepare("SELECT bt.*, o.customer_name, o.customer_phone, o.payment_status, o.status as order_status
        FROM bank_transactions bt
        LEFT JOIN orders o ON bt.order_id = o.id
        WHERE {$where}
        ORDER BY bt.transaction_date ASC");
    $txStmt->execute($params);
    $transactions = $txStmt->fetchAll();

    // QPay summary stats
    $sumStmt = $db->prepare("SELECT
        SUM(credit_amount) as total_received,
        SUM(qpay_charge) as total_fees,
        SUM(order_total) as total_order_amount,
        COUNT(*) as total_qpay,
        SUM(CASE WHEN match_status = 'matched' THEN 1 ELSE 0 END) as matched_count,
        SUM(CASE WHEN match_status = 'amount_mismatch' THEN 1 ELSE 0 END) as mismatch_count,
        SUM(CASE WHEN match_status = 'no_order' THEN 1 ELSE 0 END) as no_order_count,
        SUM(CASE WHEN match_method = 'cargo_payment' THEN 1 ELSE 0 END) as cargo_count,
        SUM(CASE WHEN match_method = 'cargo_payment' AND match_status = 'matched' THEN 1 ELSE 0 END) as cargo_matched_count
        FROM bank_transactions WHERE import_id = ? AND is_qpay = 1");
    $sumStmt->execute([$importId]);
    $summary = $sumStmt->fetch();

    // Transfer summary stats
    $tSumStmt = $db->prepare("SELECT
        SUM(credit_amount) as total_received,
        COUNT(*) as total_transfers,
        SUM(CASE WHEN match_status = 'matched' THEN 1 ELSE 0 END) as matched_count,
        SUM(CASE WHEN match_status IN ('unmatched') THEN 1 ELSE 0 END) as unmatched_count,
        SUM(CASE WHEN match_status = 'amount_mismatch' THEN 1 ELSE 0 END) as mismatch_count,
        SUM(CASE WHEN match_status = 'no_order' THEN 1 ELSE 0 END) as no_order_count,
        SUM(CASE WHEN match_status = 'ignored' THEN 1 ELSE 0 END) as ignored_count
        FROM bank_transactions WHERE import_id = ? AND is_transfer = 1");
    $tSumStmt->execute([$importId]);
    $transferSummary = $tSumStmt->fetch();

    // Bonum summary stats
    $bSumStmt = $db->prepare("SELECT
        SUM(credit_amount) as total_received,
        COUNT(*) as total_bonum,
        SUM(CASE WHEN match_status = 'matched' THEN 1 ELSE 0 END) as matched_count,
        SUM(CASE WHEN match_status = 'unmatched' THEN 1 ELSE 0 END) as unmatched_count,
        SUM(CASE WHEN match_status = 'amount_mismatch' THEN 1 ELSE 0 END) as mismatch_count,
        SUM(CASE WHEN match_status = 'no_order' THEN 1 ELSE 0 END) as no_order_count,
        SUM(CASE WHEN match_status = 'ignored' THEN 1 ELSE 0 END) as ignored_count,
        SUM(CASE WHEN match_method = 'cargo_payment' THEN 1 ELSE 0 END) as cargo_count,
        SUM(CASE WHEN match_method = 'cargo_payment' AND match_status = 'matched' THEN 1 ELSE 0 END) as cargo_matched_count
        FROM bank_transactions WHERE import_id = ? AND is_bonum = 1");
    $bSumStmt->execute([$importId]);
    $bonumSummary = $bSumStmt->fetch();
}

// ── Delete import ──
if (isset($_GET['delete_import']) && hasRole('super_admin', 'admin')) {
    $delId = (int)$_GET['delete_import'];
    $db->prepare("DELETE FROM bank_transactions WHERE import_id = ?")->execute([$delId]);
    $db->prepare("DELETE FROM bank_statement_imports WHERE id = ?")->execute([$delId]);
    header('Location: index.php?page=reconciliation');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="flex-1 p-4 md:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= e($pageTitle) ?></h1>
            <p class="text-sm text-gray-500 mt-1">Банкны хуулгаас QPay, Боном болон шилжүүлгийн төлбөрийг захиалгатай тулгах</p>
        </div>
    </div>

    <?php if ($uploadError): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
        <?= e($uploadError) ?>
    </div>
    <?php endif; ?>

    <?php if ($uploadMessage): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4">
        <?= e($uploadMessage) ?>
    </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Банкны хуулга оруулах</h2>
        <form method="POST" enctype="multipart/form-data" class="flex items-end gap-4 flex-wrap">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Эх үүсвэр</label>
                <select name="statement_type" id="statementType" onchange="updateFileHint()"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="khan_bank">Khan Bank</option>
                    <option value="bonum">Bonum</option>
                </select>
            </div>
            <div class="flex-1 min-w-[220px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Файл</label>
                <input type="file" name="bank_csv" id="bankCsvInput" accept=".csv,.xlsx" required
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p id="fileHint" class="text-xs text-gray-400 mt-1">Khan Bank CSV эсвэл Excel (.xlsx) хуулга — QPay + шилжүүлэг</p>
            </div>
            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition">
                Импортлох
            </button>
        </form>
    </div>
    <script>
    function updateFileHint() {
        const type = document.getElementById('statementType').value;
        const hint = document.getElementById('fileHint');
        const input = document.getElementById('bankCsvInput');
        if (type === 'bonum') {
            hint.textContent = 'Bonum Excel (.xlsx) хуулга — зөвхөн SETTLED мөрүүдийг оруулна';
            input.accept = '.xlsx';
        } else {
            hint.textContent = 'Khan Bank CSV эсвэл Excel (.xlsx) хуулга — QPay + шилжүүлэг';
            input.accept = '.csv,.xlsx';
        }
    }
    </script>

    <?php if ($import): ?>
    <!-- Import Detail View -->
    <div class="mb-6">
        <div class="flex items-center gap-3 mb-4">
            <a href="index.php?page=reconciliation" class="text-blue-600 hover:text-blue-700 text-sm">&larr; Бүх импортууд</a>
            <span class="text-gray-400">|</span>
            <span class="text-sm text-gray-600"><?= e($import['filename']) ?> (<?= e($import['period_from']) ?> — <?= e($import['period_to']) ?>)</span>
            <button type="button" onclick="reprocessImport(<?= (int)$importId ?>, this)"
                    class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-600 text-white text-xs font-semibold rounded-lg hover:bg-teal-700 transition disabled:opacity-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Тохиролыг дахин шалгах
            </button>
        </div>

        <!-- Tab Buttons -->
        <?php
        $qpayCount     = (int)($summary['total_qpay'] ?? 0);
        $transferCount = (int)($transferSummary['total_transfers'] ?? 0);
        $bonumCount    = (int)($bonumSummary['total_bonum'] ?? 0);
        function tabUrl($tab) {
            global $importId;
            return "index.php?page=reconciliation&import_id={$importId}&tab={$tab}";
        }
        ?>
        <div class="flex gap-2 mb-5">
            <?php if ($importSource === 'bonum'): ?>
            <a href="<?= tabUrl('bonum') ?>"
               class="px-4 py-2 rounded-lg text-sm font-semibold transition <?= $activeTab === 'bonum' ? 'bg-purple-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Боном <span class="ml-1 <?= $activeTab === 'bonum' ? 'text-purple-200' : 'text-gray-400' ?>"><?= $bonumCount ?></span>
            </a>
            <?php else: ?>
            <a href="<?= tabUrl('qpay') ?>"
               class="px-4 py-2 rounded-lg text-sm font-semibold transition <?= $activeTab === 'qpay' ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                QPay <span class="ml-1 <?= $activeTab === 'qpay' ? 'text-blue-200' : 'text-gray-400' ?>"><?= $qpayCount ?></span>
            </a>
            <a href="<?= tabUrl('transfer') ?>"
               class="px-4 py-2 rounded-lg text-sm font-semibold transition <?= $activeTab === 'transfer' ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                Шилжүүлэг <span class="ml-1 <?= $activeTab === 'transfer' ? 'text-blue-200' : 'text-gray-400' ?>"><?= $transferCount ?></span>
            </a>
            <?php endif; ?>
        </div>

        <!-- Summary Cards -->
        <?php if ($activeTab === 'bonum'): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 border border-gray-200/60 shadow-sm">
                <div class="text-xs text-gray-500 mb-1">SETTLED гүйлгээ</div>
                <div class="text-xl font-bold text-gray-900"><?= (int)($bonumSummary['total_bonum'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-green-200 shadow-sm">
                <div class="text-xs text-green-600 mb-1">Тохирсон</div>
                <div class="text-xl font-bold text-green-700"><?= (int)($bonumSummary['matched_count'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-yellow-200 shadow-sm">
                <div class="text-xs text-yellow-600 mb-1">Дүн зөрүү</div>
                <div class="text-xl font-bold text-yellow-700"><?= (int)($bonumSummary['mismatch_count'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-red-200 shadow-sm">
                <div class="text-xs text-red-600 mb-1">Захиалга олдсонгүй</div>
                <div class="text-xl font-bold text-red-700"><?= (int)($bonumSummary['no_order_count'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-blue-200 shadow-sm">
                <div class="text-xs text-blue-600 mb-1">Нийт хүлээн авсан</div>
                <div class="text-lg font-bold text-blue-700"><?= number_format($bonumSummary['total_received'] ?? 0, 0) ?>₮</div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500 mb-1">Хэрэгцээгүй</div>
                <div class="text-xl font-bold text-gray-500"><?= (int)($bonumSummary['ignored_count'] ?? 0) ?></div>
            </div>
        </div>
        <?php elseif ($activeTab === 'transfer'): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 border border-gray-200/60 shadow-sm">
                <div class="text-xs text-gray-500 mb-1">Шилжүүлгийн гүйлгээ</div>
                <div class="text-xl font-bold text-gray-900"><?= (int)($transferSummary['total_transfers'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-green-200 shadow-sm">
                <div class="text-xs text-green-600 mb-1">Тохирсон</div>
                <div class="text-xl font-bold text-green-700"><?= (int)($transferSummary['matched_count'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-orange-200 shadow-sm">
                <div class="text-xs text-orange-600 mb-1">Тулгаагүй</div>
                <div class="text-xl font-bold text-orange-700"><?= (int)($transferSummary['unmatched_count'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-yellow-200 shadow-sm">
                <div class="text-xs text-yellow-600 mb-1">Дүн зөрүү</div>
                <div class="text-xl font-bold text-yellow-700"><?= (int)($transferSummary['mismatch_count'] ?? 0) ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-blue-200 shadow-sm">
                <div class="text-xs text-blue-600 mb-1">Нийт хүлээн авсан</div>
                <div class="text-lg font-bold text-blue-700"><?= number_format($transferSummary['total_received'] ?? 0, 0) ?>₮</div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
                <div class="text-xs text-gray-500 mb-1">Хэрэгцээгүй</div>
                <div class="text-xl font-bold text-gray-500"><?= (int)($transferSummary['ignored_count'] ?? 0) ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 border border-gray-200/60 shadow-sm">
                <div class="text-xs text-gray-500 mb-1">QPay гүйлгээ</div>
                <div class="text-xl font-bold text-gray-900"><?= (int)$summary['total_qpay'] ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-green-200 shadow-sm">
                <div class="text-xs text-green-600 mb-1">Тохирсон</div>
                <div class="text-xl font-bold text-green-700"><?= (int)$summary['matched_count'] ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-yellow-200 shadow-sm">
                <div class="text-xs text-yellow-600 mb-1">Дүн зөрүү</div>
                <div class="text-xl font-bold text-yellow-700"><?= (int)$summary['mismatch_count'] ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-red-200 shadow-sm">
                <div class="text-xs text-red-600 mb-1">Захиалга олдсонгүй</div>
                <div class="text-xl font-bold text-red-700"><?= (int)$summary['no_order_count'] ?></div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-blue-200 shadow-sm">
                <div class="text-xs text-blue-600 mb-1">Нийт хүлээн авсан</div>
                <div class="text-lg font-bold text-blue-700"><?= number_format($summary['total_received'] ?? 0, 0) ?>₮</div>
            </div>
            <div class="bg-white rounded-xl p-4 border border-purple-200 shadow-sm">
                <div class="text-xs text-purple-600 mb-1">QPay шимтгэл (1%)</div>
                <div class="text-lg font-bold text-purple-700"><?= number_format($summary['total_fees'] ?? 0, 0) ?>₮</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
            <input type="hidden" name="page" value="reconciliation">
            <input type="hidden" name="import_id" value="<?= $importId ?>">
            <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
            
            <!-- Search -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Хайх</label>
                <input type="text" name="q" value="<?= e($filterSearch) ?>" placeholder="<?= $activeTab === 'transfer' ? 'Утга, захиалга №, утас...' : 'Захиалга №, нэр, утас...' ?>"
                    class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm w-48 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Тулгалт filter -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Тулгалт</label>
                <select name="match" onchange="this.form.submit()"
                    class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500">
                    <?php if ($activeTab === 'qpay'): ?>
                    <option value="">Бүгд (<?= (int)$summary['total_qpay'] ?>)</option>
                    <option value="matched" <?= $filterStatus === 'matched' ? 'selected' : '' ?>>Тохирсон (<?= (int)$summary['matched_count'] ?>)</option>
                    <option value="amount_mismatch" <?= $filterStatus === 'amount_mismatch' ? 'selected' : '' ?>>Дүн зөрүү (<?= (int)$summary['mismatch_count'] ?>)</option>
                    <option value="no_order" <?= $filterStatus === 'no_order' ? 'selected' : '' ?>>Олдсонгүй (<?= (int)$summary['no_order_count'] ?>)</option>
                    <?php if ((int)($summary['cargo_count'] ?? 0) > 0): ?>
                    <option value="cargo" <?= $filterStatus === 'cargo' ? 'selected' : '' ?>>🚚 Ачаа (<?= (int)$summary['cargo_count'] ?>)</option>
                    <?php endif; ?>
                    <?php elseif ($activeTab === 'bonum'): ?>
                    <option value="">Бүгд (<?= (int)($bonumSummary['total_bonum'] ?? 0) ?>)</option>
                    <option value="matched" <?= $filterStatus === 'matched' ? 'selected' : '' ?>>Тохирсон (<?= (int)($bonumSummary['matched_count'] ?? 0) ?>)</option>
                    <option value="amount_mismatch" <?= $filterStatus === 'amount_mismatch' ? 'selected' : '' ?>>Дүн зөрүү (<?= (int)($bonumSummary['mismatch_count'] ?? 0) ?>)</option>
                    <option value="no_order" <?= $filterStatus === 'no_order' ? 'selected' : '' ?>>Олдсонгүй (<?= (int)($bonumSummary['no_order_count'] ?? 0) ?>)</option>
                    <option value="ignored" <?= $filterStatus === 'ignored' ? 'selected' : '' ?>>Хэрэгцээгүй (<?= (int)($bonumSummary['ignored_count'] ?? 0) ?>)</option>
                    <?php if ((int)($bonumSummary['cargo_count'] ?? 0) > 0): ?>
                    <option value="cargo" <?= $filterStatus === 'cargo' ? 'selected' : '' ?>>🚚 Ачаа (<?= (int)$bonumSummary['cargo_count'] ?>)</option>
                    <?php endif; ?>
                    <?php else: // transfer ?>
                    <option value="">Бүгд (<?= (int)($transferSummary['total_transfers'] ?? 0) ?>)</option>
                    <option value="matched" <?= $filterStatus === 'matched' ? 'selected' : '' ?>>Тохирсон (<?= (int)($transferSummary['matched_count'] ?? 0) ?>)</option>
                    <option value="unmatched" <?= $filterStatus === 'unmatched' ? 'selected' : '' ?>>Тулгаагүй (<?= (int)($transferSummary['unmatched_count'] ?? 0) ?>)</option>
                    <option value="amount_mismatch" <?= $filterStatus === 'amount_mismatch' ? 'selected' : '' ?>>Дүн зөрүү (<?= (int)($transferSummary['mismatch_count'] ?? 0) ?>)</option>
                    <option value="no_order" <?= $filterStatus === 'no_order' ? 'selected' : '' ?>>Олдсонгүй (<?= (int)($transferSummary['no_order_count'] ?? 0) ?>)</option>
                    <option value="ignored" <?= $filterStatus === 'ignored' ? 'selected' : '' ?>>Хэрэгцээгүй (<?= (int)($transferSummary['ignored_count'] ?? 0) ?>)</option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Төлөв filter -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Төлөв</label>
                <select name="payment" onchange="this.form.submit()"
                    class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500">
                    <option value="">Бүгд</option>
                    <option value="paid" <?= $filterPayment === 'paid' ? 'selected' : '' ?>>Төлсөн</option>
                    <option value="pending" <?= $filterPayment === 'pending' ? 'selected' : '' ?>>Хүлээгдэж буй</option>
                    <option value="refunded" <?= $filterPayment === 'refunded' ? 'selected' : '' ?>>Буцаалт</option>
                </select>
            </div>

            <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                Шүүх
            </button>
            <?php if ($filterStatus || $filterPayment || $filterSearch): ?>
            <a href="index.php?page=reconciliation&import_id=<?= $importId ?>&tab=<?= e($activeTab) ?>" class="px-4 py-1.5 bg-gray-100 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200 transition">
                Цэвэрлэх
            </a>
            <?php endif; ?>
        </form>

        <?php if ($activeTab === 'bonum'): ?>
        <!-- Bonum Transactions Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden" x-data="txManager()">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Огноо</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Захиалга №</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Үйлчлүүлэгч</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Захиалгын дүн</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Хүлээн авсан</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Тулгалт</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase sticky right-0 bg-gray-50 shadow-[-2px_0_4px_rgba(0,0,0,0.06)]">Үйлдэл</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-gray-400 py-8">Боном гүйлгээ олдсонгүй</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $tx):
                            $isCargo = ($tx['match_method'] ?? '') === 'cargo_payment';
                        ?>
                        <tr class="group hover:bg-gray-50 transition-colors <?= $isCargo ? 'bg-amber-50/30' : '' ?>" id="tx-row-<?= (int)$tx['id'] ?>">
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= e(substr($tx['transaction_date'], 0, 16)) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($tx['order_id']): ?>
                                <a href="index.php?page=order-detail&id=<?= $tx['order_id'] ?>&return=<?= urlencode('index.php?page=reconciliation&import_id=' . $importId . '&tab=bonum') ?>" class="text-blue-600 hover:underline font-mono font-medium">
                                    <?= e($tx['order_number']) ?>
                                </a>
                                <?php else: ?>
                                <span class="font-mono text-gray-400"><?= e($tx['order_number'] ?? '—') ?></span>
                                <?php endif; ?>
                                <?php if ($isCargo): ?>
                                <span class="inline-flex items-center px-1.5 py-0.5 ml-1 rounded text-[10px] font-medium bg-amber-100 text-amber-700">🚚 Ачаа</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                <?php if ($tx['customer_name']): ?>
                                    <?= e($tx['customer_name']) ?>
                                    <span class="text-gray-400 text-xs block"><?= e($tx['customer_phone'] ?? '') ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs"><?= e($tx['counter_account'] ?? '—') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700">
                                <?= $tx['order_total'] ? number_format($tx['order_total'], 0) . '₮' : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 whitespace-nowrap">
                                <?= number_format($tx['credit_amount'], 0) ?>₮
                                <?php if ($tx['order_total'] && abs($tx['credit_amount'] - $tx['order_total']) >= 1): ?>
                                <div class="text-[10px] text-red-500">(зөрүү: <?= number_format($tx['credit_amount'] - $tx['order_total'], 0) ?>₮)</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php
                                $statusColors = [
                                    'matched' => 'bg-green-100 text-green-800',
                                    'amount_mismatch' => 'bg-yellow-100 text-yellow-800',
                                    'no_order' => 'bg-red-100 text-red-800',
                                    'unmatched' => 'bg-orange-100 text-orange-800',
                                    'ignored' => 'bg-gray-100 text-gray-500',
                                ];
                                $statusLabels = [
                                    'matched' => 'Тохирсон',
                                    'amount_mismatch' => 'Дүн зөрүү',
                                    'no_order' => 'Олдсонгүй',
                                    'unmatched' => 'Тулгаагүй',
                                    'ignored' => 'Хэрэгцээгүй',
                                ];
                                $cls = $statusColors[$tx['match_status']] ?? 'bg-gray-100 text-gray-600';
                                $lbl = $statusLabels[$tx['match_status']] ?? $tx['match_status'];
                                ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>">
                                    <?= e($lbl) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap sticky right-0 bg-white shadow-[-2px_0_4px_rgba(0,0,0,0.06)] group-hover:bg-gray-50">
                                <?php if ($isCargo && $tx['match_status'] === 'matched'): ?>
                                <span class="text-xs text-amber-700">✓ Ачаа төлсөн</span>
                                <?php elseif ($isCargo && in_array($tx['match_status'], ['amount_mismatch', 'unmatched', 'no_order'])): ?>
                                <div class="flex items-center gap-1 justify-center flex-wrap">
                                    <?php if ($tx['cargo_payment_id']): ?>
                                    <button @click="markCargoPaid(<?= (int)$tx['id'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
                                        Ачаа төлсөн
                                    </button>
                                    <?php else: ?>
                                    <button @click="openMatch(<?= (int)$tx['id'] ?>, <?= $tx['credit_amount'] ?>, true)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
                                        Ачаа тохируулах
                                    </button>
                                    <?php endif; ?>
                                    <button @click="ignoreTx(<?= (int)$tx['id'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-gray-50 text-gray-500 hover:bg-gray-100 transition" title="Хэрэгцээгүй">✕</button>
                                </div>
                                <?php elseif ($tx['match_status'] === 'matched' && $tx['payment_status'] !== 'paid'): ?>
                                <button @click="markPaid(<?= (int)$tx['id'] ?>, '<?= e($tx['order_number']) ?>')"
                                    class="text-xs font-medium px-2 py-1 rounded bg-green-50 text-green-700 hover:bg-green-100 transition">
                                    Төлсөн болгох
                                </button>
                                <?php elseif ($tx['match_status'] === 'matched' && $tx['payment_status'] === 'paid'): ?>
                                <span class="text-xs text-green-600">✓ Төлсөн</span>
                                <?php elseif ($tx['match_status'] === 'ignored'): ?>
                                <span class="text-xs text-gray-400">Хэрэгцээгүй</span>
                                <?php elseif (in_array($tx['match_status'], ['unmatched', 'no_order', 'amount_mismatch'])): ?>
                                <div class="flex items-center gap-1 justify-center flex-wrap">
                                    <button @click="openMatch(<?= (int)$tx['id'] ?>, <?= $tx['credit_amount'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
                                        Тохируулах
                                    </button>
                                    <button @click="ignoreTx(<?= (int)$tx['id'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-gray-50 text-gray-500 hover:bg-gray-100 transition" title="Хэрэгцээгүй">
                                        ✕
                                    </button>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($transactions)): ?>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-semibold">
                            <td class="px-4 py-3" colspan="3">Нийт: <?= count($transactions) ?> гүйлгээ</td>
                            <td class="px-4 py-3 text-right font-mono">
                                <?= number_format(array_sum(array_column($transactions, 'order_total')), 0) ?>₮
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-blue-700">
                                <?= number_format(array_sum(array_column($transactions, 'credit_amount')), 0) ?>₮
                            </td>
                            <td colspan="1"></td>
                            <td class="sticky right-0 bg-gray-50"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Manual Match Modal -->
            <div x-show="matchTxId" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/30"
                 @keydown.escape.window="closeMatch()">
                <div class="bg-white rounded-2xl shadow-xl border border-gray-200 w-full max-w-lg mx-4 p-6" @click.outside="closeMatch()">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Захиалгатай тулгах</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Боном гүйлгээний дүн: <span class="font-semibold text-gray-900" x-text="matchAmount ? matchAmount.toLocaleString() + '₮' : ''"></span>
                    </p>

                    <div class="relative mb-4">
                        <input type="text" x-model="searchQuery" @input.debounce.300ms="searchOrders()"
                            placeholder="Захиалга №, утас, нэр..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <div x-show="searching" class="absolute right-3 top-3">
                            <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="max-h-64 overflow-y-auto space-y-2 mb-4" x-show="results.length > 0">
                        <template x-for="order in results" :key="order.id">
                            <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 transition cursor-pointer"
                                 @click="matchIsCargo ? linkCargo(order) : linkOrder(order)">
                                <div>
                                    <span class="font-mono font-semibold text-blue-700 text-sm" x-text="order.order_number"></span>
                                    <span class="text-xs text-gray-500 ml-2" x-text="order.customer_name"></span>
                                    <span class="text-xs text-gray-400 ml-1" x-text="order.customer_phone"></span>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        <span x-text="order.payment_method"></span> · <span x-text="order.payment_status"></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-semibold text-sm" x-text="Number(order.total).toLocaleString() + '₮'"
                                         :class="Math.abs(order.total - matchAmount) < 1 ? 'text-green-700' : 'text-red-600'"></div>
                                    <div class="text-[10px]" x-show="Math.abs(order.total - matchAmount) >= 1"
                                         :class="order.total > matchAmount ? 'text-red-500' : 'text-orange-500'"
                                         x-text="'Зөрүү: ' + (matchAmount - order.total).toLocaleString() + '₮'"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="searchQuery.length >= 2 && results.length === 0 && !searching" class="text-center text-gray-400 text-sm py-4">
                        Захиалга олдсонгүй
                    </div>

                    <div x-show="matchError" class="text-red-600 text-sm mb-3" x-text="matchError"></div>

                    <div class="flex justify-end gap-2">
                        <button @click="closeMatch()" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                            Болих
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($activeTab === 'transfer'): ?>
        <!-- Transfer Transactions Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden" x-data="txManager()">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Огноо</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase min-w-[200px]">Гүйлгээний утга</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Дүн</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Тохирсон захиалга</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Арга</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Тулгалт</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase sticky right-0 bg-gray-50 shadow-[-2px_0_4px_rgba(0,0,0,0.06)]">Үйлдэл</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-gray-400 py-8">Шилжүүлгийн гүйлгээ олдсонгүй</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr class="group hover:bg-gray-50 transition-colors" id="tx-row-<?= (int)$tx['id'] ?>">
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= e(substr($tx['transaction_date'], 0, 16)) ?></td>
                            <td class="px-4 py-3 text-gray-700">
                                <div class="max-w-xs" title="<?= e($tx['description']) ?>">
                                    <span class="text-sm"><?= e(mb_strimwidth($tx['description'], 0, 80, '...')) ?></span>
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5"><?= e($tx['counter_account']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 whitespace-nowrap">
                                <?= number_format($tx['credit_amount'], 0) ?>₮
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($tx['order_id']): ?>
                                <a href="index.php?page=order-detail&id=<?= $tx['order_id'] ?>&return=<?= urlencode('index.php?page=reconciliation&import_id=' . $importId . '&tab=transfer') ?>" class="text-blue-600 hover:underline font-mono font-medium">
                                    <?= e($tx['order_number']) ?>
                                </a>
                                <?php if ($tx['customer_name']): ?>
                                <span class="text-xs text-gray-400 block"><?= e($tx['customer_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($tx['order_total']): ?>
                                <span class="text-xs text-gray-500 block"><?= number_format($tx['order_total'], 0) ?>₮
                                    <?php if (abs($tx['credit_amount'] - $tx['order_total']) >= 1): ?>
                                    <span class="text-red-500">(зөрүү: <?= number_format($tx['credit_amount'] - $tx['order_total'], 0) ?>₮)</span>
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php
                                $methodLabels = [
                                    'order_number' => ['Дугаараар', 'text-blue-600'],
                                    'amount' => ['Дүнгээр', 'text-green-600'],
                                    'phone_amount' => ['Утас+дүн', 'text-purple-600'],
                                    'phone' => ['Утасаар', 'text-indigo-600'],
                                    'time_amount' => ['Цаг+дүн', 'text-teal-600'],
                                    'manual' => ['Гараар', 'text-orange-600'],
                                    'manual_create' => ['Үүсгэсэн', 'text-pink-600'],
                                    'ignored' => ['Хэрэгцээгүй', 'text-gray-400'],
                                ];
                                $method = $tx['match_method'] ?? '';
                                ?>
                                <?php if ($method && isset($methodLabels[$method])): ?>
                                <span class="text-xs font-medium <?= $methodLabels[$method][1] ?>"><?= e($methodLabels[$method][0]) ?></span>
                                <?php else: ?>
                                <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php
                                $statusColors = [
                                    'matched' => 'bg-green-100 text-green-800',
                                    'amount_mismatch' => 'bg-yellow-100 text-yellow-800',
                                    'no_order' => 'bg-red-100 text-red-800',
                                    'unmatched' => 'bg-orange-100 text-orange-800',
                                    'ignored' => 'bg-gray-100 text-gray-500',
                                ];
                                $statusLabels = [
                                    'matched' => 'Тохирсон',
                                    'amount_mismatch' => 'Дүн зөрүү',
                                    'no_order' => 'Олдсонгүй',
                                    'unmatched' => 'Тулгаагүй',
                                    'ignored' => 'Хэрэгцээгүй',
                                ];
                                $cls = $statusColors[$tx['match_status']] ?? 'bg-gray-100 text-gray-600';
                                $lbl = $statusLabels[$tx['match_status']] ?? $tx['match_status'];
                                ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>">
                                    <?= e($lbl) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap sticky right-0 bg-white shadow-[-2px_0_4px_rgba(0,0,0,0.06)] group-hover:bg-gray-50">
                                <?php if ($tx['match_status'] === 'matched' && $tx['payment_status'] !== 'paid'): ?>
                                <button @click="markPaid(<?= (int)$tx['id'] ?>, '<?= e($tx['order_number']) ?>')"
                                    class="text-xs font-medium px-2 py-1 rounded bg-green-50 text-green-700 hover:bg-green-100 transition">
                                    Төлсөн болгох
                                </button>
                                <?php elseif ($tx['match_status'] === 'matched' && $tx['payment_status'] === 'paid'): ?>
                                <span class="text-xs text-green-600">✓ Төлсөн</span>
                                <?php elseif ($tx['match_status'] === 'ignored'): ?>
                                <span class="text-xs text-gray-400">Хэрэгцээгүй</span>
                                <?php elseif (in_array($tx['match_status'], ['unmatched', 'no_order', 'amount_mismatch'])): ?>
                                <div class="flex items-center gap-1 justify-center flex-wrap">
                                    <button @click="openMatch(<?= (int)$tx['id'] ?>, <?= $tx['credit_amount'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
                                        Тохируулах
                                    </button>
                                    <a href="index.php?page=order-create&from_tx=<?= (int)$tx['id'] ?>"
                                        class="text-xs font-medium px-2 py-1 rounded bg-pink-50 text-pink-700 hover:bg-pink-100 transition" title="Энэ гүйлгээгээс захиалга үүсгэх">
                                        + Захиалга
                                    </a>
                                    <button @click="ignoreTx(<?= (int)$tx['id'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-gray-50 text-gray-500 hover:bg-gray-100 transition" title="Хэрэгцээгүй">
                                        ✕
                                    </button>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($transactions)): ?>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-semibold">
                            <td class="px-4 py-3" colspan="2">Нийт: <?= count($transactions) ?> шилжүүлэг</td>
                            <td class="px-4 py-3 text-right font-mono text-blue-700">
                                <?= number_format(array_sum(array_column($transactions, 'credit_amount')), 0) ?>₮
                            </td>
                            <td colspan="3"></td>
                            <td class="sticky right-0 bg-gray-50"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Manual Match Modal -->
            <div x-show="matchTxId" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/30"
                 @keydown.escape.window="closeMatch()">
                <div class="bg-white rounded-2xl shadow-xl border border-gray-200 w-full max-w-lg mx-4 p-6" @click.outside="closeMatch()">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Захиалгатай тулгах</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Шилжүүлгийн дүн: <span class="font-semibold text-gray-900" x-text="matchAmount ? matchAmount.toLocaleString() + '₮' : ''"></span>
                    </p>
                    
                    <div class="relative mb-4">
                        <input type="text" x-model="searchQuery" @input.debounce.300ms="searchOrders()"
                            placeholder="Захиалга №, утас, нэр..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <div x-show="searching" class="absolute right-3 top-3">
                            <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div class="max-h-64 overflow-y-auto space-y-2 mb-4" x-show="results.length > 0">
                        <template x-for="order in results" :key="order.id">
                            <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 transition cursor-pointer"
                                 @click="matchIsCargo ? linkCargo(order) : linkOrder(order)">
                                <div>
                                    <span class="font-mono font-semibold text-blue-700 text-sm" x-text="order.order_number"></span>
                                    <span class="text-xs text-gray-500 ml-2" x-text="order.customer_name"></span>
                                    <span class="text-xs text-gray-400 ml-1" x-text="order.customer_phone"></span>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        <span x-text="order.payment_method"></span> · <span x-text="order.payment_status"></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-semibold text-sm" x-text="Number(order.total).toLocaleString() + '₮'"
                                         :class="Math.abs(order.total - matchAmount) < 1 ? 'text-green-700' : 'text-red-600'"></div>
                                    <div class="text-[10px]" x-show="Math.abs(order.total - matchAmount) >= 1"
                                         :class="order.total > matchAmount ? 'text-red-500' : 'text-orange-500'"
                                         x-text="'Зөрүү: ' + (matchAmount - order.total).toLocaleString() + '₮'"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="searchQuery.length >= 2 && results.length === 0 && !searching" class="text-center text-gray-400 text-sm py-4">
                        Захиалга олдсонгүй
                    </div>

                    <div x-show="matchError" class="text-red-600 text-sm mb-3" x-text="matchError"></div>

                    <div class="flex justify-end gap-2">
                        <button @click="closeMatch()" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                            Болих
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- QPay Transactions Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden" x-data="txManager()">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Огноо</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Захиалга №</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Үйлчлүүлэгч</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Захиалгын дүн</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Хүлээгдэх (99%)</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Банкинд ирсэн</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">QPay шимтгэл</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Төлөв</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Тулгалт</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase sticky right-0 bg-gray-50 shadow-[-2px_0_4px_rgba(0,0,0,0.06)]">Үйлдэл</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-gray-400 py-8">QPay гүйлгээ олдсонгүй</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $tx):
                            $isCargo = ($tx['match_method'] ?? '') === 'cargo_payment';
                        ?>
                        <tr class="group hover:bg-gray-50 transition-colors <?= $isCargo ? 'bg-amber-50/30' : '' ?>" id="tx-row-<?= (int)$tx['id'] ?>">
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= e(substr($tx['transaction_date'], 0, 16)) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($tx['order_id']): ?>
                                <a href="index.php?page=order-detail&id=<?= $tx['order_id'] ?>&return=<?= urlencode('index.php?page=reconciliation&import_id=' . $importId . '&tab=qpay') ?>" class="text-blue-600 hover:underline font-mono font-medium">
                                    <?= e($tx['order_number']) ?>
                                </a>
                                <?php else: ?>
                                <span class="font-mono text-gray-400"><?= e($tx['order_number']) ?></span>
                                <?php endif; ?>
                                <?php if ($isCargo): ?>
                                <span class="inline-flex items-center px-1.5 py-0.5 ml-1 rounded text-[10px] font-medium bg-amber-100 text-amber-700">🚚 Ачаа</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                <?php if ($tx['customer_name']): ?>
                                    <?= e($tx['customer_name']) ?>
                                    <span class="text-gray-400 text-xs block"><?= e($tx['customer_phone'] ?? '') ?></span>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700">
                                <?= $tx['order_total'] ? number_format($tx['order_total'], 0) . '₮' : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-500">
                                <?= $tx['expected_amount'] ? number_format($tx['expected_amount'], 2) . '₮' : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900">
                                <?= number_format($tx['credit_amount'], 2) ?>₮
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-orange-600">
                                <?= $tx['qpay_charge'] !== null ? number_format($tx['qpay_charge'], 0) . '₮' : '—' ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($tx['payment_status'] === 'paid'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Төлсөн</span>
                                <?php elseif ($tx['payment_status'] === 'pending'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Хүлээгдэж буй</span>
                                <?php elseif ($tx['payment_status']): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600"><?= e($tx['payment_status']) ?></span>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php
                                $statusColors = [
                                    'matched' => 'bg-green-100 text-green-800',
                                    'amount_mismatch' => 'bg-yellow-100 text-yellow-800',
                                    'no_order' => 'bg-red-100 text-red-800',
                                    'unmatched' => 'bg-gray-100 text-gray-600',
                                    'ignored' => 'bg-gray-100 text-gray-500',
                                ];
                                $statusLabels = [
                                    'matched' => 'Тохирсон',
                                    'amount_mismatch' => 'Дүн зөрүү',
                                    'no_order' => 'Олдсонгүй',
                                    'unmatched' => 'Тулгаагүй',
                                    'ignored' => 'Хэрэгцээгүй',
                                ];
                                $cls = $statusColors[$tx['match_status']] ?? 'bg-gray-100 text-gray-600';
                                $lbl = $statusLabels[$tx['match_status']] ?? $tx['match_status'];
                                ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>">
                                    <?= e($lbl) ?>
                                </span>
                                <?php if ($tx['match_status'] === 'amount_mismatch'): ?>
                                    <?php $diff = $tx['credit_amount'] - ($tx['expected_amount'] ?? 0); ?>
                                    <div class="text-[10px] mt-0.5 <?= $diff >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $diff >= 0 ? '+' : '' ?><?= number_format($diff, 2) ?>₮
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap sticky right-0 bg-white shadow-[-2px_0_4px_rgba(0,0,0,0.06)] group-hover:bg-gray-50">
                                <?php if ($isCargo && $tx['match_status'] === 'matched'): ?>
                                <span class="text-xs text-amber-700">✓ Ачаа төлсөн</span>
                                <?php elseif ($isCargo && in_array($tx['match_status'], ['amount_mismatch', 'unmatched', 'no_order'])): ?>
                                <div class="flex items-center gap-1 justify-center flex-wrap">
                                    <?php if ($tx['cargo_payment_id']): ?>
                                    <button @click="markCargoPaid(<?= (int)$tx['id'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
                                        Ачаа төлсөн
                                    </button>
                                    <?php else: ?>
                                    <button @click="openMatch(<?= (int)$tx['id'] ?>, <?= $tx['credit_amount'] ?>, true)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
                                        Ачаа тохируулах
                                    </button>
                                    <?php endif; ?>
                                    <button @click="ignoreTx(<?= (int)$tx['id'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-gray-50 text-gray-500 hover:bg-gray-100 transition" title="Хэрэгцээгүй">✕</button>
                                </div>
                                <?php elseif ($tx['match_status'] === 'matched' && $tx['payment_status'] !== 'paid'): ?>
                                <button @click="markPaid(<?= (int)$tx['id'] ?>, '<?= e($tx['order_number']) ?>')"
                                    class="text-xs font-medium px-2 py-1 rounded bg-green-50 text-green-700 hover:bg-green-100 transition">
                                    Төлсөн болгох
                                </button>
                                <?php elseif ($tx['match_status'] === 'matched' && $tx['payment_status'] === 'paid'): ?>
                                <span class="text-xs text-green-600">✓ Төлсөн</span>
                                <?php elseif ($tx['match_status'] === 'ignored'): ?>
                                <span class="text-xs text-gray-400">Хэрэгцээгүй</span>
                                <?php elseif (in_array($tx['match_status'], ['unmatched', 'no_order', 'amount_mismatch'])): ?>
                                <div class="flex items-center gap-1 justify-center">
                                    <button @click="openMatch(<?= (int)$tx['id'] ?>, <?= $tx['credit_amount'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
                                        Тохируулах
                                    </button>
                                    <button @click="ignoreTx(<?= (int)$tx['id'] ?>)"
                                        class="text-xs font-medium px-2 py-1 rounded bg-gray-50 text-gray-500 hover:bg-gray-100 transition" title="Хэрэгцээгүй">
                                        ✕
                                    </button>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($transactions)): ?>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-semibold">
                            <td class="px-4 py-3" colspan="3">Нийт: <?= count($transactions) ?> QPay гүйлгээ</td>
                            <td class="px-4 py-3 text-right font-mono">
                                <?= number_format(array_sum(array_column($transactions, 'order_total')), 0) ?>₮
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-500">
                                <?= number_format(array_sum(array_column($transactions, 'expected_amount')), 2) ?>₮
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-blue-700">
                                <?= number_format(array_sum(array_column($transactions, 'credit_amount')), 2) ?>₮
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-orange-600">
                                <?= number_format(array_sum(array_filter(array_column($transactions, 'qpay_charge'))), 0) ?>₮
                            </td>
                            <td colspan="2"></td>
                            <td class="sticky right-0 bg-gray-50"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Manual Match Modal (shared txManager component) -->
            <div x-show="matchTxId" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/30"
                 @keydown.escape.window="closeMatch()">
                <div class="bg-white rounded-2xl shadow-xl border border-gray-200 w-full max-w-lg mx-4 p-6" @click.outside="closeMatch()">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Захиалгатай тулгах</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Гүйлгээний дүн: <span class="font-semibold text-gray-900" x-text="matchAmount ? matchAmount.toLocaleString() + '₮' : ''"></span>
                    </p>

                    <div class="relative mb-4">
                        <input type="text" x-model="searchQuery" @input.debounce.300ms="searchOrders()"
                            placeholder="Захиалга №, утас, нэр..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <div x-show="searching" class="absolute right-3 top-3">
                            <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="max-h-64 overflow-y-auto space-y-2 mb-4" x-show="results.length > 0">
                        <template x-for="order in results" :key="order.id">
                            <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 transition cursor-pointer"
                                 @click="matchIsCargo ? linkCargo(order) : linkOrder(order)">
                                <div>
                                    <span class="font-mono font-semibold text-blue-700 text-sm" x-text="order.order_number"></span>
                                    <span class="text-xs text-gray-500 ml-2" x-text="order.customer_name"></span>
                                    <span class="text-xs text-gray-400 ml-1" x-text="order.customer_phone"></span>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        <span x-text="order.payment_method"></span> · <span x-text="order.payment_status"></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-semibold text-sm" x-text="Number(order.total).toLocaleString() + '₮'"
                                         :class="Math.abs(order.total - matchAmount) < 1 ? 'text-green-700' : 'text-red-600'"></div>
                                    <div class="text-[10px]" x-show="Math.abs(order.total - matchAmount) >= 1"
                                         :class="order.total > matchAmount ? 'text-red-500' : 'text-orange-500'"
                                         x-text="'Зөрүү: ' + (matchAmount - order.total).toLocaleString() + '₮'"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="searchQuery.length >= 2 && results.length === 0 && !searching" class="text-center text-gray-400 text-sm py-4">
                        Захиалга олдсонгүй
                    </div>

                    <div x-show="matchError" class="text-red-600 text-sm mb-3" x-text="matchError"></div>

                    <div class="flex justify-end gap-2">
                        <button @click="closeMatch()" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                            Болих
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script>
        async function reprocessImport(importId, btn) {
            if (!confirm('Тохиролгүй гүйлгээнүүдийг шинэ дүрмээр дахин шалгах уу?')) return;
            btn.disabled = true;
            const original = btn.innerHTML;
            btn.innerHTML = 'Шалгаж байна...';
            try {
                const fd = new FormData();
                fd.append('import_id', importId);
                const r = await fetch('index.php?page=reconciliation&action=reprocess', { method: 'POST', body: fd });
                const data = await r.json();
                if (data.ok) {
                    alert(`${data.matched} / ${data.total} гүйлгээ шинээр тохирлоо.`);
                    location.reload();
                } else {
                    alert('Алдаа: ' + (data.error || 'Тодорхойгүй'));
                }
            } catch (e) {
                alert('Серверийн алдаа');
            }
            btn.disabled = false;
            btn.innerHTML = original;
        }

        function txManager() {
            return {
                matchTxId: null,
                matchAmount: 0,
                matchIsCargo: false,
                searchQuery: '',
                searching: false,
                results: [],
                matchError: '',

                openMatch(txId, amount, isCargo = false) {
                    this.matchTxId = txId;
                    this.matchAmount = amount;
                    this.matchIsCargo = isCargo;
                    this.searchQuery = '';
                    this.results = [];
                    this.matchError = '';
                },
                closeMatch() {
                    this.matchTxId = null;
                    this.matchIsCargo = false;
                    this.searchQuery = '';
                    this.results = [];
                    this.matchError = '';
                },
                async searchOrders() {
                    if (this.searchQuery.length < 2) { this.results = []; return; }
                    this.searching = true;
                    try {
                        const r = await fetch(`index.php?page=reconciliation&action=search-orders&q=${encodeURIComponent(this.searchQuery)}`);
                        this.results = await r.json();
                    } catch (e) {
                        this.results = [];
                    }
                    this.searching = false;
                },
                async linkOrder(order) {
                    this.matchError = '';
                    try {
                        const fd = new FormData();
                        fd.append('transaction_id', this.matchTxId);
                        fd.append('action_type', 'link');
                        fd.append('order_number', order.order_number);
                        const r = await fetch('index.php?page=reconciliation&action=manual-match', { method: 'POST', body: fd });
                        const data = await r.json();
                        if (data.ok) {
                            location.reload();
                        } else {
                            this.matchError = data.error || 'Алдаа гарлаа';
                        }
                    } catch (e) {
                        this.matchError = 'Сервертэй холбогдоход алдаа гарлаа';
                    }
                },
                async ignoreTx(txId) {
                    if (!confirm('Энэ гүйлгээг хэрэгцээгүй гэж тэмдэглэх үү?')) return;
                    const fd = new FormData();
                    fd.append('transaction_id', txId);
                    fd.append('action_type', 'ignore');
                    const r = await fetch('index.php?page=reconciliation&action=manual-match', { method: 'POST', body: fd });
                    const data = await r.json();
                    if (data.ok) location.reload();
                    else alert(data.error || 'Алдаа');
                },
                async markPaid(txId, orderNumber) {
                    if (!confirm(orderNumber + ' захиалгыг төлсөн гэж тэмдэглэх үү?')) return;
                    const fd = new FormData();
                    fd.append('transaction_id', txId);
                    fd.append('action_type', 'mark_paid');
                    const r = await fetch('index.php?page=reconciliation&action=manual-match', { method: 'POST', body: fd });
                    const data = await r.json();
                    if (data.ok) location.reload();
                    else alert(data.error || 'Алдаа');
                },
                async markCargoPaid(txId) {
                    if (!confirm('Энэ ачааны төлбөрийг төлсөн гэж тэмдэглэх үү?')) return;
                    const fd = new FormData();
                    fd.append('transaction_id', txId);
                    fd.append('action_type', 'mark_cargo_paid');
                    const r = await fetch('index.php?page=reconciliation&action=manual-match', { method: 'POST', body: fd });
                    const data = await r.json();
                    if (data.ok) location.reload();
                    else alert(data.error || 'Алдаа');
                },
                async linkCargo(order) {
                    this.matchError = '';
                    try {
                        const fd = new FormData();
                        fd.append('transaction_id', this.matchTxId);
                        fd.append('action_type', 'link_cargo');
                        fd.append('order_number', order.order_number);
                        const r = await fetch('index.php?page=reconciliation&action=manual-match', { method: 'POST', body: fd });
                        const data = await r.json();
                        if (data.ok) location.reload();
                        else this.matchError = data.error || 'Алдаа гарлаа';
                    } catch (e) {
                        this.matchError = 'Сервертэй холбогдоход алдаа гарлаа';
                    }
                }
            };
        }
        </script>
    </div>

    <?php else: ?>
    <!-- Imports List -->
    <?php if (!empty($imports)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Импортын түүх</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Огноо</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Файл</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Данс</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Интервал</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Нийт</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">QPay / Боном</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Тохирсон</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Зөрүүтэй</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Шилжүүлэг</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase sticky right-0 bg-gray-50 shadow-[-2px_0_4px_rgba(0,0,0,0.06)]">Үйлдэл</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($imports as $imp): ?>
                    <tr class="group hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= e(substr($imp['created_at'], 0, 16)) ?></td>
                        <td class="px-4 py-3 text-gray-700 font-medium">
                            <?= e($imp['filename']) ?>
                            <?php if (($imp['source'] ?? 'khan_bank') === 'bonum'): ?>
                            <span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-700">Боном</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-500 font-mono"><?= e($imp['account_number']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($imp['period_from']) ?> — <?= e($imp['period_to']) ?></td>
                        <td class="px-4 py-3 text-center"><?= (int)$imp['total_transactions'] ?></td>
                        <td class="px-4 py-3 text-center font-semibold">
                            <?php if (($imp['source'] ?? 'khan_bank') === 'bonum'): ?>
                            <span class="text-purple-700"><?= (int)($imp['bonum_transactions'] ?? 0) ?></span>
                            <?php else: ?>
                            <span class="text-blue-700"><?= (int)$imp['qpay_transactions'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><?= (int)$imp['matched_transactions'] ?></span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ((int)$imp['unmatched_transactions'] > 0): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><?= (int)$imp['unmatched_transactions'] ?></span>
                            <?php else: ?>
                            <span class="text-gray-300">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php $tTx = (int)($imp['transfer_transactions'] ?? 0); $tM = (int)($imp['transfer_matched'] ?? 0); ?>
                            <?php if ($tTx > 0): ?>
                            <span class="text-xs">
                                <span class="font-semibold text-purple-700"><?= $tTx ?></span>
                                <?php if ($tM > 0): ?>
                                <span class="text-green-600">(<?= $tM ?>✓)</span>
                                <?php endif; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-300">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap sticky right-0 bg-white shadow-[-2px_0_4px_rgba(0,0,0,0.06)] group-hover:bg-gray-50">
                            <a href="index.php?page=reconciliation&import_id=<?= $imp['id'] ?>" class="text-blue-600 hover:underline text-xs font-medium mr-2">Харах</a>
                            <a href="index.php?page=reconciliation&delete_import=<?= $imp['id'] ?>" onclick="return confirm('Устгахдаа итгэлтэй байна уу?')" class="text-red-500 hover:underline text-xs">Устгах</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="text-center py-12 text-gray-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="text-lg font-medium">Банкны хуулга оруулаагүй байна</p>
        <p class="text-sm mt-1">Дээрх хэсэгт CSV файлыг оруулна уу</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
