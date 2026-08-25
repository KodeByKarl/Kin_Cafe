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

$title = 'Customer Preference Analysis';
$feature = getAiCustomerPreferencesData($pdo);
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <h1 class="page-title">Customer Preference Analysis Feature</h1>
            <p class="page-subtitle">Analyze customer purchase history to identify buying patterns and preferred menu categories.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <a class="btn btn-outline-secondary" href="analytics.php">Analytics</a>
        </div>
    </div>

    <div class="ai-highlight-grid">
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Profiled repeat customers</span><div class="ai-module-kpi-value"><?php echo (int) ($feature['profiled_customer_count'] ?? 0); ?></div><p class="ai-module-kpi-copy">Named customers with at least 2 completed orders in 90 days.</p><span class="forecast-model-label"><?php echo htmlspecialchars((string) ($feature['method_label'] ?? '')); ?></span></article>
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Shared trends</span><div class="ai-module-kpi-value"><?php echo count($feature['customers']); ?></div><p class="ai-module-kpi-copy">Popular item/category combinations across all customers.</p></article>
        <article class="ai-module-kpi"><span class="ai-module-kpi-label">Tracked categories</span><div class="ai-module-kpi-value"><?php echo count($feature['categories']); ?></div><p class="ai-module-kpi-copy">Top-performing menu categories from recent sales.</p></article>
    </div>

    <div class="ai-detail-grid mt-4">
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Profiled Repeat Customers</h2><span class="analytics-badge">Individual profiles</span></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach ($feature['profiles'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['customer_label']); ?> — <?php echo htmlspecialchars((string) $row['favorite_item']); ?> (<?php echo htmlspecialchars((string) $row['repeat_status']); ?>)</span><strong><?php echo (int) $row['order_count']; ?> orders · ₱<?php echo number_format((float) $row['total_spend'], 2); ?></strong></li>
                    <?php endforeach; ?>
                    <?php if (!$feature['profiles']): ?><li><span>No repeat customer profiles yet (need 2+ orders per customer).</span><strong>--</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Shared Favorite Trends</h2><span class="analytics-badge">Purchase patterns</span></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach ($feature['customers'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['favorite_item']); ?> in <?php echo htmlspecialchars((string) $row['favorite_category']); ?> · <?php echo (int) ($row['repeat_buyer_count'] ?? 0); ?> repeat buyers (<?php echo number_format((float) ($row['repeat_rate_percent'] ?? 0), 1); ?>%)</span><strong><?php echo (int) $row['order_count']; ?> orders</strong></li>
                    <?php endforeach; ?>
                    <?php if (!$feature['customers']): ?><li><span>No shared preference data is available.</span><strong>--</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Category Preferences</h2><span class="analytics-badge">Menu planning</span></div>
            <div class="card-body ai-module-body">
                <ul class="ai-module-list">
                    <?php foreach ($feature['categories'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['category_name']); ?></span><strong><?php echo (int) $row['qty']; ?> items sold</strong></li>
                    <?php endforeach; ?>
                    <?php if (!$feature['categories']): ?><li><span>No category preference data is available.</span><strong>--</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
