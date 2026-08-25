<?php

require __DIR__ . '/../includes/app_config.php';
require __DIR__ . '/../includes/forecast_client.php';

putenv('KIN_CAFE_FORECAST_SERVICE_URL=http://127.0.0.1:5999');

$history = [];
$start = new DateTimeImmutable('-89 days');
for ($i = 0; $i < 90; $i++) {
    $day = $start->modify('+' . $i . ' days');
    $history[] = [
        'date' => $day->format('Y-m-d'),
        'amount' => round(900 + ($i * 2.5), 2),
    ];
}

$response = callForecastService('sales', $history);
echo "Service unavailable call returned: ";
var_export($response);
echo PHP_EOL;

require __DIR__ . '/../includes/functions.php';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=kin_cafe;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Simulate service unavailable by temporarily overriding via env (already set)
// Inject history through moving average path by using forecastSalesMovingAverage only after failed service
// Directly test label formatting:
echo formatForecastMethodLabel('moving_average', false, true) . PHP_EOL;

// Test with live service for synthetic payload through client
putenv('KIN_CAFE_FORECAST_SERVICE_URL=http://127.0.0.1:5000');
$live = callForecastService('sales', $history);
echo 'Live service model: ' . ($live['model_used'] ?? 'none') . PHP_EOL;
echo 'Live forecast total: ' . ($live['forecast_7day_total'] ?? 'none') . PHP_EOL;
