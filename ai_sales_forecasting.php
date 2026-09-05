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

$title = 'Sales Forecasting';
$feature = getAiSalesForecastingData($pdo);
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <?php renderPageBackButton('analytics.php', 'Back to Analytics'); ?>
            <h1 class="page-title">Sales Forecasting Feature</h1>
            <p class="page-subtitle">Forecast future sales trends from historical completed orders to support inventory planning and resource allocation.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <a class="btn btn-outline-secondary" href="analytics.php">Analytics</a>
        </div>
    </div>

    <div class="ai-highlight-grid">
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Forecast next 7 days</span><div class="ai-module-kpi-value">₱<?php echo number_format((float) $feature['forecast_next_week'], 2); ?></div><p class="ai-module-kpi-copy">Projected net sales from recent completed-order history.</p><span class="forecast-model-label"><?php echo htmlspecialchars((string) ($feature['method_label'] ?? '')); ?></span></article>
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Average daily sales</span><div class="ai-module-kpi-value">₱<?php echo number_format((float) $feature['average_daily_sales'], 2); ?></div><p class="ai-module-kpi-copy">30-day baseline used by the forecasting view.</p></article>
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Peak service window</span><div class="ai-module-kpi-value"><?php echo htmlspecialchars((string) $feature['peak_day_label']); ?></div><p class="ai-module-kpi-copy"><?php echo htmlspecialchars((string) $feature['peak_hour_label']); ?><?php if (!empty($feature['using_forecast_peak'])): ?> · <?php echo htmlspecialchars((string) ($feature['peak_day_source'] ?? 'Forecast-driven')); ?><?php endif; ?></p></article>
    </div>

    <div class="ai-detail-grid mt-4">
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Forecast Drivers</h2><span class="analytics-badge">Top items</span></div>
            <div class="card-body ai-module-body">
                <p class="ai-module-summary">These top-selling items contribute most to the forecast signal.</p>
                <ul class="ai-module-list">
                    <?php foreach ($feature['top_items'] as $item): ?>
                        <li><span><?php echo htmlspecialchars((string) $item['name']); ?></span><strong><?php echo (int) $item['total_qty']; ?> sold</strong></li>
                    <?php endforeach; ?>
                    <?php if (!$feature['top_items']): ?><li><span>No demand history yet.</span><strong>--</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Planning Notes</h2><span class="analytics-badge">Allocation</span></div>
            <div class="card-body ai-module-body">
                <div class="ai-narrative-list">
                    <div class="ai-narrative-item"><span class="ai-narrative-label">Trend</span><p><?php echo htmlspecialchars((string) $feature['trend_label']); ?> and should be reviewed alongside staffing and prep decisions.</p></div>
                    <div class="ai-narrative-item"><span class="ai-narrative-label">Peak Day</span><p><?php echo htmlspecialchars((string) $feature['peak_day_label']); ?> <?php echo !empty($feature['using_forecast_peak']) ? 'is the strongest predicted day from the ARIMA forecast.' : 'is currently the strongest sales day from recent history.'; ?></p></div>
                    <div class="ai-narrative-item"><span class="ai-narrative-label">Peak Hour</span><p><?php echo htmlspecialchars((string) $feature['peak_hour_label']); ?> is the strongest operating window<?php echo !empty($feature['using_forecast_peak']) ? ', informed by SARIMA demand peak hours where available.' : ' from recent completed-order history.'; ?></p></div>
                </div>
            </div>
        </section>
    </div>

    <?php $stockUp = $feature['stock_up_guidance'] ?? ['items' => []]; ?>
    <section class="card ai-module-card mt-4">
        <div class="dashboard-card-head">
            <h2>Ingredient Stock-Up Guidance</h2>
            <span class="analytics-badge">Forecast-linked</span>
        </div>
        <div class="card-body ai-module-body">
            <p class="ai-module-summary">
                Links the <?php echo htmlspecialchars((string) ($feature['method_label'] ?? 'sales forecast')); ?> and demand predictions to ingredient replenishment suggestions.
                <?php if (!empty($stockUp['uplift_percent']) && (float) ($stockUp['uplift_percent'] ?? 0) > 0): ?>
                    Forecast uplift versus baseline: +<?php echo htmlspecialchars((string) $stockUp['uplift_percent']); ?>%.
                <?php endif; ?>
            </p>
            <ul class="ai-module-list">
                <?php foreach ($stockUp['items'] ?? [] as $row): ?>
                    <li>
                        <span>
                            <?php echo htmlspecialchars((string) $row['ingredient']); ?>
                            <?php if (!empty($row['linked_menu_item'])): ?>
                                · linked to <?php echo htmlspecialchars((string) $row['linked_menu_item']); ?>
                            <?php endif; ?>
                            · <?php echo htmlspecialchars((string) $row['reason']); ?>
                        </span>
                        <strong><?php echo rtrim(rtrim(number_format((float) $row['recommended_order'], 2), '0'), '.'); ?> <?php echo htmlspecialchars((string) $row['unit']); ?></strong>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($stockUp['items'])): ?>
                    <li><span>No stock-up actions needed from the current forecast signal.</span><strong>--</strong></li>
                <?php endif; ?>
            </ul>
            <div class="ai-module-link-row">
                <a class="btn btn-outline-secondary" href="inventory.php?panel=reordering">Open Smart Reordering</a>
                <a class="btn btn-outline-secondary" href="ai_demand_prediction.php">Open Demand Prediction</a>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>