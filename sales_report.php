<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication is required.']);
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'reports.view', true);

$today = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime('-29 days'));
$startDate = trim((string) ($_GET['start'] ?? $defaultStart));
$endDate = trim((string) ($_GET['end'] ?? $today));
$topLimit = (int) ($_GET['top_limit'] ?? 5);
if ($topLimit <= 0 || $topLimit > 20) {
    $topLimit = 5;
}
if ($startDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = $defaultStart;
}
if ($endDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $today;
}
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$salesStmt = $pdo->prepare("SELECT DATE(created_at) as sale_date, SUM(GREATEST(total_amount - refund_amount, 0)) as sales
    FROM orders
    WHERE payment_status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY sale_date");
$salesStmt->execute([$startDate, $endDate]);
$salesData = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

$paymentStmt = $pdo->prepare("SELECT payment_method, SUM(amount) as total
    FROM order_payments op
    JOIN orders o ON o.id = op.order_id
    WHERE o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY payment_method");
$paymentStmt->execute([$startDate, $endDate]);
$paymentMethods = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

$topCustomersStmt = $pdo->prepare("SELECT c.name, c.phone, c.loyalty_points, COALESCE(SUM(GREATEST(o.total_amount - o.refund_amount, 0)), 0) AS sales_total
    FROM customers c
    LEFT JOIN orders o ON o.customer_id = c.id AND o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY c.id, c.name, c.phone, c.loyalty_points
    ORDER BY sales_total DESC, c.loyalty_points DESC
    LIMIT {$topLimit}");
$topCustomersStmt->execute([$startDate, $endDate]);
$topCustomers = $topCustomersStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryStmt = $pdo->prepare("SELECT COALESCE(mc.name, 'Uncategorized') AS category_name, COALESCE(SUM(GREATEST(oi.line_total, 0)), 0) AS total_sales
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
    LEFT JOIN menu_categories mc ON mc.id = mi.category_id
    WHERE o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY mc.id, mc.name
    ORDER BY total_sales DESC
    LIMIT {$topLimit}");
$categoryStmt->execute([$startDate, $endDate]);
$categorySales = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

$topItemsStmt = $pdo->prepare("SELECT mi.name, COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.payment_status = 'completed' AND o.transaction_status <> 'refunded' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY mi.id, mi.name
    ORDER BY total_sold DESC
    LIMIT {$topLimit}");
$topItemsStmt->execute([$startDate, $endDate]);
$topItems = $topItemsStmt->fetchAll(PDO::FETCH_ASSOC);

$todaySummary = getSalesSummary($pdo, $today);
$salesForecast = getSalesForecastData($pdo, 7);
$analyticsTrends = getAnalyticsKpiTrends($pdo);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'today' => $todaySummary,
    'filters' => ['start' => $startDate, 'end' => $endDate, 'top_limit' => $topLimit],
    'sales_data' => $salesData,
    'payment_methods' => $paymentMethods,
    'category_sales' => $categorySales,
    'top_items' => $topItems,
    'top_customers' => $topCustomers,
    'forecast' => (float) ($salesForecast['forecast_total'] ?? forecastSales($pdo, 7)),
    'forecast_method_label' => (string) ($salesForecast['method_label'] ?? formatForecastMethodLabel('moving_average', true)),
    'trends' => $analyticsTrends,
]);