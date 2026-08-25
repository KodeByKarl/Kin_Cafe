<?php
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_services.php';

function timed(string $label, callable $fn): void {
    $start = microtime(true);
    $fn();
    echo sprintf("%-40s %7.0f ms\n", $label, (microtime(true) - $start) * 1000);
}

echo "Kin Cafe latency profile\n";
echo str_repeat('=', 50) . "\n";
echo 'server_time=' . date('Y-m-d H:i:s') . "\n";
echo 'auto_backup_enabled=' . getSetting($pdo, 'auto_backup_enabled', '1') . "\n";
echo 'auto_backup_time=' . getSetting($pdo, 'auto_backup_time', '17:00') . "\n";

timed('ensureSystemSchema', static function () use ($pdo) {
    ensureSystemSchema($pdo);
});
timed('authBootstrap', static function () use ($pdo) {
    authBootstrap($pdo);
});
timed('checkAndRunScheduledBackup', static function () use ($pdo) {
    checkAndRunScheduledBackup($pdo);
});
timed('getSalesForecastData', static function () use ($pdo) {
    getSalesForecastData($pdo, 7);
});
timed('getUnavailableMenuItemsSnapshot', static function () use ($pdo) {
    getUnavailableMenuItemsSnapshot($pdo, 8);
});
timed('aiBuildSignals', static function () use ($pdo) {
    aiBuildSignals($pdo);
});

$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM backups WHERE reason IN ('scheduled_5pm', 'scheduled_daily') AND DATE(created_at) = ?");
$stmt->execute([$today]);
echo 'scheduled_backups_today=' . (int) $stmt->fetchColumn() . "\n";
$orders = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$items = (int) $pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
echo "orders={$orders} order_items={$items}\n";
