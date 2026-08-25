<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$pdoRef = $pdo;
$start = date('Y-m-d', strtotime('-30 days'));
$end = date('Y-m-d');

echo "Analytics benchmark (range {$start} to {$end})\n";

function bench(string $name, callable $fn): void {
    $t0 = microtime(true);
    $fn();
    $ms = (microtime(true) - $t0) * 1000;
    echo "- {$name}: " . number_format($ms, 2) . " ms\n";
}

bench('KPIs', function () use ($pdoRef, $start, $end) {
    analyticsKpis($pdoRef, $start, $end, []);
});

bench('Sales series', function () use ($pdoRef, $start, $end) {
    analyticsSalesSeries($pdoRef, $start, $end, []);
});

bench('Payment mix', function () use ($pdoRef, $start, $end) {
    analyticsPaymentMix($pdoRef, $start, $end, []);
});

bench('Top items', function () use ($pdoRef, $start, $end) {
    analyticsTopItems($pdoRef, $start, $end, 10, []);
});

bench('Orders drilldown (50)', function () use ($pdoRef, $start, $end) {
    analyticsOrders($pdoRef, $start, $end, 50, 0, []);
});

bench('Heatmap', function () use ($pdoRef, $start, $end) {
    analyticsHeatmap($pdoRef, $start, $end, []);
});
