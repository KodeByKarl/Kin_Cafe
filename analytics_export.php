<?php
session_start();
require 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

requirePermission($pdo, 'reports.view');

$type = strtolower(trim((string) ($_GET['type'] ?? 'orders')));
$format = strtolower(trim((string) ($_GET['format'] ?? 'csv')));
[$startDate, $endDate] = analyticsDateRange($_GET);
$filters = analyticsFilters($_GET);

$fileBase = 'kin-cafe-' . $type . '-' . $startDate . '-to-' . $endDate . '-' . date('Ymd-His');

function outputCsv(string $filename, array $header, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
}

function outputXls(string $filename, array $header, array $rows): void {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo "<table border='1'><thead><tr>";
    foreach ($header as $h) echo "<th>" . htmlspecialchars((string) $h) . "</th>";
    echo "</tr></thead><tbody>";
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $cell) echo "<td>" . htmlspecialchars((string) $cell) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

function outputXlsSections(string $filename, array $sections): void {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');

    echo '<html><head><meta charset="utf-8"><style>';
    echo 'body{font-family:Calibri,Arial,sans-serif;font-size:12px;color:#2f241d;}';
    echo 'h1{font-size:22px;margin:0 0 12px;}';
    echo 'h2{font-size:16px;margin:24px 0 8px;}';
    echo 'p{margin:0 0 12px;}';
    echo 'table{border-collapse:collapse;margin:0 0 18px;width:100%;}';
    echo 'th,td{border:1px solid #cbb8a6;padding:6px 8px;vertical-align:top;text-align:left;}';
    echo 'th{background:#f5ece3;font-weight:700;}';
    echo '.empty{color:#7a6556;font-style:italic;}';
    echo '</style></head><body>';
    echo '<h1>Kin Cafe Analytics Report</h1>';

    foreach ($sections as $section) {
        $title = (string) ($section['title'] ?? 'Section');
        $header = isset($section['header']) && is_array($section['header']) ? $section['header'] : [];
        $rows = isset($section['rows']) && is_array($section['rows']) ? $section['rows'] : [];
        $note = trim((string) ($section['note'] ?? ''));

        echo '<h2>' . htmlspecialchars($title) . '</h2>';
        if ($note !== '') {
            echo '<p>' . htmlspecialchars($note) . '</p>';
        }

        if (!$header) {
            continue;
        }

        echo '<table><thead><tr>';
        foreach ($header as $column) {
            echo '<th>' . htmlspecialchars((string) $column) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if (!$rows) {
            echo '<tr><td class="empty" colspan="' . count($header) . '">No data available.</td></tr>';
        } else {
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
                }
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    echo '</body></html>';
}

try {
    if ($type === 'report') {
        $todayDate = date('Y-m-d');
        $today = getSalesSummary($pdo, $todayDate);
        $forecastNextWeek = forecastSales($pdo, 7);
        $averageOrderValue = (float) ($today['order_count'] ?? 0) > 0
            ? ((float) ($today['net_sales'] ?? 0) / (float) ($today['order_count'] ?? 0))
            : 0.0;
        $newCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE DATE(created_at) = CURDATE()")->fetchColumn();

        $salesStmt = $pdo->prepare("SELECT DATE(created_at) AS sale_date, COALESCE(SUM(GREATEST(total_amount - refund_amount, 0)), 0) AS sales
            FROM orders
            WHERE payment_status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY sale_date");
        $salesStmt->execute([$startDate, $endDate]);
        $salesRows = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

        $categoryStmt = $pdo->prepare("SELECT COALESCE(mc.name, 'Uncategorized') AS category_name, COALESCE(SUM(GREATEST(oi.line_total, 0)), 0) AS total_sales
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
            LEFT JOIN menu_categories mc ON mc.id = mi.category_id
            WHERE o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY mc.id, mc.name
            ORDER BY total_sales DESC
            LIMIT 10");
        $categoryStmt->execute([$startDate, $endDate]);
        $categorySales = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

        $topItemsStmt = $pdo->prepare("SELECT COALESCE(mi.name, oi.item_name_snapshot) AS name, COALESCE(SUM(oi.quantity), 0) AS total_sold, COALESCE(SUM(GREATEST(oi.line_total, 0)), 0) AS revenue
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
            WHERE o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY mi.id, mi.name, oi.item_name_snapshot
            ORDER BY total_sold DESC, revenue DESC
            LIMIT 10");
        $topItemsStmt->execute([$startDate, $endDate]);
        $topItems = $topItemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $topCustomersStmt = $pdo->prepare("SELECT COALESCE(NULLIF(c.name, ''), NULLIF(c.phone, ''), 'Walk-in') AS customer_name,
                COALESCE(c.phone, '') AS phone,
                COALESCE(c.loyalty_points, 0) AS loyalty_points,
                COALESCE(SUM(GREATEST(o.total_amount - o.refund_amount, 0)), 0) AS sales_total
            FROM customers c
            LEFT JOIN orders o ON o.customer_id = c.id AND o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY c.id, c.name, c.phone, c.loyalty_points
            ORDER BY sales_total DESC, c.loyalty_points DESC
            LIMIT 10");
        $topCustomersStmt->execute([$startDate, $endDate]);
        $topCustomers = $topCustomersStmt->fetchAll(PDO::FETCH_ASSOC);

        $weeklyComparison = $pdo->query("SELECT
                COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN GREATEST(total_amount - refund_amount, 0) ELSE 0 END), 0) AS current_week_sales,
                COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN GREATEST(total_amount - refund_amount, 0) ELSE 0 END), 0) AS previous_week_sales
            FROM orders
            WHERE payment_status = 'completed'")->fetch(PDO::FETCH_ASSOC) ?: [];
        $currentWeekSales = (float) ($weeklyComparison['current_week_sales'] ?? 0);
        $previousWeekSales = (float) ($weeklyComparison['previous_week_sales'] ?? 0);
        $trendLabel = 'Stable versus last week';
        if ($previousWeekSales > 0 || $currentWeekSales > 0) {
            $trend = buildPercentageTrend($currentWeekSales, $previousWeekSales);
            $trendLabel = $trend['value'] . ' versus last week';
        }

        $topCategory = $pdo->prepare("SELECT COALESCE(mc.name, 'Uncategorized') AS category_name,
                COALESCE(SUM(GREATEST(oi.line_total, 0)), 0) AS total_sales
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
            LEFT JOIN menu_categories mc ON mc.id = mi.category_id
            WHERE o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY mc.id, mc.name
            ORDER BY total_sales DESC
            LIMIT 1");
        $topCategory->execute([$startDate, $endDate]);
        $topCategoryRow = $topCategory->fetch(PDO::FETCH_ASSOC) ?: null;

        $pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending'")->fetchColumn();

        $todayTopSellerStmt = $pdo->prepare("SELECT COALESCE(mi.name, oi.item_name_snapshot) AS item_name, COALESCE(SUM(oi.quantity), 0) AS total_sold
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
            WHERE o.payment_status = 'completed' AND DATE(o.created_at) = ?
            GROUP BY mi.id, oi.item_name_snapshot, mi.name
            ORDER BY total_sold DESC
            LIMIT 1");
        $todayTopSellerStmt->execute([$todayDate]);
        $todayTopSeller = $todayTopSellerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $lowStockIngredients = [];
        $expiringIngredients = [];
        if (tableExists($pdo, 'ingredients')) {
            $lowStockIngredients = $pdo->query("SELECT name, stock_quantity, unit
                FROM ingredients
                WHERE deleted_at IS NULL AND stock_quantity < 10
                ORDER BY stock_quantity ASC, name ASC
                LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            $expiringIngredients = getExpiringIngredients($pdo, 14);
        }

        $recommendations = $pdo->query("SELECT
                LEAST(oi1.item_name_snapshot, oi2.item_name_snapshot) AS item_a,
                GREATEST(oi1.item_name_snapshot, oi2.item_name_snapshot) AS item_b,
                COUNT(DISTINCT oi1.order_id) AS pair_count
            FROM order_items oi1
            JOIN order_items oi2 ON oi1.order_id = oi2.order_id AND oi1.id < oi2.id
            JOIN orders o ON o.id = oi1.order_id
            WHERE o.payment_status = 'completed'
              AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            GROUP BY LEAST(oi1.item_name_snapshot, oi2.item_name_snapshot), GREATEST(oi1.item_name_snapshot, oi2.item_name_snapshot)
            ORDER BY pair_count DESC, item_a ASC, item_b ASC
            LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

        $sections = [
            [
                'title' => 'Overview',
                'note' => 'Report range: ' . $startDate . ' to ' . $endDate . '. Generated on ' . date('Y-m-d H:i:s') . '.',
                'header' => ['metric', 'value'],
                'rows' => [
                    ['Today Net Sales', number_format((float) ($today['net_sales'] ?? 0), 2, '.', '')],
                    ['Today Order Count', (string) ((int) ($today['order_count'] ?? 0))],
                    ['Today Average Order Value', number_format($averageOrderValue, 2, '.', '')],
                    ['New Customers Today', (string) $newCustomers],
                    ['Pending Orders', (string) $pendingOrders],
                    ['Forecast Next 7 Days', number_format((float) $forecastNextWeek, 2, '.', '')],
                    ['Weekly Trend', $trendLabel],
                    ['Top Category', $topCategoryRow ? (string) $topCategoryRow['category_name'] : 'No data'],
                    ['Top Category Sales', $topCategoryRow ? number_format((float) $topCategoryRow['total_sales'], 2, '.', '') : '0.00'],
                    ['Top Seller Today', $todayTopSeller ? (string) $todayTopSeller['item_name'] : 'No completed sales yet'],
                ],
            ],
            [
                'title' => 'Sales Performance',
                'header' => ['date', 'sales'],
                'rows' => array_map(static function (array $row): array {
                    return [
                        (string) $row['sale_date'],
                        number_format((float) ($row['sales'] ?? 0), 2, '.', ''),
                    ];
                }, $salesRows),
            ],
            [
                'title' => 'Sales by Category',
                'header' => ['category', 'total_sales'],
                'rows' => array_map(static function (array $row): array {
                    return [
                        (string) $row['category_name'],
                        number_format((float) ($row['total_sales'] ?? 0), 2, '.', ''),
                    ];
                }, $categorySales),
            ],
            [
                'title' => 'Top Selling Items',
                'header' => ['item', 'quantity_sold', 'revenue'],
                'rows' => array_map(static function (array $row): array {
                    return [
                        (string) $row['name'],
                        (string) ((int) ($row['total_sold'] ?? 0)),
                        number_format((float) ($row['revenue'] ?? 0), 2, '.', ''),
                    ];
                }, $topItems),
            ],
            [
                'title' => 'Top Customers',
                'header' => ['customer', 'phone', 'loyalty_points', 'sales_total'],
                'rows' => array_map(static function (array $row): array {
                    return [
                        (string) $row['customer_name'],
                        (string) $row['phone'],
                        (string) ((int) ($row['loyalty_points'] ?? 0)),
                        number_format((float) ($row['sales_total'] ?? 0), 2, '.', ''),
                    ];
                }, $topCustomers),
            ],
            [
                'title' => 'Low Stock Ingredients',
                'header' => ['ingredient', 'stock_quantity', 'unit'],
                'rows' => array_map(static function (array $row): array {
                    return [
                        (string) $row['name'],
                        rtrim(rtrim(number_format((float) ($row['stock_quantity'] ?? 0), 2, '.', ''), '0'), '.'),
                        (string) $row['unit'],
                    ];
                }, $lowStockIngredients),
            ],
            [
                'title' => 'Expiring Ingredients',
                'header' => ['ingredient', 'stock_quantity', 'unit', 'expires_at', 'days_remaining'],
                'rows' => array_map(static function (array $row): array {
                    return [
                        (string) ($row['name'] ?? ''),
                        rtrim(rtrim(number_format((float) ($row['stock_quantity'] ?? 0), 2, '.', ''), '0'), '.'),
                        (string) ($row['unit'] ?? ''),
                        (string) ($row['expires_at'] ?? ''),
                        isset($row['days_remaining']) ? (string) ((int) $row['days_remaining']) : '',
                    ];
                }, $expiringIngredients),
            ],
            [
                'title' => 'Upsell Recommendations',
                'header' => ['item_a', 'item_b', 'pair_count'],
                'rows' => array_map(static function (array $row): array {
                    return [
                        (string) $row['item_a'],
                        (string) $row['item_b'],
                        (string) ((int) ($row['pair_count'] ?? 0)),
                    ];
                }, $recommendations),
            ],
        ];

        logAuditEvent($pdo, 'analytics_export', 'analytics_report', null, ['format' => $format, 'start' => $startDate, 'end' => $endDate]);

        outputXlsSections($fileBase, $sections);
        exit;
    }

    if ($type === 'orders') {
        $data = analyticsOrders($pdo, $startDate, $endDate, 2000, 0, $filters);
        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                $r['created_at'],
                $r['receipt_number'],
                $r['customer_name'],
                $r['payment_method'],
                number_format((float) $r['total_amount'], 2, '.', ''),
                number_format((float) $r['discount_amount'], 2, '.', ''),
                number_format((float) $r['refund_amount'], 2, '.', ''),
                $r['created_by_name'],
            ];
        }
        $header = ['created_at', 'receipt_number', 'customer', 'payment_method', 'total_amount', 'discount_amount', 'refund_amount', 'created_by'];
        logAuditEvent($pdo, 'analytics_export', 'orders', null, ['format' => $format, 'start' => $startDate, 'end' => $endDate]);

        if ($format === 'excel') {
            outputXls($fileBase, $header, $rows);
        } else {
            outputCsv($fileBase, $header, $rows);
        }
        exit;
    }

    if ($type === 'audit') {
        $limit = 2000;
        $params = [$startDate, $endDate];
        $sql = "SELECT a.created_at, COALESCE(u.username, 'System') AS username, a.action, a.entity_type, a.entity_id, a.details_json
            FROM audit_logs a
            LEFT JOIN users u ON u.id = a.user_id
            WHERE DATE(a.created_at) BETWEEN ? AND ?
            ORDER BY a.created_at DESC
            LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [$r['created_at'], $r['username'], $r['action'], $r['entity_type'], $r['entity_id'], $r['details_json']];
        }
        $header = ['created_at', 'username', 'action', 'entity_type', 'entity_id', 'details_json'];
        logAuditEvent($pdo, 'analytics_export', 'audit_logs', null, ['format' => $format, 'start' => $startDate, 'end' => $endDate]);

        if ($format === 'excel') {
            outputXls($fileBase, $header, $rows);
        } else {
            outputCsv($fileBase, $header, $rows);
        }
        exit;
    }

    http_response_code(400);
    echo "Unsupported export type.";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Export failed: " . $e->getMessage();
}

