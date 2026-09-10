<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ai_modules.php';

requirePermission($pdo, 'reports.view', true);

$type = strtolower(trim((string) ($_GET['type'] ?? 'sales')));
$saleDate = trim((string) ($_GET['date'] ?? ''));
$average = (float) ($_GET['average'] ?? 0);

if ($type === 'sales') {
    echo json_encode(getAiAnomalySalesDayDetail($pdo, $saleDate, $average));
    exit;
}

if ($type === 'inventory') {
    $signals = aiBuildSignals($pdo);
    echo json_encode([
        'success' => true,
        'type' => 'inventory',
        'data_source' => 'inventory_logs joined with ingredients (last 30 days). Large removals (≥5) or large additions (≥25) are flagged.',
        'items' => $signals['inventory_anomalies'] ?? [],
        'inventory_url' => 'inventory.php',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown anomaly detail type.']);
