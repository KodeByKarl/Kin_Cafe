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

    requirePermission($pdo, 'orders.view', true);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($payload['order_id']) ? (int) $payload['order_id'] : 0;
    $csrf = (string) ($payload['csrf'] ?? '');

    if (!csrfValidate($csrf, 'complete_order')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security token validation failed.']);
        exit;
    }

    $result = completePendingOrder($pdo, $orderId, isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : null);

    echo json_encode([
        'success' => true,
        'message' => $result['already_completed'] ? 'Order was already completed.' : 'Order marked completed successfully.',
        'result' => $result,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
