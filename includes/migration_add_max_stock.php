<?php
/**
 * Migration: Add max_stock column to ingredients table
 * Adds a healthbar capacity indicator for ingredient inventory tracking
 */

function migrate_add_max_stock($pdo) {
    try {
        // Check if column already exists
        $stmt = $pdo->query("SHOW COLUMNS FROM ingredients WHERE Field = 'max_stock'");
        if ($stmt->rowCount() > 0) {
            return; // Column already exists
        }

        // Add max_stock column after stock_quantity
        $pdo->exec("ALTER TABLE ingredients ADD COLUMN max_stock DECIMAL(10,2) DEFAULT 100 AFTER stock_quantity");
        
        error_log("Migration: Added max_stock column to ingredients table");
    } catch (Throwable $e) {
        error_log("Migration error (add_max_stock): " . $e->getMessage());
    }
}

// Run migration if this file is called
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    require_once 'db.php';
    migrate_add_max_stock($pdo);
    echo "Migration completed: max_stock column added to ingredients table";
}
