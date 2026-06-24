<?php
define('MASTER_PASSWORD', 'eshop@master2024');
date_default_timezone_set('Asia/Ulaanbaatar');

function getDB(): PDO {
    static $db = null;
    if ($db) return $db;
    $db = new PDO(
        'mysql:host=' . ($_ENV['ESHOP_DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['ESHOP_DB_NAME'] ?? 'rw') . ';charset=utf8mb4',
        $_ENV['ESHOP_DB_USER'] ?? 'root',
        $_ENV['ESHOP_DB_PASS'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $db->exec("SET time_zone = '+08:00'");
    return $db;
}
