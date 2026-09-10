<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ai_modules.php';

requirePermission($pdo, 'reports.view');

$title = 'Anomaly Detection';
$feature = getAiAnomalyDetectionData($pdo);
$averageDailySales = (float) ($feature['average_daily_sales'] ?? 0);
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <?php renderPageBackButton('analytics.php', 'Back to Analytics'); ?>
            <h1 class="page-title">Sales and Inventory Anomaly Detection Feature</h1>
            <p class="page-subtitle">Detect irregular sales patterns and inventory movements that may indicate operational issues. Click a variance or Stock Review to see where the amount comes from.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <a class="btn btn-outline-secondary" href="analytics.php">Analytics</a>
        </div>
    </div>

    <div class="ai-detail-grid">
        <section class="card ai-module-card" id="salesAnomaliesCard">
            <div class="dashboard-card-head">
                <h2>Sales Anomalies</h2>
                <span class="analytics-badge">Revenue pattern</span>
            </div>
            <div class="card-body ai-module-body">
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($feature['method_label'] ?? '')); ?></span>
                <p class="text-muted small mb-2">30-day average daily sales: <strong>₱<?php echo number_format($averageDailySales, 2); ?></strong>. Variance = |day sales − average|.</p>
                <ul class="ai-module-list">
                    <?php foreach ($feature['sales'] as $row): ?>
                        <li>
                            <button type="button"
                                class="ai-anomaly-trigger"
                                data-anomaly-type="sales"
                                data-sale-date="<?php echo htmlspecialchars((string) $row['sale_date']); ?>"
                                data-average="<?php echo htmlspecialchars((string) $averageDailySales); ?>">
                                <span><?php echo htmlspecialchars((string) $row['sale_date']); ?>: <?php echo htmlspecialchars((string) $row['type']); ?> (<?php echo htmlspecialchars((string) ($row['detection_method'] ?? 'Z-score')); ?>, z=<?php echo htmlspecialchars((string) ($row['z_score'] ?? 'n/a')); ?>)</span>
                                <strong>₱<?php echo number_format((float) $row['gap_amount'], 2); ?> variance</strong>
                            </button>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$feature['sales']): ?><li><span>No sales anomalies detected.</span><strong>OK</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
        <section class="card ai-module-card" id="inventoryAnomaliesCard">
            <div class="dashboard-card-head">
                <h2>Inventory Anomalies</h2>
                <button type="button" class="analytics-badge ai-anomaly-badge-btn" id="stockReviewBtn" data-anomaly-type="inventory">Stock review</button>
            </div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list" id="inventoryAnomalyList">
                    <?php foreach ($feature['inventory'] as $row): ?>
                        <li>
                            <a class="ai-anomaly-trigger" href="inventory.php">
                                <span><?php echo htmlspecialchars((string) $row['name']); ?>: <?php echo htmlspecialchars((string) $row['reason']); ?></span>
                                <strong><?php echo rtrim(rtrim(number_format((float) $row['quantity'], 2), '0'), '.'); ?></strong>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$feature['inventory']): ?><li><span>No inventory anomalies detected.</span><strong>OK</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="anomalyDetailModal" tabindex="-1" role="dialog" aria-labelledby="anomalyDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="anomalyDetailTitle">Anomaly detail</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="anomalyDetailBody">
                <p class="text-muted mb-0">Loading…</p>
            </div>
            <div class="modal-footer">
                <a class="btn btn-outline-secondary" id="anomalyDetailOrdersLink" href="orders_history.php">Open Orders</a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const modalEl = document.getElementById('anomalyDetailModal');
    const titleEl = document.getElementById('anomalyDetailTitle');
    const bodyEl = document.getElementById('anomalyDetailBody');
    const ordersLink = document.getElementById('anomalyDetailOrdersLink');
    const average = <?php echo json_encode($averageDailySales); ?>;

    function money(value) {
        return '₱' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showModal() {
        if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
            window.jQuery(modalEl).modal('show');
            return;
        }
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
    }

    async function openSalesDetail(saleDate) {
        titleEl.textContent = 'Sales variance — ' + saleDate;
        bodyEl.innerHTML = '<p class="text-muted mb-0">Loading order breakdown…</p>';
        ordersLink.href = 'orders_history.php';
        showModal();

        const params = new URLSearchParams({
            type: 'sales',
            date: saleDate,
            average: String(average || 0),
            tab: new URLSearchParams(window.location.search).get('tab') || ''
        });
        try {
            const res = await fetch('get_anomaly_detail.php?' + params.toString(), { credentials: 'same-origin', cache: 'no-store' });
            const data = await res.json();
            if (!data.success) {
                bodyEl.innerHTML = '<p class="text-danger">' + escapeHtml(data.message || 'Could not load detail.') + '</p>';
                return;
            }

            const orderRows = (data.orders || []).map((order) => `
                <tr>
                    <td>${escapeHtml(order.receipt_number || ('#' + order.id))}</td>
                    <td>${escapeHtml(order.created_at || '')}</td>
                    <td>${escapeHtml(order.customer_name || 'Walk-in')}</td>
                    <td class="text-right">${money(order.net_amount)}</td>
                </tr>
            `).join('') || '<tr><td colspan="4" class="text-muted">No completed orders on this day.</td></tr>';

            const itemRows = (data.top_items || []).map((item) => `
                <tr>
                    <td>${escapeHtml(item.item_name || '')}</td>
                    <td class="text-right">${escapeHtml(item.qty)}</td>
                    <td class="text-right">${money(item.revenue)}</td>
                </tr>
            `).join('') || '<tr><td colspan="3" class="text-muted">No item lines.</td></tr>';

            bodyEl.innerHTML = `
                <div class="anomaly-detail-summary">
                    <p><strong>Data source:</strong> ${escapeHtml(data.data_source || '')}</p>
                    <p><strong>Formula:</strong> ${escapeHtml(data.formula || '')}</p>
                    <ul class="list-unstyled mb-3">
                        <li>Day net sales: <strong>${money(data.day_sales)}</strong> (${escapeHtml(data.order_count)} completed orders)</li>
                        <li>30-day average daily sales: <strong>${money(data.average_daily_sales)}</strong></li>
                        <li>Variance (${escapeHtml(data.type)}): <strong>${money(data.gap_amount)}</strong></li>
                    </ul>
                </div>
                <h6>Completed orders on this day</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Receipt</th><th>Time</th><th>Customer</th><th class="text-right">Net</th></tr></thead>
                        <tbody>${orderRows}</tbody>
                    </table>
                </div>
                <h6>Top items contributing to that day</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Revenue</th></tr></thead>
                        <tbody>${itemRows}</tbody>
                    </table>
                </div>
            `;
        } catch (err) {
            bodyEl.innerHTML = '<p class="text-danger">Failed to load anomaly detail.</p>';
        }
    }

    async function openStockReview() {
        titleEl.textContent = 'Stock Review — inventory anomalies';
        bodyEl.innerHTML = '<p class="text-muted mb-0">Loading stock flags…</p>';
        ordersLink.href = 'inventory.php';
        ordersLink.textContent = 'Open Inventory';
        showModal();

        const params = new URLSearchParams({
            type: 'inventory',
            tab: new URLSearchParams(window.location.search).get('tab') || ''
        });
        try {
            const res = await fetch('get_anomaly_detail.php?' + params.toString(), { credentials: 'same-origin', cache: 'no-store' });
            const data = await res.json();
            if (!data.success) {
                bodyEl.innerHTML = '<p class="text-danger">' + escapeHtml(data.message || 'Could not load stock review.') + '</p>';
                return;
            }
            const rows = (data.items || []).map((row) => `
                <tr>
                    <td>${escapeHtml(row.name || '')}</td>
                    <td>${escapeHtml(row.action || '')}</td>
                    <td class="text-right">${escapeHtml(row.quantity)}</td>
                    <td>${escapeHtml(row.reason || '')}</td>
                    <td>${escapeHtml(row.timestamp || '')}</td>
                </tr>
            `).join('') || '<tr><td colspan="5" class="text-muted">No inventory anomalies.</td></tr>';

            bodyEl.innerHTML = `
                <p><strong>Data source:</strong> ${escapeHtml(data.data_source || '')}</p>
                <p class="text-muted">Large stock removals (≥5) or additions (≥25) in the last 30 days are flagged from <code>inventory_logs</code>.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Ingredient</th><th>Action</th><th class="text-right">Qty</th><th>Reason</th><th>When</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        } catch (err) {
            bodyEl.innerHTML = '<p class="text-danger">Failed to load stock review.</p>';
        }
    }

    document.querySelectorAll('[data-anomaly-type="sales"]').forEach((btn) => {
        btn.addEventListener('click', () => openSalesDetail(btn.getAttribute('data-sale-date') || ''));
    });

    const stockBtn = document.getElementById('stockReviewBtn');
    if (stockBtn) {
        stockBtn.addEventListener('click', openStockReview);
    }

    modalEl && modalEl.addEventListener('hidden.bs.modal', () => {
        ordersLink.textContent = 'Open Orders';
        ordersLink.href = 'orders_history.php';
    });
    if (window.jQuery) {
        window.jQuery(modalEl).on('hidden.bs.modal', () => {
            ordersLink.textContent = 'Open Orders';
            ordersLink.href = 'orders_history.php';
        });
    }
})();
</script>

<?php include 'includes/footer.php'; ?>
