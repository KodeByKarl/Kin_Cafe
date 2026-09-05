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
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <?php renderPageBackButton('analytics.php', 'Back to Analytics'); ?>
            <h1 class="page-title">Sales and Inventory Anomaly Detection Feature</h1>
            <p class="page-subtitle">Detect irregular sales patterns and inventory movements that may indicate operational issues.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <a class="btn btn-outline-secondary" href="analytics.php">Analytics</a>
        </div>
    </div>

    <div class="ai-detail-grid">
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Sales Anomalies</h2><span class="analytics-badge">Revenue pattern</span></div>
            <div class="card-body ai-module-body">
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($feature['method_label'] ?? '')); ?></span>
                <ul class="ai-module-list">
                    <?php foreach ($feature['sales'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['sale_date']); ?>: <?php echo htmlspecialchars((string) $row['type']); ?> (<?php echo htmlspecialchars((string) ($row['detection_method'] ?? 'Z-score')); ?>, z=<?php echo htmlspecialchars((string) ($row['z_score'] ?? 'n/a')); ?>)</span><strong>₱<?php echo number_format((float) $row['gap_amount'], 2); ?> variance</strong></li>
                    <?php endforeach; ?>
                    <?php if (!$feature['sales']): ?><li><span>No sales anomalies detected.</span><strong>OK</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Inventory Anomalies</h2><span class="analytics-badge">Stock review</span></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach ($feature['inventory'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['name']); ?>: <?php echo htmlspecialchars((string) $row['reason']); ?></span><strong><?php echo rtrim(rtrim(number_format((float) $row['quantity'], 2), '0'), '.'); ?></strong></li>
                    <?php endforeach; ?>
                    <?php if (!$feature['inventory']): ?><li><span>No inventory anomalies detected.</span><strong>OK</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>