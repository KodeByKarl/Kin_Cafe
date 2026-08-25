<?php
require_once __DIR__ . '/db.php';

echo "Running migration to remove ingredients.supplier_id...\n";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ingredients_supplier_archive (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ingredient_id INT NOT NULL,
        supplier_id INT NULL,
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ingredient_id (ingredient_id)
    )");

    $col = $pdo->query("SHOW COLUMNS FROM ingredients LIKE 'supplier_id'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        echo "ingredients.supplier_id not found (already removed)\n";
        exit;
    }

    $pdo->exec("INSERT INTO ingredients_supplier_archive (ingredient_id, supplier_id)
        SELECT id, supplier_id FROM ingredients WHERE supplier_id IS NOT NULL");
    echo "Archived supplier links.\n";

    $fk = $pdo->prepare("SELECT CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'ingredients'
          AND COLUMN_NAME = 'supplier_id'
          AND CONSTRAINT_NAME <> 'PRIMARY'
        LIMIT 1");
    $fk->execute();
    $constraintName = $fk->fetchColumn();
    if ($constraintName) {
        $pdo->exec("ALTER TABLE ingredients DROP FOREIGN KEY `{$constraintName}`");
        echo "Dropped foreign key: {$constraintName}\n";
    }

    $pdo->exec("ALTER TABLE ingredients DROP COLUMN supplier_id");
    echo "Dropped ingredients.supplier_id\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

