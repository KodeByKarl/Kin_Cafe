<?php
session_start();
header('Content-Type: application/json');

require 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ai_services.php';

try {
    if (!isset($_SESSION['admin'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication is required.']);
        exit;
    }

    requirePermission($pdo, 'orders.view', true);

    $startDate = trim((string) ($_GET['start'] ?? ''));
    $endDate = trim((string) ($_GET['end'] ?? ''));
    $query = trim((string) ($_GET['q'] ?? ''));
    $method = trim((string) ($_GET['method'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $limit = (int) ($_GET['limit'] ?? 50);
    $page = (int) ($_GET['page'] ?? 1);

    if ($limit <= 0 || $limit > 200) {
        $limit = 50;
    }
    if ($page <= 0) {
        $page = 1;
    }
    if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }

    $offset = ($page - 1) * $limit;
    $where = [];
    $params = [];

    if ($startDate !== '') {
        $where[] = 'DATE(o.created_at) >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $where[] = 'DATE(o.created_at) <= ?';
        $params[] = $endDate;
    }
    if ($method !== '' && in_array($method, ['cash', 'card', 'digital'], true)) {
        $where[] = 'o.payment_method = ?';
        $params[] = $method;
    }
    if ($status !== '' && in_array($status, ['pending', 'completed', 'failed'], true)) {
        $where[] = 'o.payment_status = ?';
        $params[] = $status;
    }
    if ($query !== '') {
        $where[] = "(o.receipt_number LIKE ? OR COALESCE(c.name, '') LIKE ?)";
        $like = '%' . $query . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sqlBase = "FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id";
    $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) {$sqlBase} {$sqlWhere}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT o.id, o.created_at, o.receipt_number, o.total_amount, o.discount_amount, o.refund_amount, o.payment_method, o.payment_status, o.transaction_status,
            COALESCE(c.name, 'Walk-in') AS customer_name
        {$sqlBase}
        {$sqlWhere}
        ORDER BY o.created_at DESC
        LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingSummary = $pdo->query("SELECT
            COUNT(*) AS pending_count,
            COALESCE(SUM(total_amount), 0) AS pending_total,
            MIN(created_at) AS oldest_pending_at
        FROM orders
        WHERE payment_status = 'pending'")->fetch(PDO::FETCH_ASSOC) ?: [];

    $kitchenWorkflow = getAiKitchenWorkflowOptimizationData($pdo);
    $pendingQueue = $kitchenWorkflow['queue'];

    $pendingCount = (int) ($pendingSummary['pending_count'] ?? 0);
    $pendingTotal = (float) ($pendingSummary['pending_total'] ?? 0);
    $oldestPendingAt = $pendingSummary['oldest_pending_at'] ?? null;
    $oldestPendingMinutes = null;
    if ($oldestPendingAt) {
        $oldestTs = strtotime((string) $oldestPendingAt);
        if ($oldestTs !== false) {
            $oldestPendingMinutes = max(0, (int) floor((time() - $oldestTs) / 60));
        }
    }

    $totalPages = max(1, $limit > 0 ? (int) ceil($total / $limit) : 1);
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'pending' => [
            'count' => $pendingCount,
            'total' => $pendingTotal,
            'oldest_at' => $oldestPendingAt,
            'oldest_minutes' => $oldestPendingMinutes,
            'queue' => $pendingQueue,
        ],
        'kitchen_workflow' => $kitchenWorkflow,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'from' => $total > 0 ? ($offset + 1) : 0,
            'to' => min($offset + count($orders), $total),
        ],
        'server_time' => date('c'),
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
