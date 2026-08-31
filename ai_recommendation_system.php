<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ai_modules.php';

requirePermission($pdo, hasPermission($pdo, 'pos.access') ? 'pos.access' : 'reports.view');

$title = 'Recommendation System';
$feature = getAiRecommendationSystemData($pdo);
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <?php renderPageBackButton('pos.php', 'Back to POS'); ?>
            <h1 class="page-title">Recommendation System Feature</h1>
            <p class="page-subtitle">Suggest menu pairings from completed-order co-occurrence patterns to support upselling at the POS.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <a class="btn btn-outline-secondary" href="pos.php">Open POS</a>
        </div>
    </div>

    <div class="ai-highlight-grid">
        <article class="ai-module-kpi">
            <span class="ai-module-kpi-label">Pairings tracked</span>
            <div class="ai-module-kpi-value"><?php echo count($feature['pairings']); ?></div>
            <p class="ai-module-kpi-copy">Popular item combinations from the last 60 days.</p>
            <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($feature['method_label'] ?? '')); ?></span>
        </article>
        <article class="ai-module-kpi">
            <span class="ai-module-kpi-label">Customer-context rules</span>
            <div class="ai-module-kpi-value"><?php echo count($feature['customer_recommendations']); ?></div>
            <p class="ai-module-kpi-copy">Anchor-item rules used for preference-based upsell copy.</p>
        </article>
        <article class="ai-module-kpi">
            <span class="ai-module-kpi-label">POS integration</span>
            <div class="ai-module-kpi-value">Live</div>
            <p class="ai-module-kpi-copy">Recommended add-ons appear in the POS order sidebar while ordering.</p>
        </article>
    </div>

    <div class="ai-detail-grid mt-4">
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Top Item Pairings</h2><span class="analytics-badge">Co-occurrence</span></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach ($feature['pairings'] as $pair): ?>
                        <li>
                            <span><?php echo htmlspecialchars((string) $pair['item_a']); ?> + <?php echo htmlspecialchars((string) $pair['item_b']); ?></span>
                            <strong><?php echo (int) $pair['pair_count']; ?> orders</strong>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$feature['pairings']): ?><li><span>No pairing history yet.</span><strong>--</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Context Recommendations</h2><span class="analytics-badge">Customer patterns</span></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach ($feature['customer_recommendations'] as $row): ?>
                        <li>
                            <span><?php echo htmlspecialchars((string) $row['customer_label']); ?> → <?php echo htmlspecialchars((string) $row['recommended_item']); ?></span>
                            <strong><?php echo (int) $row['pair_count']; ?> pairs</strong>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$feature['customer_recommendations']): ?><li><span>No customer-context rules yet.</span><strong>--</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
