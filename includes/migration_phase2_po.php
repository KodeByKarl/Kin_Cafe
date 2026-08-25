<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Running Phase 2 Migration: Purchase Order Automation & Supplier Setup...\n";

try {
    // 1. Add threshold columns to ingredients
    ensureColumn($pdo, 'ingredients', 'critical_level', 'DECIMAL(10,2) NOT NULL DEFAULT 5.00');
    ensureColumn($pdo, 'ingredients', 'reorder_point', 'DECIMAL(10,2) NOT NULL DEFAULT 10.00');
    ensureColumn($pdo, 'ingredients', 'supplier_id', 'INT NULL');

    // 2. Create suppliers table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact_email VARCHAR(100) NULL,
        contact_phone VARCHAR(50) NULL,
        address TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure email and phone columns exist if table already existed
    ensureColumn($pdo, 'suppliers', 'contact_email', 'VARCHAR(100) NULL');
    ensureColumn($pdo, 'suppliers', 'contact_phone', 'VARCHAR(50) NULL');

    // 3. Create ingredient_suppliers table for multi-supplier cost ranking
    $pdo->exec("CREATE TABLE IF NOT EXISTS ingredient_suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ingredient_id INT NOT NULL,
        supplier_id INT NOT NULL,
        cost_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )");

    // 4. Ensure purchase_orders table
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
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )");

    // 5. Ensure purchase_order_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_order_id INT NOT NULL,
        ingredient_id INT NOT NULL,
        quantity_ordered DECIMAL(10,2) NOT NULL,
        quantity_received DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
    )");

    // Seed a default supplier if none exists
    $count = (int) $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO suppliers (name, contact_email, contact_phone, address) VALUES 
            ('Kin Cafe Primary Supplier Co.', 'supplier@kincafe.com', '+63 917 123 4567', 'Metro Manila, Philippines'),
            ('Metro Food Products Inc.', 'orders@metrofood.ph', '+63 918 987 6543', 'Quezon City, Philippines')");
        echo "Seeded default suppliers.\n";
    }

    echo "Phase 2 Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Phase 2 Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
