<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Running Phase 1 Migration: Costing & Dynamic Pricing...\n";

try {
    ensureColumn($pdo, 'ingredients', 'unit_cost', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    ensureColumn($pdo, 'menu_items', 'cost_price', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    ensureColumn($pdo, 'menu_items', 'admin_cost', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    ensureColumn($pdo, 'menu_items', 'markup_percent', 'DECIMAL(10,2) NOT NULL DEFAULT 30.00');
    ensureColumn($pdo, 'menu_items', 'manual_price_override', 'TINYINT(1) NOT NULL DEFAULT 0');

    echo "Phase 1 Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
