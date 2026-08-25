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

    if (!hasPermission($pdo, 'menu.manage') && !hasPermission($pdo, 'pos.access')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to view this item.']);
        exit;
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid item id.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, name, price, price_solo, price_sharing, price_hot, price_iced, size_option_enabled, size_label_1, size_label_2, price_size_1, price_size_2, category_id, available, product_code, image, temperature_option_enabled FROM menu_items WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item not found.']);
        exit;
    }

    $recipeStmt = $pdo->prepare("SELECT mr.id, mr.ingredient_id, mr.quantity, mr.quantity_unit, ing.name AS ingredient_name, ing.unit AS ingredient_unit
        FROM menu_item_recipes mr
        JOIN ingredients ing ON ing.id = mr.ingredient_id
        WHERE mr.menu_item_id = ?
        ORDER BY mr.id ASC");
    $recipeStmt->execute([$id]);
    $details = getMenuItemUnavailableDetails($pdo, $item);
    $item['available'] = !empty($details['available']);
    if (!$item['available']) {
        $item['unavailable_reason'] = $details['reason'];
        $item['unavailable_details'] = $details;
    }

    $item['recipe'] = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($item);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load item.']);
}
?>
