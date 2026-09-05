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

$title = 'AI Business Suite';
$ai = getAiFeatureSuite($pdo);
$overview = $ai['overview'];
$today = $overview['today'];
$forecastNextWeek = (float) $overview['forecast_next_week'];
$forecastMethodLabel = (string) ($overview['forecast_method_label'] ?? formatForecastMethodLabel('moving_average', true));
$demandMethodLabel = (string) ($ai['demand_prediction']['method_label'] ?? formatForecastMethodLabel('moving_average', true));
$pendingOrders = (int) $overview['pending_orders'];
$lowStockIngredients = $overview['low_stock_ingredients'];
$expiringIngredients = $overview['expiring_ingredients'];
$inventoryHealth = $ai['inventory_health'] ?? getAiInventoryHealthSnapshot($pdo);
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-insights-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <?php renderPageBackButton('analytics.php', 'Back to Analytics'); ?>
            <h1 class="page-title">AI Business Suite</h1>
            <p class="page-subtitle">Eleven dedicated AI-supported features distributed across analytics, inventory, and service workflows.</p>
        </div>
        <div class="analytics-hero-actions">
            <span class="analytics-badge">11 Feature Modules</span>
        </div>
    </div>

    <div class="ai-insights-stats">
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-label">Pending Orders</div>
            <div class="dashboard-stat-value"><?php echo $pendingOrders; ?></div>
            <div class="dashboard-stat-label">Waiting for staff completion</div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-label">Forecast Next 7 Days</div>
            <div class="dashboard-stat-value">₱<?php echo number_format($forecastNextWeek, 2); ?></div>
            <div class="dashboard-stat-label"><?php echo htmlspecialchars($forecastMethodLabel); ?></div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-label">Today Net Sales</div>
            <div class="dashboard-stat-value">₱<?php echo number_format((float) ($today['net_sales'] ?? 0), 2); ?></div>
            <div class="dashboard-stat-label"><?php echo htmlspecialchars((string) $overview['trend_label']); ?></div>
        </article>
    </div>

    <section class="ai-suite-group">
        <div class="dashboard-card-head">
            <div>
                <h2>Analytics Intelligence</h2>
                <p class="dashboard-ai-subtitle">Forecasting, demand analysis, customer insights, pricing guidance, and anomaly review.</p>
            </div>
        </div>
        <div class="ai-module-hub-grid">
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Sales Forecasting</h2><span class="analytics-badge">Analytics</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Predict future sales trends from historical completed orders for better planning and staffing.</p><ul class="ai-module-list"><li><span>Next 7 days</span><strong>₱<?php echo number_format($forecastNextWeek, 2); ?></strong></li><li><span>Peak day</span><strong><?php echo htmlspecialchars((string) $overview['peak_day_label']); ?></strong></li><li><span>Peak hour</span><strong><?php echo htmlspecialchars((string) $overview['peak_hour_label']); ?></strong></li></ul><span class="forecast-model-label"><?php echo htmlspecialchars($forecastMethodLabel); ?></span><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="ai_sales_forecasting.php">Open Sales Forecasting</a></div></div></section>
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Demand Prediction</h2><span class="analytics-badge">Analytics</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Predict demand for specific menu items using recent sales volume, time signals, and ordering patterns.</p><ul class="ai-module-list"><li><span>Tracked menu items</span><strong><?php echo count($ai['demand_prediction']['items']); ?></strong></li><li><span>Peak service window</span><strong><?php echo htmlspecialchars((string) $ai['demand_prediction']['peak_day_label']); ?></strong></li><li><span>Peak hour</span><strong><?php echo htmlspecialchars((string) $ai['demand_prediction']['peak_hour_label']); ?></strong></li></ul><span class="forecast-model-label"><?php echo htmlspecialchars($demandMethodLabel); ?></span><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="ai_demand_prediction.php">Open Demand Prediction</a></div></div></section>
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Customer Preference Analysis</h2><span class="analytics-badge">Analytics</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Analyze purchase history to understand favorite items, preferred categories, and repeat-customer behavior.</p><ul class="ai-module-list"><li><span>Profiled repeat customers</span><strong><?php echo (int) ($ai['customer_preferences']['profiled_customer_count'] ?? count($ai['customer_preferences']['profiles'] ?? [])); ?></strong></li><li><span>Top category</span><strong><?php echo htmlspecialchars((string) (($overview['top_category']['category_name'] ?? 'No data'))); ?></strong></li><li><span>Tracked categories</span><strong><?php echo count($ai['customer_preferences']['categories']); ?></strong></li></ul><span class="forecast-model-label"><?php echo htmlspecialchars((string) ($ai['customer_preferences']['method_label'] ?? aiRuleBasedMethodLabel())); ?></span><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="ai_customer_preferences.php">Open Customer Preferences</a></div></div></section>
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Sales and Inventory Anomaly Detection</h2><span class="analytics-badge">Analytics</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Detect irregular sales spikes, unusual drops, and suspicious inventory adjustments that need review.</p><ul class="ai-module-list"><li><span>Sales anomalies</span><strong><?php echo count($ai['anomaly_detection']['sales']); ?></strong></li><li><span>Inventory anomalies</span><strong><?php echo count($ai['anomaly_detection']['inventory']); ?></strong></li><li><span>30-day baseline</span><strong>₱<?php echo number_format((float) $ai['anomaly_detection']['average_daily_sales'], 2); ?></strong></li></ul><span class="forecast-model-label"><?php echo htmlspecialchars((string) ($ai['anomaly_detection']['method_label'] ?? aiAnomalyMethodLabel())); ?></span><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="ai_anomaly_detection.php">Open Anomaly Detection</a></div></div></section>
        </div>
    </section>

    <section class="ai-suite-group">
        <div class="dashboard-card-head">
            <div>
                <h2>Inventory Intelligence</h2>
                <p class="dashboard-ai-subtitle">Optimization, reordering, and waste reduction aligned to stock operations.</p>
            </div>
        </div>
        <div class="ai-module-hub-grid">
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Inventory Optimization</h2><span class="analytics-badge">Inventory</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Recommend optimal stock quantities from 14-day ingredient usage rates to reduce shortages and overstocking.</p><ul class="ai-module-list"><li><span>Ingredients analyzed</span><strong><?php echo count($ai['inventory_optimization']['items']); ?></strong></li><li><span>Low stock alerts</span><strong><?php echo count($lowStockIngredients); ?></strong></li><li><span>Balanced items</span><strong><?php echo (int) $ai['inventory_optimization']['balanced_count']; ?></strong></li></ul><span class="forecast-model-label"><?php echo htmlspecialchars((string) ($ai['inventory_optimization']['method_label'] ?? aiRuleBasedMethodLabel())); ?></span><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="ai_inventory_optimization.php">Open Inventory Optimization</a></div></div></section>
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Waste Reduction and Inventory Efficiency</h2><span class="analytics-badge">Inventory</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Analyze expiring stock, slow movers, and removal logs to reduce waste and improve inventory efficiency.</p><ul class="ai-module-list"><li><span>Expiring soon</span><strong><?php echo count($expiringIngredients); ?></strong></li><li><span>Waste alerts</span><strong><?php echo count($ai['waste_reduction']['waste_alerts']); ?></strong></li><li><span>Slow movers</span><strong><?php echo count($ai['waste_reduction']['slow_moving_items']); ?></strong></li></ul><span class="forecast-model-label"><?php echo htmlspecialchars((string) ($ai['waste_reduction']['method_label'] ?? aiRuleBasedMethodLabel())); ?></span><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="ai_waste_reduction.php">Open Waste Reduction</a></div></div></section>
            <section class="card ai-module-card ai-module-card-overview"><div class="dashboard-card-head"><h2>Inventory Health Snapshot</h2><span class="analytics-badge"><?php echo htmlspecialchars((string) ($inventoryHealth['label'] ?? 'Overview')); ?></span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Computed health score from stock pressure, low-stock alerts, expiry risk, and consumption balance.</p><ul class="ai-module-list"><li><span>Health score</span><strong><?php echo (int) ($inventoryHealth['score'] ?? 0); ?>/100</strong></li><li><span>Low stock ingredients</span><strong><?php echo (int) ($inventoryHealth['low_stock_count'] ?? count($lowStockIngredients)); ?></strong></li><li><span>Expiring ingredients</span><strong><?php echo (int) ($inventoryHealth['expiring_count'] ?? count($expiringIngredients)); ?></strong></li><li><span>Under target</span><strong><?php echo (int) ($inventoryHealth['under_target_count'] ?? 0); ?></strong></li></ul><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="inventory.php?tab=optimization">Open Inventory Module</a></div></div></section>
        </div>
    </section>

    <section class="ai-suite-group">
        <div class="dashboard-card-head">
            <div>
                <h2>Service Intelligence</h2>
                <p class="dashboard-ai-subtitle">Customer-facing recommendation and assistant features connected to the order workflow.</p>
            </div>
        </div>
        <div class="ai-module-hub-grid">
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Recommendation System</h2><span class="analytics-badge">POS</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Suggest menu pairings from order co-occurrence patterns and surface live add-on recommendations in the POS sidebar.</p><ul class="ai-module-list"><li><span>Pairings tracked</span><strong><?php echo count($ai['recommendation_system']['pairings'] ?? []); ?></strong></li><li><span>Context rules</span><strong><?php echo count($ai['recommendation_system']['customer_recommendations'] ?? []); ?></strong></li><li><span>POS panel</span><strong>Live</strong></li></ul><span class="forecast-model-label"><?php echo htmlspecialchars((string) ($ai['recommendation_system']['method_label'] ?? aiRecommendationMethodLabel())); ?></span><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="ai_recommendation_system.php">Open Recommendation System</a><a class="btn btn-outline-secondary" href="pos.php">Open POS</a></div></div></section>
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Chatbot / Virtual Assistant</h2><span class="analytics-badge">POS</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Provide menu help, order guidance, and operational answers through a dedicated virtual-assistant surface.</p><ul class="ai-module-list"><li><span>Suggested prompts</span><strong><?php echo count($ai['virtual_assistant']['prompts']); ?></strong></li><li><span>Pending orders</span><strong><?php echo $pendingOrders; ?></strong></li><li><span>Best location</span><strong>POS</strong></li></ul><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="ai_virtual_assistant.php">Open Virtual Assistant</a></div></div></section>
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Bulk Menu Import</h2><span class="analytics-badge">Service</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Import menu items using a spreadsheet-style CSV and auto-create missing categories for fast menu updates.</p><ul class="ai-module-list"><li><span>Import format</span><strong>Name / Category / Price / Available / Ingredients</strong></li><li><span>Ingredients format</span><strong>Name:Quantity Unit; ...</strong></li><li><span>Target page</span><strong>Menu Management</strong></li></ul><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="menu_management.php">Open Menu Import</a></div></div></section>
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Bulk Ingredient Import</h2><span class="analytics-badge">Inventory</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">Import ingredients via CSV with package units, stock quantities, and optional manufacturing / expiration dates.</p><ul class="ai-module-list"><li><span>Supported units</span><strong>pcs, grams, kg, ml, liters</strong></li><li><span>Quantity handling</span><strong>Package amount × stock units</strong></li><li><span>Target page</span><strong>Inventory Management</strong></li></ul><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="inventory.php">Open Ingredient Import</a></div></div></section>
            <section class="card ai-module-card"><div class="dashboard-card-head"><h2>Smart Kitchen Workflow Optimization</h2><span class="analytics-badge">Orders</span></div><div class="card-body ai-module-body"><p class="ai-module-summary">AI organizes the kitchen order queue to reduce wait times, surface high-pressure tickets, and improve preparation flow.</p><ul class="ai-module-list"><li><span>Pending tickets scored</span><strong><?php echo (int) $ai['kitchen_workflow_optimization']['pending_count']; ?></strong></li><li><span>Rush-first tickets</span><strong><?php echo (int) $ai['kitchen_workflow_optimization']['high_priority_count']; ?></strong></li><li><span>Next up</span><strong><?php echo htmlspecialchars((string) (($ai['kitchen_workflow_optimization']['next_up']['receipt_number'] ?? 'No pending orders'))); ?></strong></li></ul><div class="ai-module-link-row"><a class="btn btn-outline-secondary" href="orders_history.php">Open Orders Module</a></div></div></section>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>