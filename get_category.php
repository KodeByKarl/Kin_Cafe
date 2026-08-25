<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'menu.manage', true);

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT id, name, description, parent_id FROM menu_categories WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($category) {
        header('Content-Type: application/json');
        echo json_encode($category);
    } else {
        http_response_code(404);
    }
} else {
    http_response_code(400);
}
?>
