<?php
/**
 * Seed demo data for capstone defense / ARIMA-SARIMA verification.
 *
 * Usage:
 *   c:\xampp\php\php.exe tools\seed_demo_data.php
 *   c:\xampp\php\php.exe tools\seed_demo_data.php --days=90
 *   c:\xampp\php\php.exe tools\seed_demo_data.php --force
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$days = 90;
$force = in_array('--force', $argv ?? [], true);
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $days = max(14, (int) substr($arg, 7));
    }
}

function seedLog(string $message): void {
    echo $message . PHP_EOL;
}

function seedPickMenuItems(PDO $pdo): array {
    $rows = $pdo->query("SELECT mi.id, mi.name, mi.price, mi.product_code, mc.name AS category_name
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mc.id = mi.category_id
        WHERE mi.available = 1
        ORDER BY mi.id ASC")->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) < 4) {
        throw new RuntimeException('Need at least 4 available menu items. Add menu items first.');
    }

    $byName = [];
    foreach ($rows as $row) {
        $byName[strtolower((string) $row['name'])] = $row;
    }

    $find = static function (array $needles) use ($byName, $rows): array {
        foreach ($needles as $needle) {
            foreach ($byName as $name => $row) {
                if (str_contains($name, strtolower($needle))) {
                    return $row;
                }
            }
        }
        return $rows[0];
    };

    return [
        'latte' => $find(['latte', 'caramel']),
        'chaofan_a' => $find(['tapa', 'chaofan']),
        'chaofan_b' => $find(['sisig', 'chaofan']),
        'chaofan_c' => $find(['chicken poppers', 'chaofan']),
        'all' => $rows,
    ];
}

function seedEnsureCustomers(PDO $pdo): array {
    $existing = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    if ($existing >= 8) {
        seedLog("- Customers already present ($existing). Skipping customer seed.");
        return $pdo->query('SELECT id, name FROM customers ORDER BY id ASC LIMIT 12')->fetchAll(PDO::FETCH_ASSOC);
    }

    $customers = [
        ['Maria Santos', '09171234501'],
        ['Juan Dela Cruz', '09181234502'],
        ['Ana Reyes', '09191234503'],
        ['Carlo Mendoza', '09201234504'],
        ['Bea Fernandez', '09211234505'],
        ['Miguel Torres', '09221234506'],
        ['Sofia Ramos', '09231234507'],
        ['Ethan Villanueva', '09241234508'],
        ['Walk-in Regular', '09251234509'],
        ['Team Alpha', '09261234510'],
    ];

    $stmt = $pdo->prepare('INSERT INTO customers (name, phone, loyalty_points, created_at) VALUES (?, ?, ?, ?)');
    $created = [];
    $baseCreated = strtotime('-120 days');
    foreach ($customers as $index => $customer) {
        $createdAt = date('Y-m-d H:i:s', $baseCreated + ($index * 86400 * 3));
        $stmt->execute([$customer[0], $customer[1], random_int(20, 180), $createdAt]);
        $created[] = ['id' => (int) $pdo->lastInsertId(), 'name' => $customer[0]];
    }

    seedLog('- Created ' . count($created) . ' demo customers.');
    return $created;
}

function seedEnsureIngredientsAndRecipes(PDO $pdo, array $menuPick): void {
    $recipeCount = (int) $pdo->query('SELECT COUNT(*) FROM menu_item_recipes')->fetchColumn();
    if ($recipeCount >= 5) {
        seedLog("- Recipes already present ($recipeCount). Skipping ingredient seed.");
        return;
    }

    $ingredients = [
        ['Espresso Beans', 'grams', 2500, 5000, 0.45],
        ['Fresh Milk', 'liters', 180, 400, 0.08],
        ['Jasmine Rice', 'grams', 12000, 20000, 0.03],
        ['Chicken Fillet', 'grams', 3500, 8000, 0.12],
        ['Cooking Oil', 'liters', 40, 120, 0.15],
    ];

    $ingStmt = $pdo->prepare('INSERT INTO ingredients (name, unit, stock_quantity, max_stock, unit_cost) VALUES (?, ?, ?, ?, ?)');
    $ingredientIds = [];
    foreach ($ingredients as $row) {
        $ingStmt->execute([$row[0], $row[1], $row[2], $row[3], $row[4]]);
        $ingredientIds[$row[0]] = (int) $pdo->lastInsertId();
    }

    $recipeMap = [
        (int) $menuPick['latte']['id'] => [
            ['Espresso Beans', 18, 'grams'],
            ['Fresh Milk', 180, 'milliliters'],
        ],
        (int) $menuPick['chaofan_a']['id'] => [
            ['Jasmine Rice', 220, 'grams'],
            ['Chicken Fillet', 80, 'grams'],
            ['Cooking Oil', 15, 'milliliters'],
        ],
        (int) $menuPick['chaofan_b']['id'] => [
            ['Jasmine Rice', 220, 'grams'],
            ['Chicken Fillet', 90, 'grams'],
            ['Cooking Oil', 15, 'milliliters'],
        ],
    ];

    $recipeStmt = $pdo->prepare('INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (?, ?, ?, ?)');
    foreach ($recipeMap as $menuItemId => $recipes) {
        foreach ($recipes as $recipe) {
            $recipeStmt->execute([$menuItemId, $ingredientIds[$recipe[0]], $recipe[1], $recipe[2]]);
        }
    }

    $logStmt = $pdo->prepare("INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, timestamp) VALUES (?, 'remove', ?, 'Demo consumption seed', ?)");
    foreach ($ingredientIds as $ingredientId) {
        for ($day = 13; $day >= 0; $day--) {
            $qty = round(random_int(5, 25) / 10, 2);
            $logStmt->execute([$ingredientId, $qty, date('Y-m-d 15:00:00', strtotime("-{$day} days"))]);
        }
    }

    seedLog('- Seeded ingredients, recipes, and 14-day consumption logs.');
}

function seedCreateOrder(PDO $pdo, array $orderData, array $items, ?int $customerId, ?int $createdBy = null): int {
    $orderStmt = $pdo->prepare("INSERT INTO orders (
            total_amount, subtotal_amount, tax_amount, discount_amount,
            paid_amount, change_amount, cash_received_amount,
            payment_method, payment_status, receipt_number, customer_id,
            transaction_status, refund_amount, created_by, created_at
        ) VALUES (?, ?, 0, 0, ?, ?, ?, 'cash', 'completed', ?, ?, 'completed', 0, ?, ?)");
    $orderStmt->execute([
        $orderData['total'],
        $orderData['total'],
        $orderData['paid'],
        $orderData['change'],
        $orderData['paid'],
        $orderData['receipt'],
        $customerId,
        $createdBy,
        $orderData['created_at'],
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($items as $item) {
        $lineTotal = round((float) $item['price'] * (int) $item['qty'], 2);
        $itemStmt->execute([
            $orderId,
            (int) $item['id'],
            (int) $item['qty'],
            (float) $item['price'],
            $lineTotal,
            (string) $item['name'],
            (string) ($item['product_code'] ?? ''),
        ]);
    }

    $payStmt = $pdo->prepare("INSERT INTO order_payments (order_id, payment_method, amount, created_at) VALUES (?, 'cash', ?, ?)");
    $payStmt->execute([$orderId, $orderData['paid'], $orderData['created_at']]);

    return $orderId;
}

function seedSalesHistory(PDO $pdo, array $menuPick, array $customers, int $days, bool $force): void {
    $historyDays = (int) $pdo->query("SELECT COUNT(DISTINCT DATE(created_at)) FROM orders WHERE payment_status = 'completed' AND receipt_number LIKE 'RCPT-DEMO-%'")->fetchColumn();
    if ($historyDays >= 14 && !$force) {
        seedLog("- Demo sales history already covers $historyDays days. Use --force to add more.");
        return;
    }

    if ($force && $historyDays > 0) {
        seedLog('- Force mode: removing prior RCPT-DEMO-* orders.');
        $pdo->exec("DELETE op FROM order_payments op JOIN orders o ON o.id = op.order_id WHERE o.receipt_number LIKE 'RCPT-DEMO-%'");
        $pdo->exec("DELETE oi FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.receipt_number LIKE 'RCPT-DEMO-%'");
        $pdo->exec("DELETE FROM transaction_logs WHERE reference_number LIKE 'RCPT-DEMO-%'");
        $pdo->exec("DELETE FROM orders WHERE receipt_number LIKE 'RCPT-DEMO-%'");
    }

    $latte = $menuPick['latte'];
    $chaofanA = $menuPick['chaofan_a'];
    $chaofanB = $menuPick['chaofan_b'];
    $chaofanC = $menuPick['chaofan_c'];
    $allItems = $menuPick['all'];

    $pairTemplates = [
        [$latte, $chaofanA],
        [$latte, $chaofanB],
        [$latte, $chaofanC],
        [$latte, $chaofanA],
        [$chaofanA, $chaofanB],
    ];

    $staffUserId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($staffUserId <= 0) {
        $staffUserId = (int) $pdo->query("SELECT id FROM users WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
    }
    $staffUserId = $staffUserId > 0 ? $staffUserId : null;

    $orderCounter = 0;
    $totalOrders = 0;

    for ($dayOffset = $days - 1; $dayOffset >= 0; $dayOffset--) {
        $date = date('Y-m-d', strtotime("-{$dayOffset} days"));
        $weekday = (int) date('N', strtotime($date));
        $isWeekend = $weekday >= 6;
        $trendBoost = 1 + (($days - $dayOffset) / $days) * 0.18;
        $weekendBoost = $isWeekend ? 1.28 : 1.0;
        $ordersToday = (int) round((random_int(8, 14)) * $weekendBoost * $trendBoost);

        for ($i = 0; $i < $ordersToday; $i++) {
            $orderCounter++;
            $hour = [11, 12, 13, 14, 17, 18, 19][array_rand([11, 12, 13, 14, 17, 18, 19])];
            $minute = random_int(0, 59);
            $createdAt = sprintf('%s %02d:%02d:00', $date, $hour, $minute);

            $customer = null;
            if (random_int(1, 100) <= 72 && $customers) {
                $customer = $customers[array_rand($customers)];
            }

            if ($customer && random_int(1, 100) <= 55) {
                $template = $pairTemplates[$customer['id'] % count($pairTemplates)];
            } else {
                $template = $pairTemplates[array_rand($pairTemplates)];
            }

            $items = [];
            $total = 0.0;
            foreach ($template as $menuRow) {
                $qty = random_int(1, 2);
                $price = (float) $menuRow['price'];
                $items[] = [
                    'id' => (int) $menuRow['id'],
                    'name' => (string) $menuRow['name'],
                    'product_code' => (string) ($menuRow['product_code'] ?? ''),
                    'price' => $price,
                    'qty' => $qty,
                ];
                $total += $price * $qty;
            }

            if (random_int(1, 100) <= 20) {
                $extra = $allItems[array_rand($allItems)];
                $qty = 1;
                $price = (float) $extra['price'];
                $items[] = [
                    'id' => (int) $extra['id'],
                    'name' => (string) $extra['name'],
                    'product_code' => (string) ($extra['product_code'] ?? ''),
                    'price' => $price,
                    'qty' => $qty,
                ];
                $total += $price * $qty;
            }

            $total = round($total, 2);
            $paid = round($total + random_int(0, 4) * 10, 2);
            if ($paid < $total) {
                $paid = $total;
            }

            seedCreateOrder($pdo, [
                'total' => $total,
                'paid' => $paid,
                'change' => round($paid - $total, 2),
                'receipt' => sprintf('RCPT-DEMO-%s-%04d', str_replace('-', '', $date), $orderCounter),
                'created_at' => $createdAt,
            ], $items, $customer ? (int) $customer['id'] : null, $staffUserId);

            $totalOrders++;
        }
    }

    seedLog("- Seeded $totalOrders completed demo orders across $days days.");
}

try {
    seedLog('Kin Cafe demo data seed');
    seedLog('====================');

    $menuPick = seedPickMenuItems($pdo);
    seedLog('- Using menu anchors: ' . $menuPick['latte']['name'] . ' + ' . $menuPick['chaofan_a']['name']);

    $pdo->beginTransaction();
    $customers = seedEnsureCustomers($pdo);
    seedEnsureIngredientsAndRecipes($pdo, $menuPick);
    seedSalesHistory($pdo, $menuPick, $customers, $days, $force);
    $pdo->commit();

    $historyDays = (int) $pdo->query("SELECT COUNT(DISTINCT DATE(created_at)) FROM orders WHERE payment_status = 'completed'")->fetchColumn();
    $completedOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'completed'")->fetchColumn();
    $profiled = (int) $pdo->query("SELECT COUNT(*) FROM (
            SELECT customer_id FROM orders
            WHERE payment_status = 'completed' AND customer_id IS NOT NULL
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY customer_id HAVING COUNT(*) >= 2
        ) t")->fetchColumn();

    seedLog('');
    seedLog('Seed complete.');
    seedLog("- Completed orders: $completedOrders");
    seedLog("- Distinct sales days: $historyDays");
    seedLog("- Repeat customers (2+ orders / 90d): $profiled");

    $pdo->exec("UPDATE ingredients SET stock_quantity = 4 WHERE name = 'Fresh Milk' AND deleted_at IS NULL");
    $pdo->exec("UPDATE ingredients SET stock_quantity = 120 WHERE name = 'Espresso Beans' AND deleted_at IS NULL");
    seedLog('- Adjusted demo low-stock levels for Fresh Milk and Espresso Beans.');
    seedLog('');
    seedLog('Next steps:');
    seedLog('  1. Start forecast service: start_forecast_service.bat');
    seedLog('  2. Verify: c:\\xampp\\php\\php.exe tools\\verify_system.php');
    seedLog('  3. Open Dashboard / Sales Forecasting — expect ARIMA labels');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    seedLog('ERROR: ' . $e->getMessage());
    exit(1);
}
