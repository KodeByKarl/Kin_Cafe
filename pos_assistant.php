<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ai_services.php';

requirePermission($pdo, 'pos.access');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $question = trim((string) ($payload['question'] ?? ''));
    $token = (string) ($payload['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!csrfValidate($token, 'pos_virtual_assistant')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security validation failed. Refresh the page and try again.']);
        exit;
    }

    $result = askAiVirtualAssistant($pdo, $question);
    echo json_encode($result);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'The assistant could not process your request. Try a shorter question.',
    ]);
}
