<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requirePermission($pdo, 'dashboard.view');

$days = (int) ($_GET['days'] ?? 7);
$series = getDashboardSalesSeries($pdo, $days);

echo json_encode([
    'success' => true,
    'days' => (int) ($series['days'] ?? $days),
    'labels' => $series['labels'] ?? [],
    'values' => $series['values'] ?? [],
]);
