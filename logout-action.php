<?php
require_once __DIR__ . '/includes/config.php';

$token = customerToken();
if ($token) {
    apiCall('POST', 'auth/logout.php', null, $token);
}
logoutCustomerSession();

header('Location: ' . url());
exit;
