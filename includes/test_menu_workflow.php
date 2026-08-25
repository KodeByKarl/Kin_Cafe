<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "Running menu workflow tests...\n";

function assertThrows(callable $fn, string $label): void {
    try {
        $fn();
        echo "- FAILED: {$label} (no exception)\n";
    } catch (Throwable $e) {
        echo "- PASSED: {$label}\n";
    }
}

function assertTrue(bool $cond, string $label): void {
    echo ($cond ? "- PASSED: " : "- FAILED: ") . $label . "\n";
}

$pdo->exec("INSERT INTO menu_categories (name, description, parent_id) VALUES ('TestCatA', NULL, NULL)");
$catA = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO menu_categories (name, description, parent_id) VALUES ('TestCatB', NULL, {$catA})");
$catB = (int) $pdo->lastInsertId();

assertThrows(function () use ($pdo, $catA, $catB) {
    assertValidCategoryParent($pdo, $catA, $catB);
}, 'Prevent circular category parent (A -> B where B already under A)');

assertThrows(function () use ($pdo, $catA) {
    assertValidCategoryParent($pdo, $catA, $catA);
}, 'Prevent self-parent category');

assertTrue(parseAvailableValue('1', 0) === 1, 'parseAvailableValue(1) => 1');
assertTrue(parseAvailableValue('0', 1) === 0, 'parseAvailableValue(0) => 0');
assertTrue(parseAvailableValue('on', 0) === 1, 'parseAvailableValue(on) => 1');
assertTrue(parseAvailableValue(null, 1) === 1, 'parseAvailableValue(null, default=1) => 1');

$stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price, category_id, image, available) VALUES (?, NULL, ?, ?, NULL, 1)");
$stmt->execute(['TestItem', 100.00, $catA]);
$itemId = (int) $pdo->lastInsertId();

$stmt->execute(['ChildItem', 50.00, $catB]);
$childItemId = (int) $pdo->lastInsertId();

$pdo->prepare("UPDATE menu_items SET available = ? WHERE id = ?")->execute([parseAvailableValue('0', 1), $itemId]);
$available = (int) $pdo->query("SELECT available FROM menu_items WHERE id = {$itemId}")->fetchColumn();
assertTrue($available === 0, 'Menu item availability update persists as 0');

$deleted = deleteMenuCategoryTree($pdo, $catA);
assertTrue(($deleted['deleted_categories'] ?? 0) === 2, 'Deleting parent category removes child categories too');
assertTrue(($deleted['deleted_items'] ?? 0) === 2, 'Deleting category tree removes items in parent and child categories');

$remainingCategoryCount = (int) $pdo->query("SELECT COUNT(*) FROM menu_categories WHERE id IN ({$catA}, {$catB})")->fetchColumn();
$remainingItemCount = (int) $pdo->query("SELECT COUNT(*) FROM menu_items WHERE id IN ({$itemId}, {$childItemId})")->fetchColumn();
assertTrue($remainingCategoryCount === 0, 'Category tree is fully removed');
assertTrue($remainingItemCount === 0, 'Items in deleted category tree are fully removed');

echo "Menu workflow tests completed.\n";
