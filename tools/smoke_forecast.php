<?php
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$health = @file_get_contents('http://127.0.0.1:5000/health', false, stream_context_create([
    'http' => ['timeout' => 3, 'ignore_errors' => true],
]));
echo 'health=' . trim((string) $health) . PHP_EOL;

$days = (int) $pdo->query("SELECT COUNT(DISTINCT DATE(created_at)) FROM orders WHERE payment_status = 'completed'")->fetchColumn();
echo 'history_days=' . $days . PHP_EOL;

$forecast = getSalesForecastData($pdo, 7);
echo 'method=' . ($forecast['method_used'] ?? '?') . PHP_EOL;
echo 'label=' . ($forecast['method_label'] ?? '?') . PHP_EOL;
echo 'total=' . ($forecast['forecast_total'] ?? '?') . PHP_EOL;
echo 'insufficient=' . (!empty($forecast['insufficient_data']) ? 'yes' : 'no') . PHP_EOL;
echo 'service_unavailable_hint=' . (str_contains((string) ($forecast['method_label'] ?? ''), 'unavailable') ? 'yes' : 'no') . PHP_EOL;
