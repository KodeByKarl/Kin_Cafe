<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'reports.view');

$title = 'Advanced Analytics';

$users = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="main-content analytics-admin-page">
    <div class="page-hero analytics-hero">
        <div>
            <?php renderPageBackButton('analytics.php', 'Back to Analytics'); ?>
            <h1 class="page-title">Advanced Analytics</h1>
            <p class="page-subtitle">Real-time KPIs, interactive charts, drill-down, and exports.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="analytics_export.php?type=orders&format=csv">Export Orders (CSV)</a>
            <a class="btn btn-outline-secondary" href="analytics_export.php?type=orders&format=excel">Export Orders (Excel)</a>
            <a class="btn btn-outline-secondary" href="analytics_export.php?type=audit&format=csv">Export Audit (CSV)</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form id="analyticsFilters" class="form-row">
                <div class="col-md-3 mb-2">
                    <label>Start</label>
                    <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime('-30 days'))); ?>">
                </div>
                <div class="col-md-3 mb-2">
                    <label>End</label>
                    <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                </div>
                <div class="col-md-3 mb-2">
                    <label>User</label>
                    <select class="form-control" name="created_by">
                        <option value="">All</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?> (<?php echo htmlspecialchars(normalizeRole((string) $u['role'])); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary btn-block" onclick="loadAdvancedAnalytics()">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-3" id="kpiRow">
        <div class="col-md-3 mb-2"><div class="card"><div class="card-body"><small>Net Sales</small><h4 id="kpiNetSales">₱0.00</h4></div></div></div>
        <div class="col-md-3 mb-2"><div class="card"><div class="card-body"><small>Orders</small><h4 id="kpiOrders">0</h4></div></div></div>
        <div class="col-md-3 mb-2"><div class="card"><div class="card-body"><small>Discounts</small><h4 id="kpiDiscounts">₱0.00</h4></div></div></div>
        <div class="col-md-3 mb-2"><div class="card"><div class="card-body"><small>Refunds</small><h4 id="kpiRefunds">₱0.00</h4></div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Sales Trend</span>
                    <small class="text-muted">Click a day to drill down orders</small>
                </div>
                <div class="card-body">
                    <canvas id="chartSales" height="130"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card">
                <div class="card-header">Payment Mix</div>
                <div class="card-body">
                    <canvas id="chartPayments" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-3">
            <div class="card">
                <div class="card-header">Top Items</div>
                <div class="card-body">
                    <canvas id="chartTopItems" height="160"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card">
                <div class="card-header">Order Heatmap (Hour x Day)</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm mb-0" id="heatmapTable"></table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Orders</span>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" onclick="loadOrdersPage(-1)">Prev</button>
                <button class="btn btn-outline-secondary" onclick="loadOrdersPage(1)">Next</button>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Receipt</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">Refund</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody id="ordersBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
function escapeHtml(s) {
    return String(s || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

let salesChart, paymentChart, topItemsChart;
let ordersOffset = 0;
let ordersLimit = 50;
let lastRange = null;

function getFilters() {
    const form = document.getElementById('analyticsFilters');
    const data = new FormData(form);
    const obj = {};
    for (const [k, v] of data.entries()) {
        if (String(v).trim() !== '') obj[k] = v;
    }
    return obj;
}

function fmt(n) {
    return Number(n || 0).toFixed(2);
}

function heatColor(v, max) {
    if (!max) return 'transparent';
    const t = Math.min(1, v / max);
    const a = 0.08 + (t * 0.26);
    return `rgba(176, 137, 104, ${a})`;
}

function renderHeatmap(grid) {
    const table = document.getElementById('heatmapTable');
    if (!table) return;
    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    let max = 0;
    for (const dow in grid) for (const hr in grid[dow]) max = Math.max(max, grid[dow][hr]);

    let html = '<thead><tr><th>Hour</th>';
    for (const d of days) html += `<th class="text-center">${d}</th>`;
    html += '</tr></thead><tbody>';
    for (let hr = 0; hr < 24; hr++) {
        html += `<tr><td>${hr}:00</td>`;
        for (let dow = 1; dow <= 7; dow++) {
            const v = (grid[dow] && grid[dow][hr]) ? grid[dow][hr] : 0;
            html += `<td class="text-center" style="background:${heatColor(v, max)}">${v || ''}</td>`;
        }
        html += '</tr>';
    }
    html += '</tbody>';
    table.innerHTML = html;
}

function renderOrders(rows) {
    const body = document.getElementById('ordersBody');
    if (!body) return;
    body.innerHTML = rows.map(r => `
        <tr>
            <td>${escapeHtml(r.created_at)}</td>
            <td>${escapeHtml(r.receipt_number || '')}</td>
            <td>${escapeHtml(r.customer_name || '')}</td>
            <td>${escapeHtml(r.payment_method)}</td>
            <td class="text-right">₱${fmt(r.total_amount)}</td>
            <td class="text-right">₱${fmt(r.discount_amount)}</td>
            <td class="text-right">₱${fmt(r.refund_amount)}</td>
            <td>${escapeHtml(r.created_by_name || '')}</td>
        </tr>
    `).join('');
}

function loadOrders() {
    const q = getFilters();
    q.action = 'orders';
    q.limit = ordersLimit;
    q.offset = ordersOffset;
    const qs = new URLSearchParams(q).toString();
    fetch('analytics_api.php?' + qs)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            renderOrders(data.data.rows || []);
        })
        .catch(() => {});
}

function loadOrdersPage(direction) {
    ordersOffset = Math.max(0, ordersOffset + (direction * ordersLimit));
    loadOrders();
}

function loadAdvancedAnalytics() {
    ordersOffset = 0;
    const q = getFilters();
    q.action = 'summary';
    const qs = new URLSearchParams(q).toString();
    fetch('analytics_api.php?' + qs)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            lastRange = data.range;
            document.getElementById('kpiNetSales').textContent = '₱' + fmt(data.kpis.net_sales);
            document.getElementById('kpiOrders').textContent = String(data.kpis.order_count);
            document.getElementById('kpiDiscounts').textContent = '₱' + fmt(data.kpis.discount_total);
            document.getElementById('kpiRefunds').textContent = '₱' + fmt(data.kpis.refund_total);

            const salesLabels = (data.series || []).map(r => r.day);
            const salesValues = (data.series || []).map(r => Number(r.sales || 0));
            const ctxSales = document.getElementById('chartSales').getContext('2d');
            if (salesChart) salesChart.destroy();
            salesChart = new Chart(ctxSales, {
                type: 'line',
                data: {
                    labels: salesLabels,
                    datasets: [{
                        label: 'Net Sales',
                        data: salesValues,
                        borderColor: '#7A553A',
                        backgroundColor: 'rgba(176, 137, 104, 0.20)',
                        fill: true,
                        tension: 0.25,
                    }]
                },
                options: {
                    responsive: true,
                    onClick: (evt, elems) => {
                        if (!elems || !elems.length) return;
                        const idx = elems[0]._index;
                        const day = salesLabels[idx];
                        const form = document.getElementById('analyticsFilters');
                        form.querySelector('input[name="start"]').value = day;
                        form.querySelector('input[name="end"]').value = day;
                        loadAdvancedAnalytics();
                    }
                }
            });

            const payLabels = (data.payments || []).map(r => r.payment_method);
            const payValues = (data.payments || []).map(r => Number(r.total || 0));
            const ctxPay = document.getElementById('chartPayments').getContext('2d');
            if (paymentChart) paymentChart.destroy();
            paymentChart = new Chart(ctxPay, {
                type: 'pie',
                data: {
                    labels: payLabels,
                    datasets: [{
                        data: payValues,
                        backgroundColor: ['#B08968', '#7A553A', '#3B2A22']
                    }]
                },
                options: { responsive: true }
            });

            const itemLabels = (data.top_items || []).map(r => r.name);
            const itemValues = (data.top_items || []).map(r => Number(r.revenue || 0));
            const ctxItems = document.getElementById('chartTopItems').getContext('2d');
            if (topItemsChart) topItemsChart.destroy();
            topItemsChart = new Chart(ctxItems, {
                type: 'bar',
                data: {
                    labels: itemLabels,
                    datasets: [{
                        label: 'Revenue',
                        data: itemValues,
                        backgroundColor: 'rgba(122, 85, 58, 0.65)'
                    }]
                },
                options: { responsive: true, legend: { display: false } }
            });

            renderHeatmap(data.heatmap || {});
            loadOrders();
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', function () {
    loadAdvancedAnalytics();
    setInterval(loadAdvancedAnalytics, 30000);
});
</script>

<?php include 'includes/footer.php'; ?>
