<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ai_services.php';

requirePermission($pdo, 'orders.view');

$title = 'Orders';
$completeOrderCsrf = csrfToken('complete_order');
$cancelOrderCsrf = csrfToken('cancel_order');

$startDate = trim((string) ($_GET['start'] ?? ''));
$endDate = trim((string) ($_GET['end'] ?? ''));
$query = trim((string) ($_GET['q'] ?? ''));
$method = trim((string) ($_GET['method'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 20);
$page = (int) ($_GET['page'] ?? 1);
if ($limit <= 0 || $limit > 200) $limit = 20;
if ($page <= 0) $page = 1;
$offset = ($page - 1) * $limit;

if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

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
$kitchenNextUp = $kitchenWorkflow['next_up'] ?? null;
$kitchenHighPriorityCount = (int) ($kitchenWorkflow['high_priority_count'] ?? 0);
$kitchenAverageWait = (float) ($kitchenWorkflow['average_wait_minutes'] ?? 0);

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
$resetParams = [];
if (isset($_GET['tab']) && $_GET['tab'] !== '') {
    $resetParams['tab'] = (string) $_GET['tab'];
}
$resetUrl = 'orders_history.php' . ($resetParams ? '?' . http_build_query($resetParams) : '');

include 'includes/header.php';
?>

<div class="main-content orders-history-page" data-orders-poll-ms="3000" data-orders-hidden-poll-ms="5000">
    <div class="page-hero orders-hero">
        <div>
            <?php renderPageBackButton('dashboard.php', 'Back to Dashboard'); ?>
            <h1 class="page-title">Order History</h1>
            <p class="page-subtitle">Track and manage all cafe transactions.</p>
            <small class="text-muted" id="ordersLiveStatus">Auto-updating every few seconds.</small>
        </div>
        <div class="orders-hero-actions">
            <button type="button" class="btn btn-outline-secondary kc-btn-icon" onclick="exportOrdersCsv()">
                <span class="orders-inline-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 4v10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m8 10 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 18h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span>Export CSV</span>
            </button>
            <button type="button" class="btn btn-primary kc-btn-icon" onclick="window.print()">
                <span class="orders-inline-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M7 9V4h10v5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><rect x="5" y="13" width="14" height="7" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M17 13v-2H7v2" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                </span>
                <span>Print Report</span>
            </button>
        </div>
    </div>

    <section class="orders-pending-dashboard">
        <div class="orders-pending-stats">
            <article class="orders-pending-card">
                <span class="orders-pending-label">Pending Orders</span>
                <strong class="orders-pending-value" id="pendingCountValue"><?php echo $pendingCount; ?></strong>
                <span class="orders-pending-meta">Orders waiting for staff completion</span>
            </article>
            <article class="orders-pending-card">
                <span class="orders-pending-label">Pending Value</span>
                <strong class="orders-pending-value" id="pendingValueAmount">₱<?php echo number_format($pendingTotal, 2); ?></strong>
                <span class="orders-pending-meta">Current unpaid completion workload</span>
            </article>
            <article class="orders-pending-card">
                <span class="orders-pending-label">Oldest Pending</span>
                <strong class="orders-pending-value" id="oldestPendingValue"><?php echo $oldestPendingMinutes !== null ? $oldestPendingMinutes . ' min' : '--'; ?></strong>
                <span class="orders-pending-meta" id="oldestPendingMeta"><?php echo $oldestPendingAt ? htmlspecialchars(date('M d, h:i A', strtotime((string) $oldestPendingAt))) : 'No pending orders'; ?></span>
            </article>
        </div>

        <section class="card orders-pending-queue-card">
            <div class="dashboard-card-head">
                <h2>Pending Queue</h2>
                <span class="text-muted">AI-prioritized for kitchen flow</span>
            </div>
            <div class="card-body orders-pending-queue" id="ordersPendingQueue">
                <?php if ($pendingQueue): ?>
                    <?php foreach ($pendingQueue as $pendingOrder): ?>
                        <article class="orders-pending-row">
                            <div class="orders-pending-copy">
                                <strong><?php echo htmlspecialchars((string) $pendingOrder['receipt_number']); ?></strong>
                                <span><?php echo htmlspecialchars((string) $pendingOrder['customer_name']); ?></span>
                                <small><?php echo htmlspecialchars(date('M d, h:i A', strtotime((string) $pendingOrder['created_at']))); ?><?php if (isset($pendingOrder['reasoning'])): ?> • <?php echo htmlspecialchars((string) $pendingOrder['reasoning']); ?><?php endif; ?></small>
                            </div>
                            <div class="orders-pending-row-meta">
                                <strong>₱<?php echo number_format((float) $pendingOrder['total_amount'], 2); ?></strong>
                                <?php if (isset($pendingOrder['priority_label'])): ?><small class="text-muted"><?php echo htmlspecialchars((string) $pendingOrder['priority_label']); ?><?php if (isset($pendingOrder['waiting_minutes'])): ?> • <?php echo (int) $pendingOrder['waiting_minutes']; ?> min<?php endif; ?></small><?php endif; ?>
                                <div class="orders-pending-row-actions">
                                    <button class="orders-complete-btn" type="button" onclick="markOrderComplete(<?php echo (int) $pendingOrder['id']; ?>)">Complete</button>
                                    <button class="orders-cancel-btn" type="button" onclick="markOrderCancelled(<?php echo (int) $pendingOrder['id']; ?>)">Cancel</button>
                                    <button class="orders-icon-btn" type="button" onclick="openOrderDetails(<?php echo (int) $pendingOrder['id']; ?>)" aria-label="View pending order">
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.8"/></svg>
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="orders-pending-empty">No pending orders right now.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card orders-pending-queue-card">
            <div class="dashboard-card-head">
                <h2>Smart Kitchen Workflow Optimization</h2>
                <span class="text-muted">AI insight for the order module</span>
            </div>
            <div class="card-body">
                <div class="orders-pending-stats">
                    <article class="orders-pending-card">
                        <span class="orders-pending-label">Rush-First Tickets</span>
                        <strong class="orders-pending-value" id="kitchenHighPriorityCount"><?php echo $kitchenHighPriorityCount; ?></strong>
                        <span class="orders-pending-meta">Orders that should be prepared first</span>
                    </article>
                    <article class="orders-pending-card">
                        <span class="orders-pending-label">Average Wait</span>
                        <strong class="orders-pending-value" id="kitchenAverageWait"><?php echo number_format($kitchenAverageWait, 1); ?> min</strong>
                        <span class="orders-pending-meta">Current pending-queue age</span>
                    </article>
                    <article class="orders-pending-card">
                        <span class="orders-pending-label">Prepare Next</span>
                        <strong class="orders-pending-value" id="kitchenNextUp"><?php echo htmlspecialchars((string) ($kitchenNextUp['receipt_number'] ?? 'No pending orders')); ?></strong>
                        <span class="orders-pending-meta" id="kitchenNextUpMeta"><?php echo htmlspecialchars((string) ($kitchenNextUp['reasoning'] ?? 'Queue is clear.')); ?></span>
                    </article>
                </div>
            </div>
        </section>
    </section>

    <section class="card orders-shell-card">
        <div class="orders-filter-bar">
            <form method="get" class="orders-filter-form" data-no-autosave="true">
                <?php if (isset($_GET['tab']) && $_GET['tab'] !== ''): ?>
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars((string) $_GET['tab']); ?>">
                <?php endif; ?>
                <div class="orders-filter-field orders-search-field">
                    <input type="text" class="form-control" name="q" id="ordersSearchInput" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search receipt or customer..." data-live-search-target="#ordersTable tbody tr" autocomplete="off">
                </div>
                <div class="orders-filter-field">
                    <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="orders-filter-field">
                    <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="orders-filter-field orders-status-field">
                    <select class="form-control" name="method">
                        <option value="">All Methods</option>
                        <option value="cash" <?php echo $method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="card" <?php echo $method === 'card' ? 'selected' : ''; ?>>Card</option>
                        <option value="digital" <?php echo $method === 'digital' ? 'selected' : ''; ?>>Digital</option>
                    </select>
                </div>
                <div class="orders-filter-field orders-status-field">
                    <select class="form-control" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <input type="hidden" name="limit" value="<?php echo (int) $limit; ?>">
                <button class="btn btn-primary" type="submit">Apply Filters</button>
                <a class="btn btn-outline-secondary orders-reset-btn" href="<?php echo htmlspecialchars($resetUrl); ?>">Reset Filters</a>
            </form>
        </div>

        <div class="orders-table-wrap">
            <div class="table-responsive">
                <table class="table table-sm mb-0 orders-table" id="ordersTable">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Receipt #</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <?php foreach ($orders as $o): ?>
                            <?php
                                $paymentStatus = strtolower((string) $o['payment_status']);
                                $transactionStatus = strtolower((string) ($o['transaction_status'] ?? ''));
                                $statusClass = $paymentStatus === 'completed' ? 'is-completed' : ($paymentStatus === 'pending' ? 'is-pending' : ($transactionStatus === 'cancelled' ? 'is-cancelled' : 'is-failed'));
                                $statusLabel = $transactionStatus === 'cancelled' ? 'CANCELLED' : strtoupper((string) $o['payment_status']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars(formatAppDateTime((string) $o['created_at'])); ?></td>
                                <td class="orders-receipt-cell"><?php echo htmlspecialchars((string) $o['receipt_number']); ?></td>
                                <td class="orders-customer-cell"><?php echo htmlspecialchars((string) $o['customer_name']); ?></td>
                                <td class="orders-method-cell"><?php echo htmlspecialchars(strtoupper((string) $o['payment_method'])); ?></td>
                                <td><span class="orders-status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                                <td class="orders-total-cell">₱<?php echo number_format((float) $o['total_amount'], 2); ?></td>
                                <td>
                                    <div class="orders-actions-cell">
                                        <?php if (strtolower((string) $o['payment_status']) === 'pending'): ?>
                                            <button class="orders-complete-btn" type="button" onclick="markOrderComplete(<?php echo (int) $o['id']; ?>)" aria-label="Mark order complete">Complete</button>
                                            <button class="orders-cancel-btn" type="button" onclick="markOrderCancelled(<?php echo (int) $o['id']; ?>)" aria-label="Cancel order">Cancel</button>
                                        <?php endif; ?>
                                        <button class="orders-icon-btn" type="button" onclick="openOrderDetails(<?php echo (int) $o['id']; ?>)" aria-label="View order">
                                            <svg viewBox="0 0 24 24" fill="none"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.8"/></svg>
                                        </button>
                                        <button class="orders-icon-btn orders-icon-btn-muted" type="button" onclick="openOrderDetails(<?php echo (int) $o['id']; ?>)" aria-label="More actions">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$orders): ?>
                            <tr><td colspan="7" class="orders-empty-state">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="orders-footer-bar">
            <div class="orders-entries-copy" id="ordersEntriesCopy">Showing <?php echo $total > 0 ? (int) $offset + 1 : 0; ?> to <?php echo min((int) ($offset + count($orders)), $total); ?> of <?php echo (int) $total; ?> entries</div>
            <div class="orders-pagination">
                <?php $prevQs = $_GET; $prevQs['page'] = max(1, $page - 1); ?>
                <a class="orders-page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" id="ordersPrevPage" href="orders_history.php?<?php echo htmlspecialchars(http_build_query($prevQs)); ?>">Previous</a>
                <span class="orders-page-current" id="ordersCurrentPage"><?php echo (int) $page; ?></span>
                <?php $nextQs = $_GET; $nextQs['page'] = min(max(1, $totalPages), $page + 1); ?>
                <a class="orders-page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" id="ordersNextPage" href="orders_history.php?<?php echo htmlspecialchars(http_build_query($nextQs)); ?>">Next</a>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="orderDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="orderDetailsBody" class="text-muted">Loading...</div>
            </div>
        </div>
    </div>
</div>

<script>
const completeOrderCsrf = <?php echo json_encode($completeOrderCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const cancelOrderCsrf = <?php echo json_encode($cancelOrderCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const ordersPollConfig = {
    feedUrl: 'orders_history_feed.php',
    pageUrl: 'orders_history.php',
    pollMs: 3000,
    hiddenPollMs: 5000
};

function escapeHtml(s) {
    return String(s || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function fmtMoney(n) {
    const value = Number(n || 0);
    if (!Number.isFinite(value)) {
        return '0.00';
    }
    return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDateTime(value) {
    if (!value) {
        return '';
    }
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) {
        return String(value);
    }
    return d.toLocaleString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function fmtPendingMeta(value) {
    if (!value) {
        return 'No pending orders';
    }
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) {
        return String(value);
    }
    return d.toLocaleString(undefined, { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function getOrderStatusParts(order) {
    const paymentStatus = String(order.payment_status || '').toLowerCase();
    const transactionStatus = String(order.transaction_status || '').toLowerCase();
    return {
        className: paymentStatus === 'completed' ? 'is-completed' : (paymentStatus === 'pending' ? 'is-pending' : (transactionStatus === 'cancelled' ? 'is-cancelled' : 'is-failed')),
        label: transactionStatus === 'cancelled' ? 'CANCELLED' : String(order.payment_status || '').toUpperCase()
    };
}

function buildOrdersPageHref(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    return ordersPollConfig.pageUrl + '?' + params.toString();
}

function renderPendingQueue(queue) {
    const container = document.getElementById('ordersPendingQueue');
    if (!container) {
        return;
    }

    if (!queue || !queue.length) {
        container.innerHTML = '<div class="orders-pending-empty">No pending orders right now.</div>';
        return;
    }

    container.innerHTML = queue.map((pendingOrder) => `
        <article class="orders-pending-row">
            <div class="orders-pending-copy">
                <strong>${escapeHtml(pendingOrder.receipt_number || '')}</strong>
                <span>${escapeHtml(pendingOrder.customer_name || 'Walk-in')}</span>
                <small>${escapeHtml(fmtPendingMeta(pendingOrder.created_at))}${pendingOrder.reasoning ? ` • ${escapeHtml(pendingOrder.reasoning)}` : ''}</small>
            </div>
            <div class="orders-pending-row-meta">
                <strong>₱${fmtMoney(pendingOrder.total_amount)}</strong>
                ${pendingOrder.priority_label ? `<small class="text-muted">${escapeHtml(pendingOrder.priority_label)}${pendingOrder.waiting_minutes != null ? ` • ${Number(pendingOrder.waiting_minutes)} min` : ''}</small>` : ''}
                <div class="orders-pending-row-actions">
                    <button class="orders-complete-btn" type="button" onclick="markOrderComplete(${Number(pendingOrder.id)})">Complete</button>
                    <button class="orders-cancel-btn" type="button" onclick="markOrderCancelled(${Number(pendingOrder.id)})">Cancel</button>
                    <button class="orders-icon-btn" type="button" onclick="openOrderDetails(${Number(pendingOrder.id)})" aria-label="View pending order">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.8"/></svg>
                    </button>
                </div>
            </div>
        </article>
    `).join('');
}

function renderOrdersTable(orders) {
    const body = document.getElementById('ordersTableBody');
    if (!body) {
        return;
    }

    if (!orders || !orders.length) {
        body.innerHTML = '<tr><td colspan="7" class="orders-empty-state">No orders found.</td></tr>';
        return;
    }

    body.innerHTML = orders.map((order) => {
        const status = getOrderStatusParts(order);
        const pendingActions = String(order.payment_status || '').toLowerCase() === 'pending'
            ? `
                <button class="orders-complete-btn" type="button" onclick="markOrderComplete(${Number(order.id)})" aria-label="Mark order complete">Complete</button>
                <button class="orders-cancel-btn" type="button" onclick="markOrderCancelled(${Number(order.id)})" aria-label="Cancel order">Cancel</button>
            `
            : '';

        return `
            <tr>
                <td>${escapeHtml(fmtDateTime(order.created_at))}</td>
                <td class="orders-receipt-cell">${escapeHtml(order.receipt_number || '')}</td>
                <td class="orders-customer-cell">${escapeHtml(order.customer_name || 'Walk-in')}</td>
                <td class="orders-method-cell">${escapeHtml(String(order.payment_method || '').toUpperCase())}</td>
                <td><span class="orders-status-pill ${status.className}">${escapeHtml(status.label)}</span></td>
                <td class="orders-total-cell">₱${fmtMoney(order.total_amount)}</td>
                <td>
                    <div class="orders-actions-cell">
                        ${pendingActions}
                        <button class="orders-icon-btn" type="button" onclick="openOrderDetails(${Number(order.id)})" aria-label="View order">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.8"/></svg>
                        </button>
                        <button class="orders-icon-btn orders-icon-btn-muted" type="button" onclick="openOrderDetails(${Number(order.id)})" aria-label="More actions">
                            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPagination(pagination) {
    const entriesCopy = document.getElementById('ordersEntriesCopy');
    const currentPage = document.getElementById('ordersCurrentPage');
    const prevPage = document.getElementById('ordersPrevPage');
    const nextPage = document.getElementById('ordersNextPage');

    if (entriesCopy) {
        entriesCopy.textContent = `Showing ${pagination.from} to ${pagination.to} of ${pagination.total} entries`;
    }
    if (currentPage) {
        currentPage.textContent = String(pagination.page || 1);
    }
    if (prevPage) {
        prevPage.href = buildOrdersPageHref(Math.max(1, Number(pagination.page || 1) - 1));
        prevPage.classList.toggle('disabled', Number(pagination.page || 1) <= 1);
    }
    if (nextPage) {
        const totalPages = Math.max(1, Number(pagination.total_pages || 1));
        const page = Math.max(1, Number(pagination.page || 1));
        nextPage.href = buildOrdersPageHref(Math.min(totalPages, page + 1));
        nextPage.classList.toggle('disabled', page >= totalPages);
    }
}

function renderLiveSnapshot(data) {
    const pending = data.pending || {};
    const pagination = data.pagination || {};
    const kitchen = data.kitchen_workflow || {};
    const liveStatus = document.getElementById('ordersLiveStatus');
    const pendingCountValue = document.getElementById('pendingCountValue');
    const pendingValueAmount = document.getElementById('pendingValueAmount');
    const oldestPendingValue = document.getElementById('oldestPendingValue');
    const oldestPendingMeta = document.getElementById('oldestPendingMeta');
    const kitchenHighPriorityCount = document.getElementById('kitchenHighPriorityCount');
    const kitchenAverageWait = document.getElementById('kitchenAverageWait');
    const kitchenNextUp = document.getElementById('kitchenNextUp');
    const kitchenNextUpMeta = document.getElementById('kitchenNextUpMeta');

    if (pendingCountValue) {
        pendingCountValue.textContent = String(Number(pending.count || 0));
    }
    if (pendingValueAmount) {
        pendingValueAmount.textContent = `₱${fmtMoney(pending.total || 0)}`;
    }
    if (oldestPendingValue) {
        oldestPendingValue.textContent = pending.oldest_minutes == null ? '--' : `${pending.oldest_minutes} min`;
    }
    if (oldestPendingMeta) {
        oldestPendingMeta.textContent = fmtPendingMeta(pending.oldest_at);
    }
    if (liveStatus) {
        liveStatus.textContent = `Live sync ${new Date().toLocaleTimeString()}`;
    }
    if (kitchenHighPriorityCount) {
        kitchenHighPriorityCount.textContent = String(Number(kitchen.high_priority_count || 0));
    }
    if (kitchenAverageWait) {
        kitchenAverageWait.textContent = `${Number(kitchen.average_wait_minutes || 0).toFixed(1)} min`;
    }
    if (kitchenNextUp) {
        kitchenNextUp.textContent = String((kitchen.next_up && kitchen.next_up.receipt_number) || 'No pending orders');
    }
    if (kitchenNextUpMeta) {
        kitchenNextUpMeta.textContent = String((kitchen.next_up && kitchen.next_up.reasoning) || 'Queue is clear.');
    }

    renderPendingQueue(pending.queue || []);
    renderOrdersTable(data.orders || []);
    renderPagination(pagination);
}

function startOrdersRealtimePolling() {
    const shell = document.querySelector('.orders-history-page');
    if (!shell) {
        return;
    }

    ordersPollConfig.pollMs = Number(shell.dataset.ordersPollMs || ordersPollConfig.pollMs);
    ordersPollConfig.hiddenPollMs = Number(shell.dataset.ordersHiddenPollMs || ordersPollConfig.hiddenPollMs);

    let inFlight = false;
    let timerId = null;

    const scheduleNext = () => {
        const delay = document.hidden ? ordersPollConfig.hiddenPollMs : ordersPollConfig.pollMs;
        timerId = window.setTimeout(runPoll, delay);
    };

    const runPoll = () => {
        if (inFlight) {
            scheduleNext();
            return;
        }

        inFlight = true;
        const params = new URLSearchParams(window.location.search);

        fetch(`${ordersPollConfig.feedUrl}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        })
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to refresh orders.');
                }
                renderLiveSnapshot(data);
            })
            .catch(() => {
                const liveStatus = document.getElementById('ordersLiveStatus');
                if (liveStatus) {
                    liveStatus.textContent = 'Live sync paused. Retrying...';
                }
            })
            .finally(() => {
                inFlight = false;
                scheduleNext();
            });
    };

    document.addEventListener('visibilitychange', () => {
        if (timerId) {
            clearTimeout(timerId);
            timerId = null;
        }
        scheduleNext();
    });

    scheduleNext();
}

function exportOrdersCsv() {
    const rows = Array.from(document.querySelectorAll('#ordersTable tr'));
    const csv = rows.map((row) => Array.from(row.querySelectorAll('th,td')).slice(0, 6).map((cell) => {
        const text = (cell.innerText || '').replace(/\s+/g, ' ').trim();
        return '"' + text.replace(/"/g, '""') + '"';
    }).join(',')).join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'orders-history.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function markOrderComplete(orderId) {
    if (!orderId) {
        return;
    }

    fetch('complete_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, csrf: completeOrderCsrf })
    })
    .then(async (response) => {
        let data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('The server returned an unreadable response.');
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to complete order.');
        }

        window.location.reload();
    })
    .catch((error) => {
        window.KinAlertModal ? window.KinAlertModal.alert(error.message || 'Unable to complete order.', 'Notice', 'OK') : alert(error.message || 'Unable to complete order.');
    });
}

function markOrderCancelled(orderId) {
    if (!orderId) {
        return;
    }

    const runCancel = () => fetch('cancel_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, csrf: cancelOrderCsrf })
    })
    .then(async (response) => {
        let data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('The server returned an unreadable response.');
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to cancel order.');
        }

        window.location.reload();
    })
    .catch((error) => {
        window.KinAlertModal ? window.KinAlertModal.alert(error.message || 'Unable to cancel order.', 'Notice', 'OK') : alert(error.message || 'Unable to cancel order.');
    });

    if (window.KinAlertModal && typeof window.KinAlertModal.confirm === 'function') {
        window.KinAlertModal.confirm('Cancel this pending order?', 'Cancel Order', 'Cancel Order', 'Keep Order').then((ok) => {
            if (ok) {
                runCancel();
            }
        });
        return;
    }

    if (confirm('Cancel this pending order?')) {
        runCancel();
    }
}

function openOrderDetails(orderId) {
    const body = document.getElementById('orderDetailsBody');
    body.innerHTML = '<div class="text-muted">Loading...</div>';
    fetch('get_order.php?id=' + encodeURIComponent(orderId))
        .then(async (response) => {
            const text = await response.text();
            let data;
            try { data = JSON.parse(text); } catch (e) { throw new Error(text ? text.slice(0, 200) : 'Invalid response'); }
            if (!response.ok || (data && data.success === false)) throw new Error((data && data.message) ? data.message : 'Unable to load order.');
            return data;
        })
        .then((data) => {
            const o = data.order;
            const items = data.items || [];
            const payments = data.payments || [];
            const tx = data.transaction_logs || [];
            const audit = data.audit_logs || [];

            const itemsHtml = items.map(it => `
                <tr>
                    <td>${escapeHtml(it.item_name_snapshot || '')}</td>
                    <td class="text-right">${escapeHtml(it.quantity)}</td>
                    <td class="text-right">₱${fmtMoney(it.price)}</td>
                    <td class="text-right">₱${fmtMoney(it.line_total)}</td>
                </tr>
            `).join('');

            const txFilterOptions = Array.from(new Set(tx.map((entry) => String(entry.event_type || '').trim()).filter(Boolean)));

            function buildTransactionLogRows(filterType) {
                const filtered = filterType
                    ? tx.filter((entry) => String(entry.event_type || '') === filterType)
                    : tx;
                return filtered.map(t => `
                <tr>
                    <td>${escapeHtml(t.created_at || '')}</td>
                    <td>${escapeHtml(t.event_type)}</td>
                    <td>${escapeHtml(t.status)}</td>
                    <td>${escapeHtml(t.message || '')}</td>
                </tr>
            `).join('');
            }

            const payHtml = payments.map(p => `
                <tr>
                    <td>${escapeHtml(p.payment_method)}</td>
                    <td class="text-right">₱${fmtMoney(p.amount)}</td>
                    <td>${escapeHtml(p.created_at || '')}</td>
                </tr>
            `).join('');

            const txHtml = buildTransactionLogRows('');
            const txFilterHtml = txFilterOptions.map((eventType) => `<option value="${escapeHtml(eventType)}">${escapeHtml(eventType)}</option>`).join('');

            const auditHtml = audit.map(a => `
                <tr>
                    <td>${escapeHtml(a.created_at || '')}</td>
                    <td>${escapeHtml(a.username || '')}</td>
                    <td>${escapeHtml(a.action || '')}</td>
                </tr>
            `).join('');

            body.innerHTML = `
                <div class="mb-3">
                    <div><strong>Receipt:</strong> ${escapeHtml(o.receipt_number || '')}</div>
                    <div><strong>Customer:</strong> ${escapeHtml(o.customer_name || 'Walk-in')}</div>
                    <div><strong>Status:</strong> ${escapeHtml(o.payment_status)} / ${escapeHtml(o.transaction_status)}</div>
                    <div><strong>Method:</strong> ${escapeHtml(o.payment_method)}</div>
                    <div><strong>Created:</strong> ${escapeHtml(o.created_at)}</div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Items</div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Line Total</th></tr></thead>
                            <tbody>${itemsHtml || '<tr><td colspan="4" class="text-muted">No items.</td></tr>'}</tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Totals</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between"><span>Subtotal</span><strong>₱${fmtMoney(o.subtotal_amount)}</strong></div>
                        <div class="d-flex justify-content-between"><span>Discount ${o.discount_type === 'pwd_senior' ? `(PWD/Senior 20% Off${o.pwd_senior_id ? `, ID: ${escapeHtml(o.pwd_senior_id)}` : ''})` : ''}</span><strong>₱${fmtMoney(o.discount_amount)}</strong></div>
                        <div class="d-flex justify-content-between"><span>Refund</span><strong>₱${fmtMoney(o.refund_amount)}</strong></div>
                        <hr>
                        <div class="d-flex justify-content-between"><span>Total</span><strong>₱${fmtMoney(o.total_amount)}</strong></div>
                        <div class="d-flex justify-content-between"><span>Paid</span><strong>₱${fmtMoney(o.paid_amount)}</strong></div>
                        <div class="d-flex justify-content-between"><span>Change</span><strong>₱${fmtMoney(o.change_amount)}</strong></div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Payments</div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Method</th><th class="text-right">Amount</th><th>Time</th></tr></thead>
                            <tbody>${payHtml || '<tr><td colspan="3" class="text-muted">No payments.</td></tr>'}</tbody>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Transaction Logs</span>
                                <select id="orderTxFilter" class="form-control form-control-sm order-tx-filter" aria-label="Filter transaction log event type">
                                    <option value="">All types</option>
                                    ${txFilterHtml}
                                </select>
                            </div>
                            <div class="card-body table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Time</th><th>Type</th><th>Status</th><th>Message</th></tr></thead>
                                    <tbody id="orderTxTableBody">${txHtml || '<tr><td colspan="4" class="text-muted">No logs.</td></tr>'}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">Audit Trail</div>
                            <div class="card-body table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Time</th><th>User</th><th>Action</th></tr></thead>
                                    <tbody>${auditHtml || '<tr><td colspan="3" class="text-muted">No audit events.</td></tr>'}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const txFilter = document.getElementById('orderTxFilter');
            const txBody = document.getElementById('orderTxTableBody');
            if (txFilter && txBody) {
                txFilter.addEventListener('change', () => {
                    const rows = buildTransactionLogRows(txFilter.value);
                    txBody.innerHTML = rows || '<tr><td colspan="4" class="text-muted">No logs for this event type.</td></tr>';
                });
            }
        })
        .catch((err) => {
            body.innerHTML = '<div class="alert alert-danger">' + escapeHtml(err.message || 'Unable to load order.') + '</div>';
        });
    $('#orderDetailsModal').modal('show');
}

startOrdersRealtimePolling();
</script>

<?php include 'includes/footer.php'; ?>
