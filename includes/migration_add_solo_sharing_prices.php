<?php
require_once __DIR__ . '/db.php';

echo "Running migration to add solo/sharing prices...\n";

try {
    $colSolo = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'price_solo'")->fetch(PDO::FETCH_ASSOC);
    if (!$colSolo) {
        $pdo->exec("ALTER TABLE menu_items ADD COLUMN price_solo DECIMAL(10,2) NULL AFTER price");
        echo "Added menu_items.price_solo\n";
    } else {
        echo "menu_items.price_solo already exists\n";
    }

    $colSharing = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'price_sharing'")->fetch(PDO::FETCH_ASSOC);
    if (!$colSharing) {
        $pdo->exec("ALTER TABLE menu_items ADD COLUMN price_sharing DECIMAL(10,2) NULL AFTER price_solo");
        echo "Added menu_items.price_sharing\n";
    } else {
        echo "menu_items.price_sharing already exists\n";
    }
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

