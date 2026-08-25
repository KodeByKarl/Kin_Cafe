<?php
require_once 'db.php';

echo "Running migration to add Stock Replenishment System...\n";

try {
    // MySQL DDL statements cause implicit commits, so we don't use transactions for this migration.

    /* 1. Update ingredients table with stock thresholds
    $pdo->exec("ALTER TABLE ingredients 
        ADD COLUMN min_stock_level DECIMAL(10,2) DEFAULT 5.00,
        ADD COLUMN target_stock_level DECIMAL(10,2) DEFAULT 20.00,
        ADD COLUMN last_purchase_price DECIMAL(10,2) DEFAULT 0.00");
    */

    // 2. Create purchase_orders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        status ENUM('draft', 'sent', 'received', 'cancelled') NOT NULL DEFAULT 'draft',
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_by INT NULL,
        notes TEXT NULL,
        received_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");

    // 3. Create purchase_order_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_order_id INT NOT NULL,
        ingredient_id INT NOT NULL,
        quantity_ordered DECIMAL(10,2) NOT NULL,
        quantity_received DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        unit_cost DECIMAL(10,2) NOT NULL,
        line_total DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
    )");

    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Error running migration: " . $e->getMessage() . "\n";
}
