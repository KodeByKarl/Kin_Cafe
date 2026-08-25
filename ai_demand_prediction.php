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

$title = 'Demand Prediction';
$feature = getAiDemandPredictionData($pdo);
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <h1 class="page-title">Demand Prediction Feature</h1>
            <p class="page-subtitle">Predict demand for specific menu items using historical data, time patterns, and recent customer behavior.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <a class="btn btn-outline-secondary" href="menu_management.php">Menu</a>
        </div>
    </div>

    <div class="ai-highlight-grid">
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Predicted items</span><div class="ai-module-kpi-value"><?php echo count($feature['items']); ?></div><p class="ai-module-kpi-copy">Menu items currently receiving a demand score.</p></article>
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Peak day</span><div class="ai-module-kpi-value"><?php echo htmlspecialchars((string) $feature['peak_day_label']); ?></div><p class="ai-module-kpi-copy">Best day to prepare for higher demand.</p></article>
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Peak hour</span><div class="ai-module-kpi-value"><?php echo htmlspecialchars((string) $feature['peak_hour_label']); ?></div><p class="ai-module-kpi-copy">Best hour window for prep and staffing.</p></article>
    </div>

    <section class="card ai-module-card mt-4">
        <div class="dashboard-card-head"><h2>Predicted Menu Demand</h2><span class="analytics-badge">Menu prep</span></div>
        <div class="card-body ai-module-body">
            <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($feature['method_label'] ?? '')); ?></span>
            <ul class="ai-module-list">
                <?php foreach ($feature['items'] as $row): ?>
                    <li><span><?php echo htmlspecialchars((string) $row['name']); ?>: <?php echo htmlspecialchars((string) $row['demand_strength']); ?> demand<?php if (!empty($row['method_used'])): ?> (<?php echo htmlspecialchars((string) $row['method_used']); ?>)<?php endif; ?></span><strong><?php echo number_format((float) $row['predicted_week_units'], 1); ?> units next 7 days</strong></li>
                <?php endforeach; ?>
                <?php if (!$feature['items']): ?><li><span>No menu demand history is available.</span><strong>--</strong></li><?php endif; ?>
            </ul>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>