<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sign in required.']);
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrfValidate($token, 'notifications')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$userId = (int) $_SESSION['admin'];

if ($action === 'dismiss') {
    $key = trim((string) ($_POST['notification_key'] ?? ''));
    if ($key === '' || !dismissStaffNotification($pdo, $userId, $key)) {
        echo json_encode(['success' => false, 'message' => 'Unable to dismiss notification.']);
        exit;
    }
    $feed = getStaffNotificationFeed($pdo, $userId);
    echo json_encode(['success' => true, 'count' => (int) ($feed['count'] ?? 0)]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
