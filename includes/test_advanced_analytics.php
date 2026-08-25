<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Running advanced analytics tests...\n";

function assertTrue(bool $cond, string $label): void {
    echo ($cond ? "- PASSED: " : "- FAILED: ") . $label . "\n";
}

$start = date('Y-m-d', strtotime('-2 days'));
$end = date('Y-m-d');

$pdo->exec("INSERT INTO orders (total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, payment_method, payment_status, receipt_number, transaction_status, refund_amount, created_by, created_at)
    VALUES (100.00, 100.00, 0.00, 0.00, 100.00, 0.00, 'cash', 'completed', 'TST-1', 'completed', 0.00, NULL, NOW())");
$orderId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot)
    VALUES (?, NULL, 2, 50.00, 100.00, 'Test Item', 'ITMTEST')")->execute([$orderId]);
$pdo->prepare("INSERT INTO order_payments (order_id, payment_method, amount) VALUES (?, 'cash', 100.00)")->execute([$orderId]);

$kpis = analyticsKpis($pdo, $start, $end, []);
assertTrue(isset($kpis['order_count']) && $kpis['order_count'] >= 1, 'KPIs include orders');
assertTrue(isset($kpis['net_sales']) && $kpis['net_sales'] >= 100.00, 'KPIs net_sales includes seeded order');

$series = analyticsSalesSeries($pdo, $start, $end, []);
assertTrue(is_array($series), 'Sales series returns array');

$payments = analyticsPaymentMix($pdo, $start, $end, []);
assertTrue(is_array($payments), 'Payment mix returns array');

$top = analyticsTopItems($pdo, $start, $end, 10, []);
assertTrue(is_array($top), 'Top items returns array');

$orders = analyticsOrders($pdo, $start, $end, 50, 0, []);
assertTrue(isset($orders['total']) && $orders['total'] >= 1, 'Orders drilldown returns total');

$pdo->prepare("DELETE FROM order_payments WHERE order_id = ?")->execute([$orderId]);
$pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
$pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

echo "Advanced analytics tests completed.\n";
