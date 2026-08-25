<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'dashboard.view');

$title = 'Dashboard';
$canViewAiSuite = hasPermission($pdo, 'reports.view');

$errors = [];
$totalOrders = 0;
$totalSales = 0.0;
$today = ['net_sales' => 0.0, 'discount_total' => 0.0, 'refund_total' => 0.0, 'cash_total' => 0.0, 'card_total' => 0.0, 'digital_total' => 0.0];
$recentOrders = [];
$weeklySales = [];
$topItems = [];
$forecastNextWeek = 0.0;
$forecastMethodLabel = formatForecastMethodLabel('moving_average', true);
$activeUsers = 0;
$orderTrend = ['value' => '0%', 'positive' => true];
$salesTrend = ['value' => '0%', 'positive' => true];
$netSalesTrend = ['value' => '0%', 'positive' => true];
$activeUsersTrend = ['value' => '0%', 'positive' => true];

$buildTrend = static function ($current, $previous): array {
    $trend = buildPercentageTrend((float) $current, (float) $previous);
    return [
        'value' => $trend['value'],
        'positive' => $trend['positive'],
    ];
};

try {
    $totalOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'completed'")->fetchColumn();
    $totalSales = (float) getTotalSales($pdo);
    $today = getSalesSummary($pdo, date('Y-m-d'));

    $periods = $pdo->query("SELECT
            SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN 1 ELSE 0 END) AS current_week_orders,
            SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN 1 ELSE 0 END) AS previous_week_orders,
            SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN GREATEST(total_amount - refund_amount, 0) ELSE 0 END) AS current_week_sales,
            SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN GREATEST(total_amount - refund_amount, 0) ELSE 0 END) AS previous_week_sales
        FROM orders
        WHERE payment_status = 'completed'")->fetch(PDO::FETCH_ASSOC) ?: [];

    $yesterday = getSalesSummary($pdo, date('Y-m-d', strtotime('-1 day')));
    $activeUserMetrics = getDashboardActiveUserTrend($pdo);
    $activeUsers = (int) ($activeUserMetrics['active_now'] ?? 0);
    $activeUsersTrend = $activeUserMetrics['trend'] ?? ['value' => '0%', 'positive' => true];

    $orderTrend = $buildTrend($periods['current_week_orders'] ?? 0, $periods['previous_week_orders'] ?? 0);
    $salesTrend = $buildTrend($periods['current_week_sales'] ?? 0, $periods['previous_week_sales'] ?? 0);
    $netSalesTrend = $buildTrend($today['net_sales'] ?? 0, $yesterday['net_sales'] ?? 0);

    $recentOrders = $pdo->query("SELECT o.id, o.receipt_number, o.created_at, o.total_amount, o.refund_amount, o.payment_method,
            COALESCE(c.name, 'Walk-in') AS customer_name,
            GROUP_CONCAT(oi.item_name_snapshot SEPARATOR ', ') AS items
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        JOIN order_items oi ON oi.order_id = o.id
        GROUP BY o.id, o.receipt_number, o.created_at, o.total_amount, o.refund_amount, o.payment_method, c.name
        ORDER BY o.created_at DESC
        LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    $weeklySalesSeries = getDashboardSalesSeries($pdo, 7);

    $topItems = getTopSellingItems($pdo, 5);
    $salesForecast = getSalesForecastData($pdo, 7);
    $forecastNextWeek = (float) ($salesForecast['forecast_total'] ?? 0.0);
    $forecastMethodLabel = (string) ($salesForecast['method_label'] ?? formatForecastMethodLabel('moving_average', true));
} catch (Throwable $e) {
    $errors[] = 'Dashboard data failed to load. ' . $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="main-content dashboard-shell">
    <div class="page-hero">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Overview of sales, orders, and activity.</p>
        </div>
        <div class="orders-hero-actions">
            <?php if ($canViewAiSuite): ?>
            <a class="btn btn-outline-secondary" href="ai_insights.php">Open AI Suite</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($errors as $error): ?>
                <div><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-stats-grid">
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-head">
                <span class="dashboard-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="6" y="4" width="12" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M9 2.75h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 9h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="dashboard-stat-trend <?php echo $orderTrend['positive'] ? 'positive' : 'negative'; ?>"><?php echo htmlspecialchars($orderTrend['value']); ?> <span aria-hidden="true"><?php echo $orderTrend['positive'] ? '↗' : '↘'; ?></span></span>
            </div>
            <div class="dashboard-stat-label">Total Orders</div>
            <div class="dashboard-stat-value"><?php echo (int) $totalOrders; ?></div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-head">
                <span class="dashboard-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M16 7.5c0-1.93-1.79-3.5-4-3.5s-4 1.57-4 3.5S9.79 11 12 11s4 1.57 4 3.5S14.21 18 12 18s-4-1.57-4-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="dashboard-stat-trend <?php echo $salesTrend['positive'] ? 'positive' : 'negative'; ?>"><?php echo htmlspecialchars($salesTrend['value']); ?> <span aria-hidden="true"><?php echo $salesTrend['positive'] ? '↗' : '↘'; ?></span></span>
            </div>
            <div class="dashboard-stat-label">Total Sales</div>
            <div class="dashboard-stat-value">₱<?php echo number_format((float) $totalSales, 2); ?></div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-head">
                <span class="dashboard-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 16 10 11l3 3 6-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M15 7h4v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="dashboard-stat-trend <?php echo $netSalesTrend['positive'] ? 'positive' : 'negative'; ?>"><?php echo htmlspecialchars($netSalesTrend['value']); ?> <span aria-hidden="true"><?php echo $netSalesTrend['positive'] ? '↗' : '↘'; ?></span></span>
            </div>
            <div class="dashboard-stat-label">Today’s Net Sales</div>
            <div class="dashboard-stat-value">₱<?php echo number_format((float) ($today['net_sales'] ?? 0), 2); ?></div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-head">
                <span class="dashboard-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9.5" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M20 8v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M23 11h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <span class="dashboard-stat-trend <?php echo $activeUsersTrend['positive'] ? 'positive' : 'negative'; ?>"><?php echo htmlspecialchars($activeUsersTrend['value']); ?> <span aria-hidden="true"><?php echo $activeUsersTrend['positive'] ? '↗' : '↘'; ?></span></span>
            </div>
            <div class="dashboard-stat-label">Active Users</div>
            <div class="dashboard-stat-value"><?php echo (int) $activeUsers; ?></div>
        </article>
    </div>

    <div class="dashboard-primary-grid">
        <section class="card dashboard-chart-card">
            <div class="dashboard-card-head">
                <h2 id="dashboardSalesTrendTitle">Sales Trend (Last 7 Days)</h2>
                <select class="form-control dashboard-range-select" id="dashboardSalesRange" aria-label="Sales range">
                    <option value="7" selected>Last 7 Days</option>
                    <option value="14">Last 14 Days</option>
                    <option value="30">Last 30 Days</option>
                </select>
            </div>
            <div class="card-body">
                <div id="weeklySalesFallback" class="text-muted">Loading chart…</div>
                <canvas id="weeklySalesChart" height="120" role="img" aria-label="Weekly sales chart"></canvas>
            </div>
        </section>

        <aside class="card dashboard-top-card">
            <div class="dashboard-card-head">
                <h2>Top Selling Items</h2>
            </div>
            <div class="card-body">
                <ol class="dashboard-top-list">
                    <?php foreach ($topItems as $index => $item): ?>
                    <li class="dashboard-top-item">
                        <span class="dashboard-rank"><?php echo (int) $index + 1; ?></span>
                        <div class="dashboard-top-copy">
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            <span><?php echo (int) $item['total_sold']; ?> sold</span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$topItems): ?>
                        <li class="dashboard-top-empty text-muted">No sales data available.</li>
                    <?php endif; ?>
                </ol>
                <?php if ($canViewAiSuite): ?>
                    <a class="dashboard-report-link" href="analytics.php">View full report</a>
                <?php else: ?>
                    <button type="button" class="dashboard-report-link" onclick="showAdminOnlyReportPopup()">View full report</button>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <div class="dashboard-secondary-grid">
        <section class="card dashboard-forecast-card">
            <div class="dashboard-card-head">
                <h2>Sales Forecast</h2>
                <span class="text-muted">Next 7 Days</span>
            </div>
            <div class="card-body">
                <div class="dashboard-forecast-value">₱<?php echo number_format((float) $forecastNextWeek, 2); ?></div>
                <p class="text-muted mb-2">Projection based on the last 90 days of completed sales.</p>
                <span class="forecast-model-label"><?php echo htmlspecialchars($forecastMethodLabel); ?></span>
            </div>
        </section>

        <section class="card dashboard-transactions-card">
            <div class="dashboard-card-head">
                <h2>Recent Transactions</h2>
                <a class="btn btn-sm btn-outline-secondary" href="orders_history.php">View all</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm dashboard-transactions-table mb-0">
                    <thead>
                        <tr>
                            <th class="col-receipt">Receipt</th>
                            <th class="col-customer">Customer</th>
                            <th class="col-items">Items</th>
                            <th class="text-right col-paid">Paid</th>
                            <th class="text-right col-refunded">Refunded</th>
                            <th class="text-right col-time">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td class="col-receipt"><span><?php echo htmlspecialchars($order['receipt_number']); ?></span></td>
                            <td class="col-customer"><span title="<?php echo htmlspecialchars($order['customer_name']); ?>"><?php echo htmlspecialchars($order['customer_name']); ?></span></td>
                            <td class="col-items"><span title="<?php echo htmlspecialchars($order['items']); ?>"><?php echo htmlspecialchars($order['items']); ?></span></td>
                            <td class="text-right col-paid">₱<?php echo number_format((float) $order['total_amount'], 2); ?></td>
                            <td class="text-right col-refunded">₱<?php echo number_format((float) $order['refund_amount'], 2); ?></td>
                            <td class="text-right col-time"><?php echo htmlspecialchars(date('M d, h:i A', strtotime($order['created_at']))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentOrders): ?>
                            <tr><td colspan="6" class="text-muted text-center py-3">No transactions yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
const weeklySalesData = {
    labels: <?php echo json_encode($weeklySalesSeries['labels']); ?>,
    datasets: [{
        label: 'Net Sales',
        backgroundColor: 'rgba(155, 112, 79, 0.14)',
        borderColor: '#8f6547',
        pointBackgroundColor: '#8f6547',
        data: <?php echo json_encode($weeklySalesSeries['values']); ?>,
        fill: true
    }]
};

let weeklySalesChart = null;

function renderWeeklySalesChart(payload) {
    const fallback = document.getElementById('weeklySalesFallback');
    const canvas = document.getElementById('weeklySalesChart');
    const title = document.getElementById('dashboardSalesTrendTitle');
    const labels = payload.labels || [];
    const values = payload.values || [];
    const days = Number(payload.days || labels.length || 7);

    if (title) {
        title.textContent = 'Sales Trend (Last ' + days + ' Days)';
    }

    if (!labels.length) {
        if (fallback) {
            fallback.style.display = 'block';
            fallback.textContent = 'No sales data available.';
        }
        if (canvas) canvas.style.display = 'none';
        if (weeklySalesChart) {
            weeklySalesChart.destroy();
            weeklySalesChart = null;
        }
        return;
    }

    if (fallback) fallback.style.display = 'none';
    if (canvas) canvas.style.display = 'block';

    if (weeklySalesChart) {
        weeklySalesChart.data.labels = labels;
        weeklySalesChart.data.datasets[0].data = values;
        weeklySalesChart.update();
        return;
    }

    weeklySalesChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Net Sales',
                backgroundColor: 'rgba(155, 112, 79, 0.14)',
                borderColor: '#8f6547',
                pointBackgroundColor: '#8f6547',
                data: values,
                fill: true
            }]
        },
        options: {
            legend: { display: false },
            maintainAspectRatio: false,
            elements: {
                line: { tension: 0.35, borderWidth: 3 },
                point: { radius: 0, hitRadius: 12, hoverRadius: 4 }
            },
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        callback: function(value) {
                            return '₱' + value;
                        }
                    },
                    gridLines: {
                        color: 'rgba(122, 87, 67, 0.16)',
                        borderDash: [4, 4],
                        drawBorder: false
                    }
                }],
                xAxes: [{
                    gridLines: { display: false },
                    ticks: { fontColor: '#B08E72' }
                }]
            }
        }
    });
}

const rangeSelect = document.getElementById('dashboardSalesRange');
if (rangeSelect) {
    rangeSelect.addEventListener('change', function () {
        const days = Number(rangeSelect.value || 7);
        fetch('dashboard_sales.php?days=' + encodeURIComponent(days), { credentials: 'same-origin' })
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to load sales trend.');
                }
                renderWeeklySalesChart(data);
            })
            .catch((error) => {
                const fallback = document.getElementById('weeklySalesFallback');
                if (fallback) {
                    fallback.style.display = 'block';
                    fallback.textContent = error.message || 'Unable to load sales trend.';
                }
            });
    });
}

renderWeeklySalesChart({ days: 7, labels: weeklySalesData.labels, values: weeklySalesData.datasets[0].data });

function showAdminOnlyReportPopup() {
    if (window.KinAlertModal && typeof window.KinAlertModal.alert === 'function') {
        window.KinAlertModal.alert('This report is only available for admins.', 'Access Restricted', 'OK');
    } else {
        alert('This report is only available for admins.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
