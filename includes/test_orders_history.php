<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Running orders history tests...\n";

function assertTrue(bool $cond, string $label): void {
    echo ($cond ? "- PASSED: " : "- FAILED: ") . $label . "\n";
}

$pdo->exec("INSERT INTO orders (total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, payment_method, payment_status, receipt_number, transaction_status, refund_amount, created_by, created_at)
    VALUES (50.00, 50.00, 0.00, 0.00, 50.00, 0.00, 'cash', 'completed', 'TST-HIST', 'completed', 0.00, NULL, NOW())");
$orderId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot)
    VALUES (?, NULL, 1, 50.00, 50.00, 'History Item', 'HIST')")->execute([$orderId]);
$pdo->prepare("INSERT INTO order_payments (order_id, payment_method, amount) VALUES (?, 'cash', 50.00)")->execute([$orderId]);

$details = getOrderDetails($pdo, $orderId);
assertTrue(isset($details['order']['id']) && (int) $details['order']['id'] === $orderId, 'Order details returns order');
assertTrue(is_array($details['items']) && count($details['items']) === 1, 'Order details returns items');
assertTrue(is_array($details['payments']) && count($details['payments']) === 1, 'Order details returns payments');

$pdo->prepare("DELETE FROM order_payments WHERE order_id = ?")->execute([$orderId]);
$pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
$pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

echo "Orders history tests completed.\n";
