<?php
session_start();
require 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['admin'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    requirePermission($pdo, 'reports.view', true);

    $action = strtolower(trim((string) ($_GET['action'] ?? 'summary')));
    [$startDate, $endDate] = analyticsDateRange($_GET);
    $filters = analyticsFilters($_GET);

    if ($action === 'summary') {
        $kpis = analyticsKpis($pdo, $startDate, $endDate, $filters);
        $series = analyticsSalesSeries($pdo, $startDate, $endDate, $filters);
        $payments = analyticsPaymentMix($pdo, $startDate, $endDate, $filters);
        $topItems = analyticsTopItems($pdo, $startDate, $endDate, 10, $filters);
        $heatmap = analyticsHeatmap($pdo, $startDate, $endDate, $filters);

        echo json_encode([
            'success' => true,
            'range' => ['start' => $startDate, 'end' => $endDate],
            'filters' => $filters,
            'kpis' => $kpis,
            'series' => $series,
            'payments' => $payments,
            'top_items' => $topItems,
            'heatmap' => $heatmap,
        ]);
        exit;
    }

    if ($action === 'orders') {
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);
        $data = analyticsOrders($pdo, $startDate, $endDate, $limit, $offset, $filters);
        echo json_encode(['success' => true, 'range' => ['start' => $startDate, 'end' => $endDate], 'data' => $data]);
        exit;
    }

    if ($action === 'widgets_get') {
        $userId = (int) ($_SESSION['admin'] ?? 0);
        $json = getUserPreference($pdo, $userId, 'analytics_widgets', null);
        echo json_encode(['success' => true, 'widgets' => $json ? json_decode($json, true) : null]);
        exit;
    }

    if ($action === 'widgets_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = (int) ($_SESSION['admin'] ?? 0);
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '[]', true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid widgets payload.');
        }
        $encoded = json_encode($payload);
        setUserPreference($pdo, $userId, 'analytics_widgets', $encoded);
        logAuditEvent($pdo, 'analytics_widgets_saved', 'user_preference', null, ['user_id' => $userId]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

