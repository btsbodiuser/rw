<?php
/**
 * Error Logging & Monitoring System
 * 
 * Logs errors, warnings, and critical events to database.
 * Provides dashboard for real-time monitoring.
 */

/**
 * Log an error/warning/info event
 * 
 * @param string $level      'error', 'warning', 'info', 'critical'
 * @param string $category   'payment', 'order', 'webhook', 'auth', 'system', etc.
 * @param string $message    Human-readable message
 * @param array  $context    Additional context data (serialized)
 * @param string $source     Source file:line (auto-detected if null)
 * @param ?int   $userId     Optional user ID
 */
function logError(
    string $level,
    string $category,
    string $message,
    array $context = [],
    ?string $source = null,
    ?int $userId = null
): void {
    static $db = null;
    try {
        if (!$db) {
            $db = getDB();
        }
        
        if (!$source) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1] ?? $trace[0] ?? [];
            $source = ($caller['file'] ?? 'unknown') . ':' . ($caller['line'] ?? '?');
        }
        
        $stmt = $db->prepare("
            INSERT INTO error_logs 
            (level, category, message, context_data, source_file, user_id, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $level,
            $category,
            $message,
            json_encode($context, JSON_UNESCAPED_UNICODE),
            $source,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    } catch (Throwable $e) {
        // Fallback to PHP error log if DB fails
        error_log("[$level] [$category] $message | " . json_encode($context));
    }
}

/**
 * Shorthand for error logs
 */
function logPaymentError(string $message, array $context = [], ?int $userId = null): void {
    logError('error', 'payment', $message, $context, null, $userId);
}

function logWebhookError(string $message, array $context = [], ?int $userId = null): void {
    logError('error', 'webhook', $message, $context, null, $userId);
}

function logOrderError(string $message, array $context = [], ?int $userId = null): void {
    logError('error', 'order', $message, $context, null, $userId);
}

function logCritical(string $category, string $message, array $context = []): void {
    logError('critical', $category, $message, $context);
}

/**
 * Get recent errors for dashboard
 */
function getRecentErrors(int $limit = 50, string $level = null, string $category = null): array {
    $db = getDB();
    $query = "SELECT * FROM error_logs WHERE 1=1";
    $params = [];
    
    if ($level) {
        $query .= " AND level = ?";
        $params[] = $level;
    }
    
    if ($category) {
        $query .= " AND category = ?";
        $params[] = $category;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get error statistics
 */
function getErrorStats(string $period = '24h'): array {
    $db = getDB();
    
    $interval = match($period) {
        '1h'  => 'INTERVAL 1 HOUR',
        '6h'  => 'INTERVAL 6 HOUR',
        '24h' => 'INTERVAL 24 HOUR',
        '7d'  => 'INTERVAL 7 DAY',
        default => 'INTERVAL 24 HOUR',
    };
    
    // Total errors by level
    $stmt = $db->prepare("
        SELECT 
            level,
            COUNT(*) as count,
            COUNT(DISTINCT category) as categories
        FROM error_logs
        WHERE created_at > NOW() - $interval
        GROUP BY level
    ");
    $stmt->execute();
    $byLevel = [];
    foreach ($stmt->fetchAll() as $row) {
        $byLevel[$row['level']] = $row;
    }
    
    // Errors by category
    $stmt = $db->prepare("
        SELECT 
            category,
            level,
            COUNT(*) as count
        FROM error_logs
        WHERE created_at > NOW() - $interval
        GROUP BY category, level
        ORDER BY count DESC
    ");
    $stmt->execute();
    
    return [
        'period'      => $period,
        'by_level'    => $byLevel,
        'by_category' => $stmt->fetchAll(),
        'total'       => array_sum(array_column($byLevel, 'count', 'level')) ?? 0,
    ];
}

/**
 * Cleanup old error logs (>30 days)
 */
function cleanupErrorLogs(int $daysOld = 30): int {
    $db = getDB();
    $stmt = $db->prepare("
        DELETE FROM error_logs 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$daysOld]);
    return $stmt->rowCount();
}
