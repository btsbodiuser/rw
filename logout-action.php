<?php
require_once __DIR__ . '/includes/config.php';

// Clear only the auth-related session keys — the guest cart (session-based)
// is intentionally left intact so a customer doesn't lose their cart by
// logging out.
unset($_SESSION['token'], $_SESSION['user_id'], $_SESSION['user']);

// Rotate the session ID so the pre-logout identifier can't be reused.
session_regenerate_id(true);

header('Location: ' . url());
exit;
