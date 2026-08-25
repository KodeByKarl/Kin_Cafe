<?php
session_start();
header('Content-Type: application/json');

require 'includes/db.php';
require_once 'includes/functions.php';

try {
    if (!isset($_SESSION['admin'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication is required.']);
        exit;
    }

    requirePermission($pdo, 'inventory.manage', true);

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid ingredient id.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, name, unit, stock_quantity, max_stock, manufacturing_date, expiration_date FROM ingredients WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ingredient) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ingredient not found.']);
        exit;
    }

    echo json_encode($ingredient);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load ingredient.']);
}
