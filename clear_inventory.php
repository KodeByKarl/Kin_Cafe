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

    $stmt = $pdo->query('SELECT id FROM ingredients WHERE deleted_at IS NULL');
    $ingredientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$ingredientIds) {
        echo json_encode(['success' => true, 'message' => 'Inventory is already empty.']);
        exit;
    }

    $pdo->beginTransaction();
    foreach ($ingredientIds as $ingredientId) {
        softDeleteIngredient($pdo, (int) $ingredientId, (int) $_SESSION['admin']);
    }
    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
