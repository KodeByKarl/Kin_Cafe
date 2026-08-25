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

$title = 'Smart Reordering';
$feature = getAiSmartReorderingData($pdo);
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <h1 class="page-title">Smart Reordering Feature</h1>
            <p class="page-subtitle">Generate reorder guidance when ingredient stock drops below consumption-based reorder points.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <a class="btn btn-outline-secondary" href="inventory.php?tab=reordering">Inventory Tab</a>
        </div>
    </div>

    <div class="ai-highlight-grid">
        <article class="ai-module-kpi">
            <span class="ai-module-kpi-label">Reorder candidates</span>
            <div class="ai-module-kpi-value"><?php echo count($feature['items']); ?></div>
            <p class="ai-module-kpi-copy">Ingredients below target or with a positive recommended order quantity.</p>
            <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($feature['method_label'] ?? '')); ?></span>
        </article>
        <article class="ai-module-kpi">
            <span class="ai-module-kpi-label">Planning window</span>
            <div class="ai-module-kpi-value">14 days</div>
            <p class="ai-module-kpi-copy">Usage rate derived from recent inventory consumption logs.</p>
        </article>
        <article class="ai-module-kpi">
            <span class="ai-module-kpi-label">Linked forecast</span>
            <div class="ai-module-kpi-value">Sales</div>
            <p class="ai-module-kpi-copy">Stock-up guidance also appears on the Sales Forecasting module.</p>
        </article>
    </div>

    <section class="card ai-module-card mt-4">
        <div class="dashboard-card-head"><h2>Reorder Recommendations</h2><span class="analytics-badge">Priority list</span></div>
        <div class="card-body ai-module-body">
            <ul class="ai-module-list">
                <?php foreach ($feature['items'] as $row): ?>
                    <li>
                        <span><?php echo htmlspecialchars((string) $row['name']); ?> · stock <?php echo rtrim(rtrim(number_format((float) $row['current_stock'], 2), '0'), '.'); ?> <?php echo htmlspecialchars((string) $row['unit']); ?></span>
                        <strong><?php echo rtrim(rtrim(number_format((float) $row['recommended_order'], 2), '0'), '.'); ?> <?php echo htmlspecialchars((string) $row['unit']); ?></strong>
                    </li>
                <?php endforeach; ?>
                <?php if (!$feature['items']): ?><li><span>No ingredient is currently below the reorder point.</span><strong>--</strong></li><?php endif; ?>
            </ul>
            <div class="ai-module-link-row">
                <a class="btn btn-outline-secondary" href="ai_sales_forecasting.php">Open Sales Forecasting</a>
                <a class="btn btn-outline-secondary" href="inventory.php?tab=reordering">Open Inventory Reordering</a>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
