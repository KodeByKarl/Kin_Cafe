<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Setting tax_rate to 0.00...\n";

try {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('tax_rate', '0.00')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute();
    logAuditEvent($pdo, 'setting_updated', 'setting', null, ['setting_key' => 'tax_rate', 'setting_value' => '0.00']);
    echo "Done.\n";
} catch (Throwable $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}

