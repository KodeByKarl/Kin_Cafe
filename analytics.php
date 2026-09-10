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

$title = 'Data Analytics';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_promotion'])) {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            $discountType = $_POST['discount_type'] ?? 'percent';
            $discountValue = (float) ($_POST['discount_value'] ?? 0);
            $minimumOrder = (float) ($_POST['minimum_order'] ?? 0);
            $startAt = trim((string) ($_POST['start_at'] ?? ''));
            $endAt = trim((string) ($_POST['end_at'] ?? ''));

            if ($code === '' || $name === '') {
                throw new InvalidArgumentException('Promotion code and name are required.');
            }
            if (!in_array($discountType, ['fixed', 'percent'], true)) {
                throw new InvalidArgumentException('Invalid promotion type.');
            }
            if ($discountValue <= 0) {
                throw new InvalidArgumentException('Promotion value must be greater than zero.');
            }

            $stmt = $pdo->prepare('INSERT INTO promotions (code, name, discount_type, discount_value, minimum_order, start_at, end_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $code,
                $name,
                $discountType,
                $discountValue,
                $minimumOrder,
                $startAt !== '' ? $startAt : null,
                $endAt !== '' ? $endAt : null,
                $_SESSION['admin'],
            ]);
            logAuditEvent($pdo, 'promotion_created', 'promotion', (int) $pdo->lastInsertId(), ['code' => $code]);
            $success = 'Promotion created.';
        }

        if (isset($_POST['toggle_promotion'])) {
            $promotionId = (int) ($_POST['promotion_id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE promotions SET active = IF(active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([$promotionId]);
            logAuditEvent($pdo, 'promotion_toggled', 'promotion', $promotionId);
            $success = 'Promotion status updated.';
        }

        if (isset($_POST['reconcile_today'])) {
            reconcileDailySales($pdo, date('Y-m-d'), (int) $_SESSION['admin'], '');
            logAuditEvent($pdo, 'sales_reconciled', 'daily_reconciliation', null, ['business_date' => date('Y-m-d')]);
            $success = 'Daily sales reconciled.';
        }

        if (isset($_POST['backup_now'])) {
            $backup = runAutomaticBackup($pdo, 'manual', true);
            logAuditEvent($pdo, 'backup_created', 'backup', null, $backup ?: []);
            $success = 'Backup created successfully.';
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$today = getSalesSummary($pdo, date('Y-m-d'));
$reportStart = date('Y-m-d', strtotime('-29 days'));
$reportEnd = date('Y-m-d');
$promotions = $pdo->query('SELECT * FROM promotions ORDER BY created_at DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
$recentBackups = $pdo->query('SELECT * FROM backups ORDER BY created_at DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
$recentReconciliations = $pdo->query('SELECT dr.*, u.username FROM daily_reconciliations dr LEFT JOIN users u ON dr.reconciled_by = u.id ORDER BY dr.business_date DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
$salesPerformanceStmt = $pdo->prepare("SELECT DATE(created_at) AS sale_date, COALESCE(SUM(GREATEST(total_amount - refund_amount, 0)), 0) AS sales
    FROM orders
    WHERE payment_status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY sale_date");
$salesPerformanceStmt->execute([$reportStart, $reportEnd]);
$salesPerformance = $salesPerformanceStmt->fetchAll(PDO::FETCH_ASSOC);
$categorySales = $pdo->query("SELECT COALESCE(mc.name, 'Uncategorized') AS category_name, COALESCE(SUM(GREATEST(oi.line_total, 0)), 0) AS total_sales
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
    LEFT JOIN menu_categories mc ON mc.id = mi.category_id
    WHERE o.payment_status = 'completed' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY mc.id, mc.name
    ORDER BY total_sales DESC
    LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$averageOrderValue = (float) ($today['order_count'] ?? 0) > 0 ? ((float) $today['net_sales'] / (float) $today['order_count']) : 0.0;
$newCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$topItems = getTopSellingItems($pdo, 5);
$topCustomersStmt = $pdo->prepare("SELECT c.name, c.phone, c.loyalty_points, COALESCE(SUM(GREATEST(o.total_amount - o.refund_amount, 0)), 0) AS sales_total
    FROM customers c
    LEFT JOIN orders o ON o.customer_id = c.id AND o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY c.id, c.name, c.phone, c.loyalty_points
    ORDER BY sales_total DESC, c.loyalty_points DESC
    LIMIT 5");
$topCustomersStmt->execute([$reportStart, $reportEnd]);
$topCustomers = $topCustomersStmt->fetchAll(PDO::FETCH_ASSOC);

$salesForecast = getSalesForecastData($pdo, 7);
$analyticsTrends = getAnalyticsKpiTrends($pdo);
$aiSalesForecast = getAiSalesForecastingData($pdo);
$aiDemandPrediction = getAiDemandPredictionData($pdo);
$aiCustomerPreferences = getAiCustomerPreferencesData($pdo);
$aiAnomalyDetection = getAiAnomalyDetectionData($pdo);

$initialAnalyticsPayload = [
    'success' => true,
    'today' => $today,
    'sales_data' => $salesPerformance,
    'category_sales' => $categorySales,
    'top_items' => $topItems,
    'top_customers' => $topCustomers,
    'forecast' => (float) ($salesForecast['forecast_total'] ?? 0),
    'forecast_method_label' => (string) ($salesForecast['method_label'] ?? ''),
    'trends' => $analyticsTrends,
];
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page">
    <div class="page-hero analytics-hero">
        <div>
            <?php renderPageBackButton('dashboard.php', 'Back to Dashboard'); ?>
            <h1 class="page-title">Data Analytics</h1>
            <p class="page-subtitle">Live sales reporting and business intelligence.</p>
        </div>
        <div class="analytics-hero-actions">
            <span class="analytics-badge">Auto-refreshes every 30s</span>
            <span class="analytics-badge">Last 30 Days</span>
            <a class="btn btn-outline-secondary ai-shortcut-btn" href="ai_insights.php"><span class="ai-shortcut-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M14 5h5v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 14 19 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Open AI Suite</span></a>
            <a class="btn btn-outline-secondary" href="advanced_analytics.php">Advanced Analytics</a>
            <a class="btn btn-primary" href="analytics_export.php?type=report&amp;format=excel&amp;start=<?php echo urlencode($reportStart); ?>&amp;end=<?php echo urlencode($reportEnd); ?>">Export Report</a>
        </div>
    </div>

    <div class="card ai-module-card ai-suite-quick-links">
        <div class="card-body">
            <div class="analytics-forecast-banner">
                <div>
                    <span class="text-muted">Next 7-day sales forecast</span>
                    <strong id="analyticsForecastValue">₱<?php echo number_format((float) ($salesForecast['forecast_total'] ?? 0), 2); ?></strong>
                </div>
                <span class="forecast-model-label" id="analyticsForecastMethodLabel"><?php echo htmlspecialchars((string) ($salesForecast['method_label'] ?? '')); ?></span>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card ai-module-card analytics-feature-tabs-card">
        <div class="card-body">
            <ul class="nav nav-tabs analytics-feature-tabs" id="analyticsFeatureTabs" role="tablist">
                <li class="nav-item"><button type="button" class="nav-link active" data-analytics-tab="overview" role="tab" aria-selected="true">Overview</button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-analytics-tab="sales-forecasting" role="tab" aria-selected="false">Sales Forecasting</button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-analytics-tab="demand-prediction" role="tab" aria-selected="false">Demand Prediction</button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-analytics-tab="customer-preferences" role="tab" aria-selected="false">Customer Preferences</button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-analytics-tab="anomaly-detection" role="tab" aria-selected="false">Anomaly Detection</button></li>
            </ul>
            <?php renderTabBackButton('analyticsTabBack', 'Back to Overview'); ?>
        </div>
    </div>

    <div class="analytics-tab-panel active" data-analytics-panel="overview">
    <div class="dashboard-stats-grid analytics-stats-grid">
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-head">
                <span class="dashboard-stat-icon">$</span>
                <span class="dashboard-stat-trend <?php echo $analyticsTrends['net_sales']['positive'] ? 'positive' : 'negative'; ?>" id="trendNetSales"><?php echo htmlspecialchars($analyticsTrends['net_sales']['value']); ?> <?php echo $analyticsTrends['net_sales']['positive'] ? '↗' : '↘'; ?></span>
            </div>
            <div class="dashboard-stat-label">Net Sales Today</div>
            <div class="dashboard-stat-value" id="todayNetSales">₱<?php echo number_format($today['net_sales'], 2); ?></div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-head">
                <span class="dashboard-stat-icon">◫</span>
                <span class="dashboard-stat-trend <?php echo $analyticsTrends['orders']['positive'] ? 'positive' : 'negative'; ?>" id="trendOrders"><?php echo htmlspecialchars($analyticsTrends['orders']['value']); ?> <?php echo $analyticsTrends['orders']['positive'] ? '↗' : '↘'; ?></span>
            </div>
            <div class="dashboard-stat-label">Total Orders</div>
            <div class="dashboard-stat-value" id="todayOrderCount"><?php echo (int) ($today['order_count'] ?? 0); ?></div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-head">
                <span class="dashboard-stat-icon">↗</span>
                <span class="dashboard-stat-trend <?php echo $analyticsTrends['average_order_value']['positive'] ? 'positive' : 'negative'; ?>" id="trendAov"><?php echo htmlspecialchars($analyticsTrends['average_order_value']['value']); ?> <?php echo $analyticsTrends['average_order_value']['positive'] ? '↗' : '↘'; ?></span>
            </div>
            <div class="dashboard-stat-label">Average Order Value</div>
            <div class="dashboard-stat-value" id="avgOrderValue">₱<?php echo number_format($averageOrderValue, 2); ?></div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-head">
                <span class="dashboard-stat-icon">◌</span>
                <span class="dashboard-stat-trend <?php echo $analyticsTrends['new_customers']['positive'] ? 'positive' : 'negative'; ?>" id="trendNewCustomers"><?php echo htmlspecialchars($analyticsTrends['new_customers']['value']); ?> <?php echo $analyticsTrends['new_customers']['positive'] ? '↗' : '↘'; ?></span>
            </div>
            <div class="dashboard-stat-label">New Customers</div>
            <div class="dashboard-stat-value" id="newCustomersCount"><?php echo $newCustomers; ?></div>
        </article>
    </div>

    <div class="analytics-primary-grid">
        <section class="card analytics-chart-card dashboard-chart-card">
            <div class="dashboard-card-head">
                <h2>Sales Performance</h2>
            </div>
            <div class="card-body">
                <div id="salesChartFallback" class="text-muted">Loading chart…</div>
                <canvas id="salesChart" role="img" aria-label="Sales performance chart"></canvas>
            </div>
        </section>
        <aside class="card analytics-category-card">
            <div class="dashboard-card-head">
                <h2>Sales by Category</h2>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" height="220"></canvas>
                <ul class="analytics-category-list" id="categoryLegendList">
                    <?php foreach ($categorySales as $index => $row): ?>
                        <li>
                            <span class="analytics-category-dot analytics-dot-<?php echo $index % 5; ?>"></span>
                            <span><?php echo htmlspecialchars($row['category_name']); ?></span>
                            <strong><?php echo number_format((float) $row['total_sales'], 2); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>
    </div>

    <div class="analytics-secondary-grid">
        <section class="card">
            <div class="dashboard-card-head">
                <h2>Top Selling Items</h2>
            </div>
            <div class="card-body">
                <ul class="analytics-simple-list" id="topItemsList">
                    <?php foreach ($topItems as $item): ?>
                        <li>
                            <span><?php echo htmlspecialchars((string) ($item['name'] ?? 'Unknown item')); ?></span>
                            <strong><?php echo (int) ($item['total_sold'] ?? 0); ?></strong>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$topItems): ?>
                        <li><span>No data available.</span><strong>--</strong></li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>
        <section class="card">
            <div class="dashboard-card-head">
                <h2>Top Customers</h2>
            </div>
            <div class="card-body">
                <ul class="analytics-simple-list" id="topCustomersList">
                    <?php foreach ($topCustomers as $customer): ?>
                        <li>
                            <span><?php echo htmlspecialchars((string) ($customer['name'] ?: ($customer['phone'] ?: 'Walk-in'))); ?></span>
                            <strong>₱<?php echo number_format((float) ($customer['sales_total'] ?? 0), 2); ?></strong>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$topCustomers): ?>
                        <li><span>No data available.</span><strong>--</strong></li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>
    </div>
    </div>

    <div class="analytics-tab-panel" data-analytics-panel="sales-forecasting" hidden>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Sales Forecasting</h2><a class="btn btn-sm btn-outline-secondary" href="ai_sales_forecasting.php">Open module</a></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <li><span>Next 7 days</span><strong>₱<?php echo number_format((float) $aiSalesForecast['forecast_next_week'], 2); ?></strong></li>
                    <li><span>Peak day</span><strong><?php echo htmlspecialchars((string) $aiSalesForecast['peak_day_label']); ?></strong></li>
                    <li><span>Peak hour</span><strong><?php echo htmlspecialchars((string) $aiSalesForecast['peak_hour_label']); ?></strong></li>
                </ul>
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($aiSalesForecast['method_label'] ?? '')); ?></span>
                <?php if (!empty($aiSalesForecast['using_forecast_peak'])): ?>
                    <p class="text-muted mb-0 mt-2"><?php echo htmlspecialchars((string) ($aiSalesForecast['peak_day_source'] ?? '')); ?></p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="analytics-tab-panel" data-analytics-panel="demand-prediction" hidden>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Demand Prediction</h2><a class="btn btn-sm btn-outline-secondary" href="ai_demand_prediction.php">Open module</a></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach (array_slice($aiDemandPrediction['items'], 0, 6) as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['name']); ?></span><strong><?php echo number_format((float) $row['predicted_week_units'], 1); ?> units / 7 days</strong></li>
                    <?php endforeach; ?>
                    <?php if (!$aiDemandPrediction['items']): ?><li><span>No demand history yet.</span><strong>--</strong></li><?php endif; ?>
                </ul>
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($aiDemandPrediction['method_label'] ?? '')); ?></span>
            </div>
        </section>
    </div>

    <div class="analytics-tab-panel" data-analytics-panel="customer-preferences" hidden>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Customer Preferences</h2><a class="btn btn-sm btn-outline-secondary" href="ai_customer_preferences.php">Open module</a></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach (array_slice($aiCustomerPreferences['profiles'], 0, 6) as $profile): ?>
                        <li><span><?php echo htmlspecialchars((string) $profile['customer_label']); ?> — <?php echo htmlspecialchars((string) $profile['favorite_item']); ?></span><strong><?php echo (int) $profile['order_count']; ?> orders</strong></li>
                    <?php endforeach; ?>
                    <?php if (!$aiCustomerPreferences['profiles']): ?><li><span>No repeat customer profiles yet.</span><strong>--</strong></li><?php endif; ?>
                </ul>
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($aiCustomerPreferences['method_label'] ?? '')); ?></span>
            </div>
        </section>
    </div>

    <div class="analytics-tab-panel" data-analytics-panel="anomaly-detection" hidden>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Anomaly Detection</h2><a class="btn btn-sm btn-outline-secondary" href="ai_anomaly_detection.php">Open module</a></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach ($aiAnomalyDetection['sales'] as $row): ?>
                        <li>
                            <a class="ai-anomaly-trigger" href="ai_anomaly_detection.php">
                                <span><?php echo htmlspecialchars((string) $row['sale_date']); ?> — <?php echo htmlspecialchars((string) ($row['detection_method'] ?? 'Z-score')); ?></span>
                                <strong><?php echo htmlspecialchars((string) $row['type']); ?> · ₱<?php echo number_format((float) ($row['gap_amount'] ?? 0), 2); ?></strong>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$aiAnomalyDetection['sales']): ?><li><span>No sales anomalies detected.</span><strong>OK</strong></li><?php endif; ?>
                </ul>
                <p class="mb-0 mt-2"><a class="btn btn-sm btn-outline-secondary" href="ai_anomaly_detection.php">Open Stock Review &amp; variance drill-down</a></p>
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($aiAnomalyDetection['method_label'] ?? '')); ?></span>
            </div>
        </section>
    </div>

<script>
let salesChart;
let categoryChart;
const initialAnalyticsData = <?php echo json_encode($initialAnalyticsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderList(listId, items, formatter) {
    const container = document.getElementById(listId);
    if (!container) {
        return;
    }
    if (!items.length) {
        container.innerHTML = '<li><span>No data available.</span><strong>--</strong></li>';
        return;
    }
    container.innerHTML = items.map(formatter).join('');
}

function renderCategoryLegend(items) {
    const container = document.getElementById('categoryLegendList');
    if (!container) {
        return;
    }
    if (!items.length) {
        container.innerHTML = '<li><span class="analytics-category-dot analytics-dot-0"></span><span>No data available.</span><strong>--</strong></li>';
        return;
    }

    container.innerHTML = items.map((item, index) => `
        <li>
            <span class="analytics-category-dot analytics-dot-${index % 5}"></span>
            <span>${escapeHtml(item.category_name)}</span>
            <strong>${Number(item.total_sales || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
        </li>
    `).join('');
}

function updateTrendBadge(elementId, trend) {
    const element = document.getElementById(elementId);
    if (!element || !trend) {
        return;
    }
    element.classList.remove('positive', 'negative');
    element.classList.add(trend.positive ? 'positive' : 'negative');
    element.textContent = `${trend.value} ${trend.positive ? '↗' : '↘'}`;
}

function renderAnalyticsReport(data) {
    if (!data || !data.success) {
        return;
    }

    const today = data.today || {};
    const salesRows = Array.isArray(data.sales_data) ? data.sales_data : [];
    const categoryTotals = Array.isArray(data.category_sales) ? data.category_sales : [];
    const topItems = Array.isArray(data.top_items) ? data.top_items : [];
    const topCustomers = Array.isArray(data.top_customers) ? data.top_customers : [];

    document.getElementById('todayNetSales').textContent = `₱${Number(today.net_sales || 0).toFixed(2)}`;
    document.getElementById('todayOrderCount').textContent = `${Number(today.order_count || 0)}`;
    document.getElementById('avgOrderValue').textContent = `₱${(Number(today.order_count || 0) > 0 ? Number(today.net_sales || 0) / Number(today.order_count || 0) : 0).toFixed(2)}`;

    const forecastValue = document.getElementById('analyticsForecastValue');
    if (forecastValue && typeof data.forecast !== 'undefined') {
        forecastValue.textContent = `₱${Number(data.forecast || 0).toFixed(2)}`;
    }
    const forecastMethodLabel = document.getElementById('analyticsForecastMethodLabel');
    if (forecastMethodLabel && data.forecast_method_label) {
        forecastMethodLabel.textContent = data.forecast_method_label;
    }

    if (data.trends) {
        updateTrendBadge('trendNetSales', data.trends.net_sales);
        updateTrendBadge('trendOrders', data.trends.orders);
        updateTrendBadge('trendAov', data.trends.average_order_value);
        updateTrendBadge('trendNewCustomers', data.trends.new_customers);
    }

    renderCategoryLegend(categoryTotals);
    renderList('topItemsList', topItems, (item) => `<li><span>${escapeHtml(item.name || 'Unknown item')}</span><strong>${Number(item.total_sold || 0)}</strong></li>`);
    renderList('topCustomersList', topCustomers, (customer) => `<li><span>${escapeHtml(customer.name || customer.phone || 'Walk-in')}</span><strong>₱${Number(customer.sales_total || 0).toFixed(2)}</strong></li>`);

    const salesCanvas = document.getElementById('salesChart');
    const salesFallback = document.getElementById('salesChartFallback');
    const categoryCanvas = document.getElementById('categoryChart');
    if (!salesCanvas || !categoryCanvas || typeof Chart === 'undefined') {
        return;
    }

    if (salesFallback) {
        salesFallback.style.display = 'none';
    }

    if (!salesRows.length) {
        if (salesFallback) {
            salesFallback.style.display = 'block';
            salesFallback.textContent = 'No sales data available.';
        }
        salesCanvas.style.display = 'none';
    } else {
        salesCanvas.style.display = 'block';
    }

    const salesCtx = salesCanvas.getContext('2d');
    const categoryCtx = categoryCanvas.getContext('2d');

    if (salesChart) salesChart.destroy();
    if (categoryChart) categoryChart.destroy();

    if (salesRows.length) {
        salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesRows.map((row) => {
                    const date = new Date(row.sale_date);
                    return Number.isNaN(date.getTime()) ? row.sale_date : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Net Sales',
                    data: salesRows.map((row) => row.sales),
                    borderColor: '#8f6547',
                    backgroundColor: 'rgba(155, 112, 79, 0.14)',
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                elements: {
                    line: { tension: 0.35, borderWidth: 3 },
                    point: { radius: 0, hitRadius: 12, hoverRadius: 4 }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#6f513d',
                            callback: function(value) {
                                return '₱' + value;
                            }
                        },
                        grid: {
                            color: 'rgba(122, 87, 67, 0.16)',
                            borderDash: [4, 4],
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            color: '#b08e72'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    const palette = ['#5b3f30', '#8c684b', '#b79779', '#d8c6b1', '#eee2d2'];
    categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: categoryTotals.map((row) => row.category_name),
            datasets: [{
                data: categoryTotals.map((row) => row.total_sales),
                backgroundColor: palette,
                borderColor: '#fffdfa',
                borderWidth: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    bodyColor: '#35251d',
                    titleColor: '#35251d',
                    backgroundColor: '#fffaf5',
                    borderColor: '#decebd',
                    borderWidth: 1,
                }
            },
            cutout: '60%'
        }
    });
}

function loadSalesReport() {
    fetch('sales_report.php', {
        headers: {
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
        .then(async (response) => {
            if (!response.ok) {
                throw new Error(`Report request failed with status ${response.status}.`);
            }
            return response.json();
        })
        .then((data) => {
            if (!data.success) {
                throw new Error(data.message || 'Unable to load report data.');
            }

            renderAnalyticsReport(data);
        })
        .catch((error) => {
            console.error('Analytics refresh failed.', error);
        });
}

function initAnalyticsFeatureTabs() {
    const tabButtons = document.querySelectorAll('[data-analytics-tab]');
    const panels = document.querySelectorAll('[data-analytics-panel]');
    const back = document.getElementById('analyticsTabBack');
    function activate(target, button) {
        tabButtons.forEach((item) => {
            const isActive = item.getAttribute('data-analytics-tab') === target;
            item.classList.toggle('active', isActive);
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            const isActive = panel.getAttribute('data-analytics-panel') === target;
            panel.classList.toggle('active', isActive);
            panel.hidden = !isActive;
        });
        if (back) {
            back.hidden = target === 'overview';
        }
    }
    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activate(button.getAttribute('data-analytics-tab'), button);
        });
    });
    if (back) {
        back.addEventListener('click', () => activate('overview'));
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initAnalyticsFeatureTabs();
    renderAnalyticsReport(initialAnalyticsData);
    loadSalesReport();
    setInterval(loadSalesReport, 30000);
});
</script>

<?php include 'includes/footer.php'; ?>
