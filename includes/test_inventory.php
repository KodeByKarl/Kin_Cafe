<?php
require_once 'db.php';
require_once 'functions.php';

echo "Running inventory tests...\n";

// Test data
$ingredientName = 'Test Ingredient';
$manufacturingDate = '2023-01-01';
$expirationDate = '2023-12-31';

// 1. Add a new ingredient
$stmt = $pdo->prepare('INSERT INTO ingredients (name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$ingredientName, 'kg', 0, $manufacturingDate, $expirationDate]);
$ingredientId = $pdo->lastInsertId();

echo "- Added new ingredient with ID: $ingredientId\n";

// 2. Verify the ingredient is displayed correctly
$stmt = $pdo->prepare('SELECT * FROM ingredients WHERE id = ?');
$stmt->execute([$ingredientId]);
$ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ingredient['manufacturing_date'] !== $manufacturingDate || $ingredient['expiration_date'] !== $expirationDate) {
    echo "- FAILED: Ingredient dates do not match.\n";
} else {
    echo "- PASSED: Ingredient dates are correct.\n";
}

// 3. Update the ingredient's dates
$newManufacturingDate = '2023-02-01';
$newExpirationDate = '2024-01-31';

$stmt = $pdo->prepare('UPDATE ingredients SET manufacturing_date = ?, expiration_date = ? WHERE id = ?');
$stmt->execute([$newManufacturingDate, $newExpirationDate, $ingredientId]);

$stmt = $pdo->prepare('SELECT * FROM ingredients WHERE id = ?');
$stmt->execute([$ingredientId]);
$ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ingredient['manufacturing_date'] !== $newManufacturingDate || $ingredient['expiration_date'] !== $newExpirationDate) {
    echo "- FAILED: Updated ingredient dates do not match.\n";
} else {
    echo "- PASSED: Updated ingredient dates are correct.\n";
}

// 4. Delete the ingredient
$stmt = $pdo->prepare('DELETE FROM ingredients WHERE id = ?');
$stmt->execute([$ingredientId]);

echo "- Deleted ingredient with ID: $ingredientId\n";

echo "Inventory tests completed.\n";
