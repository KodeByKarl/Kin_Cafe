<?php
require_once __DIR__ . '/db.php';

echo "Running migration to remove menu item stock...\n";

try {
    $col = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'stock_quantity'")->fetch(PDO::FETCH_ASSOC);
    if ($col) {
        $pdo->exec("ALTER TABLE menu_items DROP COLUMN stock_quantity");
        echo "Dropped menu_items.stock_quantity\n";
    } else {
        echo "menu_items.stock_quantity not found (already removed)\n";
    }
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

