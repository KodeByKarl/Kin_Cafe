<?php
require_once __DIR__ . '/db.php';

echo "Running migration for cash-only payments...\n";

try {
    if ($pdo->query("SHOW TABLES LIKE 'orders'")->fetchColumn()) {
        $pdo->exec("UPDATE orders SET payment_method = 'cash' WHERE payment_method <> 'cash'");
        try {
            $pdo->exec("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash') NOT NULL DEFAULT 'cash'");
            echo "Restricted orders.payment_method to cash\n";
        } catch (Throwable $e) {
            echo "Skipped altering orders.payment_method: " . $e->getMessage() . "\n";
        }
    }

    if ($pdo->query("SHOW TABLES LIKE 'order_payments'")->fetchColumn()) {
        $pdo->exec("UPDATE order_payments SET payment_method = 'cash' WHERE payment_method <> 'cash'");
        try {
            $pdo->exec("ALTER TABLE order_payments MODIFY COLUMN payment_method ENUM('cash') NOT NULL DEFAULT 'cash'");
            echo "Restricted order_payments.payment_method to cash\n";
        } catch (Throwable $e) {
            echo "Skipped altering order_payments.payment_method: " . $e->getMessage() . "\n";
        }

        $col = $pdo->query("SHOW COLUMNS FROM order_payments LIKE 'reference_number'")->fetch(PDO::FETCH_ASSOC);
        if ($col) {
            $pdo->exec("ALTER TABLE order_payments DROP COLUMN reference_number");
            echo "Dropped order_payments.reference_number\n";
        }
    }

    if ($pdo->query("SHOW TABLES LIKE 'daily_reconciliations'")->fetchColumn()) {
        $col = $pdo->query("SHOW COLUMNS FROM daily_reconciliations LIKE 'card_total'")->fetch(PDO::FETCH_ASSOC);
        if ($col) {
            $pdo->exec("ALTER TABLE daily_reconciliations DROP COLUMN card_total");
            echo "Dropped daily_reconciliations.card_total\n";
        }
        $col = $pdo->query("SHOW COLUMNS FROM daily_reconciliations LIKE 'digital_total'")->fetch(PDO::FETCH_ASSOC);
        if ($col) {
            $pdo->exec("ALTER TABLE daily_reconciliations DROP COLUMN digital_total");
            echo "Dropped daily_reconciliations.digital_total\n";
        }
    }
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
