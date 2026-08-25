<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Running account deletion tests...\n";

function assertTrue(bool $cond, string $label): void {
    echo ($cond ? "- PASSED: " : "- FAILED: ") . $label . "\n";
}

$username = 'tst_del_' . bin2hex(random_bytes(3));
$hash = password_hash('SecretPass123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, NULL, 'cashier', 1)")
    ->execute([$username, $hash]);
$userId = (int) $pdo->lastInsertId();

if (tableExists($pdo, 'user_preferences')) {
    $pdo->prepare("INSERT INTO user_preferences (user_id, pref_key, pref_value) VALUES (?, 'x', 'y')")
        ->execute([$userId]);
}

if (tableExists($pdo, 'auth_tab_sessions')) {
    $pdo->prepare("INSERT INTO auth_tab_sessions (php_session_id, tab_id, user_id, user_role, ip_address, user_agent_hash, idle_timeout_seconds, expires_at, last_seen_at)
        VALUES ('cli', 'tab', ?, 'cashier', '127.0.0.1', ?, 1800, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())")
        ->execute([$userId, hash('sha256', 'cli')]);
}

if (tableExists($pdo, 'audit_logs')) {
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details_json) VALUES (?, 'test', 'user', ?, NULL)")
        ->execute([$userId, $userId]);
    $auditId = (int) $pdo->lastInsertId();
} else {
    $auditId = 0;
}

deleteUserAccount($pdo, $userId);

$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$userId]);
assertTrue($stmt->fetchColumn() === false, 'User row deleted');

if (tableExists($pdo, 'user_preferences')) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_preferences WHERE user_id = ?');
    $stmt->execute([$userId]);
    assertTrue((int) $stmt->fetchColumn() === 0, 'User preferences removed');
}

if (tableExists($pdo, 'auth_tab_sessions')) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM auth_tab_sessions WHERE user_id = ?');
    $stmt->execute([$userId]);
    assertTrue((int) $stmt->fetchColumn() === 0, 'Auth tab sessions removed');
}

if ($auditId && tableExists($pdo, 'audit_logs')) {
    $stmt = $pdo->prepare('SELECT user_id FROM audit_logs WHERE id = ?');
    $stmt->execute([$auditId]);
    $val = $stmt->fetchColumn();
    assertTrue($val === null || $val === false || (string) $val === '', 'Audit user_id cleared');
    $pdo->prepare('DELETE FROM audit_logs WHERE id = ?')->execute([$auditId]);
}

echo "Account deletion tests completed.\n";

