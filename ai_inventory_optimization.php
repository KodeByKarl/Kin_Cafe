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

$title = 'Inventory Optimization';
$feature = getAiInventoryOptimizationData($pdo);
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <h1 class="page-title">Inventory Optimization Feature</h1>
            <p class="page-subtitle">Analyze stock levels and 14-day consumption rates to recommend optimal inventory quantities.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <a class="btn btn-outline-secondary" href="inventory.php">Inventory</a>
        </div>
    </div>

    <div class="ai-highlight-grid">
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Ingredients analyzed</span><div class="ai-module-kpi-value"><?php echo count($feature['items']); ?></div><p class="ai-module-kpi-copy">Consumption-based stock guidance.</p><span class="forecast-model-label"><?php echo htmlspecialchars((string) ($feature['method_label'] ?? '')); ?></span></article>
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Balanced items</span><div class="ai-module-kpi-value"><?php echo (int) $feature['balanced_count']; ?></div><p class="ai-module-kpi-copy">Items currently close to the suggested operating range.</p></article>
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Focus area</span><div class="ai-module-kpi-value">Inventory</div><p class="ai-module-kpi-copy">Use this module when planning stock quantities and replenishment cadence.</p></article>
    </div>

    <section class="card ai-module-card mt-4">
        <div class="dashboard-card-head"><h2>Optimization Recommendations</h2><span class="analytics-badge">Stock guidance</span></div>
        <div class="card-body ai-module-body">
            <ul class="ai-module-list">
                <?php foreach ($feature['items'] as $row): ?>
                    <li><span><?php echo htmlspecialchars((string) $row['name']); ?>: <?php echo htmlspecialchars((string) $row['status']); ?> · pressure <?php echo number_format(((float) ($row['stock_pressure'] ?? 0)) * 100, 0); ?>%</span><strong><?php echo rtrim(rtrim(number_format((float) $row['target_stock'], 2), '0'), '.'); ?> <?php echo htmlspecialchars((string) $row['unit']); ?> target</strong></li>
                <?php endforeach; ?>
                <?php if (!$feature['items']): ?><li><span>No ingredient data is available.</span><strong>--</strong></li><?php endif; ?>
            </ul>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>