<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Running role migration (admin/manager -> supervisor)...\n";

try {
    $updated = $pdo->exec("UPDATE users SET role = 'supervisor' WHERE role IN ('admin', 'manager') OR role IS NULL OR role = ''");
    echo "Updated rows: " . (int) $updated . "\n";
    logAuditEvent($pdo, 'roles_migrated', 'user', null, ['from' => ['admin', 'manager', null, ''], 'to' => 'supervisor', 'updated_rows' => (int) $updated]);
    echo "Done.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

