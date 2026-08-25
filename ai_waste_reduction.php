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

$title = 'Waste Reduction';
$feature = getAiWasteReductionData($pdo);
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <h1 class="page-title">Waste Reduction and Inventory Efficiency Feature</h1>
            <p class="page-subtitle">Analyze expiring stock, slow movers, and waste-related activity to reduce spoilage and improve efficiency.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <a class="btn btn-outline-secondary" href="inventory.php">Inventory</a>
        </div>
    </div>

    <div class="ai-detail-grid">
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Expiring and Slow Moving</h2><span class="analytics-badge">Waste risks</span></div>
            <div class="card-body ai-module-body">
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($feature['method_label'] ?? '')); ?></span>
                <ul class="ai-module-list">
                    <?php foreach ($feature['expiring_ingredients'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['name']); ?> expires soon</span><strong><?php echo htmlspecialchars((string) $row['expiration_date']); ?></strong></li>
                    <?php endforeach; ?>
                    <?php foreach ($feature['slow_moving_items'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['name']); ?> is moving slowly</span><strong><?php echo (int) $row['total_qty']; ?> sold</strong></li>
                    <?php endforeach; ?>
                    <?php if (!$feature['expiring_ingredients'] && !$feature['slow_moving_items']): ?><li><span>No immediate waste risks were found.</span><strong>OK</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Waste Activity Signals</h2><span class="analytics-badge">Removal logs</span></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach ($feature['waste_alerts'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['name']); ?>: <?php echo htmlspecialchars((string) $row['reason']); ?></span><strong><?php echo rtrim(rtrim(number_format((float) $row['quantity'], 2), '0'), '.'); ?></strong></li>
                    <?php endforeach; ?>
                    <?php if (!$feature['waste_alerts']): ?><li><span>No waste-related removal logs were found.</span><strong>OK</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>