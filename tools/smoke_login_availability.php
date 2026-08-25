<?php
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

echo "schema ok\n";
$status = getLoginLockoutStatus($pdo, 'admin');
echo 'lockout=' . json_encode($status) . "\n";

$unavailable = getUnavailableMenuItemsSnapshot($pdo, 3);
echo 'unavailable=' . (int) $unavailable['total'] . "\n";
if (!empty($unavailable['items'][0])) {
    echo $unavailable['items'][0]['name'] . ': ' . $unavailable['items'][0]['reason'] . "\n";
}

$history = getRecentMenuAndStockHistory($pdo, 5);
echo 'history=' . count($history) . "\n";

$cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'failed_login_attempts'")->fetch();
echo 'users.failed_login_attempts=' . ($cols ? 'yes' : 'no') . "\n";
$lockCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'locked_until'")->fetch();
echo 'users.locked_until=' . ($lockCol ? 'yes' : 'no') . "\n";
$table = $pdo->query("SHOW TABLES LIKE 'login_attempts'")->fetch();
echo 'login_attempts=' . ($table ? 'yes' : 'no') . "\n";
