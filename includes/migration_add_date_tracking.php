<?php
require_once 'db.php';

echo "Running migration to add date tracking to ingredients...\n";

try {
    $pdo->exec("ALTER TABLE ingredients ADD COLUMN manufacturing_date DATE NULL");
    $pdo->exec("ALTER TABLE ingredients ADD COLUMN expiration_date DATE NULL");
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Error running migration: " . $e->getMessage() . "\n";
}
