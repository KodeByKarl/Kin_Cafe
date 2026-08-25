<?php
require_once __DIR__ . '/db.php';

echo "Running migration to remove solo/sharing prices...\n";

try {
    $cols = [];
    $colSolo = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'price_solo'")->fetch(PDO::FETCH_ASSOC);
    $colSharing = $pdo->query("SHOW COLUMNS FROM menu_items LIKE 'price_sharing'")->fetch(PDO::FETCH_ASSOC);
    if ($colSolo) $cols[] = 'price_solo';
    if ($colSharing) $cols[] = 'price_sharing';

    if (!$cols) {
        echo "No solo/sharing columns found.\n";
        exit;
    }

    foreach ($cols as $c) {
        $pdo->exec("ALTER TABLE menu_items DROP COLUMN {$c}");
        echo "Dropped menu_items.{$c}\n";
    }
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

