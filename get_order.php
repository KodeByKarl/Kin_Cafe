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

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid order id.']);
        exit;
    }

    $data = getOrderDetails($pdo, $id);
    echo json_encode(['success' => true] + $data);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

