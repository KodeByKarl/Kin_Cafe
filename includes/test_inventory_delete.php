<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Running inventory delete tests...\n";

function assertTrue(bool $cond, string $label): void {
    echo ($cond ? "- PASSED: " : "- FAILED: ") . $label . "\n";
}

$pdo->prepare('INSERT INTO ingredients (name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (?, ?, 0, NULL, NULL)')
    ->execute(['Delete Test Ingredient', 'pcs']);
$id = (int) $pdo->lastInsertId();

softDeleteIngredient($pdo, $id, null);
$stmt = $pdo->prepare('SELECT deleted_at FROM ingredients WHERE id = ?');
$stmt->execute([$id]);
$deletedAt = $stmt->fetchColumn();
assertTrue($deletedAt !== false && $deletedAt !== null, 'Ingredient marked deleted');

$stmt = $pdo->prepare('SELECT COUNT(*) FROM ingredients WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$id]);
assertTrue((int) $stmt->fetchColumn() === 0, 'Ingredient excluded from active queries');

$pdo->prepare('DELETE FROM ingredients WHERE id = ?')->execute([$id]);

echo "Inventory delete tests completed.\n";

