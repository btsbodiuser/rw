<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query('SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE "smtp_%" OR setting_key LIKE "login_email%" OR setting_key LIKE "register_email%"');
while($row = $stmt->fetch()) {
    echo $row['setting_key'] . ': ' . $row['setting_value'] . PHP_EOL;
}