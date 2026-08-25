<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_services.php';
require_once __DIR__ . '/../includes/forecast_client.php';

echo "Kin Cafe Verification Snapshot\n";
echo str_repeat('=', 40) . "\n";

$checks = [];

$checks['db_connection'] = $pdo instanceof PDO;

$signals = aiBuildSignals($pdo);
$checks['ai_signals'] = is_array($signals) && isset($signals['forecast_next_week']);
$checks['profiled_customers'] = count($signals['customer_profiles'] ?? []) >= 0;
$checks['recommendation_pairs'] = count($signals['recommendation_pairs'] ?? []) >= 0;
$checks['anomaly_detection'] = function_exists('aiDetectSalesAnomalies');
$checks['analytics_trends'] = function_exists('getAnalyticsKpiTrends');

$stockUp = aiBuildForecastStockUpGuidance(
    $pdo,
    (float) ($signals['forecast_next_week'] ?? 0),
    (float) ($signals['average_daily_sales'] ?? 0),
    $signals['demand_predictions'] ?? [],
    $signals['smart_reordering'] ?? []
);
$checks['stock_up_guidance'] = is_array($stockUp['items'] ?? null);

$salesForecast = getSalesForecastData($pdo, 7);
$checks['sales_forecast'] = is_array($salesForecast);
$checks['forecast_method'] = (string) ($salesForecast['method_used'] ?? '');

$healthContext = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 3,
        'ignore_errors' => true,
    ],
]);
$health = @file_get_contents('http://127.0.0.1:5000/health', false, $healthContext);
$checks['forecast_service_online'] = $health !== false && str_contains((string) $health, '"status"');
$checks['forecast_service_arima'] = str_contains((string) $health, 'ARIMA');

$posFile = file_get_contents(__DIR__ . '/../pos.php');
$checks['pos_recommendation_panel'] = str_contains((string) $posFile, 'posRecommendationsPanel');

$isoDoc = is_file(__DIR__ . '/../docs/qa/iso25010-matrix.md');
$checks['iso25010_matrix'] = $isoDoc;

$trends = getAnalyticsKpiTrends($pdo);
$checks['analytics_trend_values'] = isset($trends['net_sales'], $trends['orders']);

foreach ($checks as $name => $passed) {
    $label = $passed ? 'PASS' : 'FAIL';
    $value = is_bool($passed) ? '' : ' (' . $passed . ')';
    echo sprintf("[%s] %s%s\n", $label, $name, is_string($passed) || is_numeric($passed) ? $value : '');
}

echo "\nRuntime metrics\n";
echo '- Forecast method: ' . ($checks['forecast_method'] ?: 'unknown') . "\n";
echo '- Profiled customers: ' . count($signals['customer_profiles'] ?? []) . "\n";
echo '- Recommendation pairs: ' . count($signals['recommendation_pairs'] ?? []) . "\n";
echo '- Stock-up guidance items: ' . count($stockUp['items'] ?? []) . "\n";
echo '- Sales anomalies flagged: ' . count($signals['sales_anomalies'] ?? []) . "\n";
echo '- Forecast service: ' . ($checks['forecast_service_online'] ? 'online' : 'offline') . "\n";

$historyDays = (int) $pdo->query("SELECT COUNT(DISTINCT DATE(created_at)) FROM orders WHERE payment_status = 'completed'")->fetchColumn();
echo '- Completed-order history days: ' . $historyDays . "\n";
if ($historyDays < 14) {
    echo "\nNOTE: Fewer than 14 days of sales history — ARIMA/SARIMA may show moving-average fallback.\n";
}

echo "\nVerification snapshot complete.\n";
