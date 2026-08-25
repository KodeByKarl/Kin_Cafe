<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Running Phase 3 Migration: Statutory PWD/Senior Discount & Receipt Tax Breakdown...\n";

try {
    ensureColumn($pdo, 'orders', 'discount_type', 'VARCHAR(30) NULL');
    ensureColumn($pdo, 'orders', 'pwd_senior_id', 'VARCHAR(50) NULL');

    echo "Phase 3 Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
