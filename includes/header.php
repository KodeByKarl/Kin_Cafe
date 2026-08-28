<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $brandLogoPath = 'assets/images/kin-cafe-logo.jpg';
    $brandLogoVersion = @filemtime(__DIR__ . '/../assets/images/kin-cafe-logo.jpg') ?: time();
    $brandLogoUrl = $brandLogoPath . '?v=' . $brandLogoVersion;
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Kin Cafe Admin'; ?></title>
    <link rel="icon" type="image/jpeg" href="<?php echo htmlspecialchars($brandLogoUrl); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <?php
    $assetCandidates = [
        __DIR__ . '/../assets/css/style.css',
        __DIR__ . '/../assets/css/theme.css',
        __DIR__ . '/../assets/css/ui-modern.css',
        __DIR__ . '/../assets/css/pos.css',
    ];
    $assetVer = 0;
    foreach ($assetCandidates as $assetPath) {
        $mtime = @filemtime($assetPath);
        if ($mtime !== false) {
            $assetVer = max($assetVer, (int) $mtime);
        }
    }
    if ($assetVer <= 0) {
        $assetVer = 1;
    }
    ?>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $assetVer; ?>">
    <link rel="stylesheet" href="assets/css/theme.css?v=<?php echo $assetVer; ?>">
    <link rel="stylesheet" href="assets/css/ui-modern.css?v=<?php echo $assetVer; ?>">
    <link rel="stylesheet" href="assets/css/pos.css?v=<?php echo $assetVer; ?>">
</head>
<body class="kc-page <?php echo htmlspecialchars(pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_FILENAME)); ?>">
    <?php
    // Keep backup off the critical navigation path. A failed/stuck dump used to
    // make every page wait ~tens of seconds after the scheduled backup time.
    if (isset($pdo) && PHP_SAPI !== 'cli') {
        register_shutdown_function(static function () use ($pdo): void {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                checkAndRunScheduledBackup($pdo);
            } catch (Throwable $e) {
                // Ignore backup errors during page teardown.
            }
        });
    }
    $current = basename($_SERVER['SCRIPT_NAME']);
    ?>
    <?php
    $aiPages = [
        'ai_insights.php',
        'ai_sales_forecasting.php',
        'ai_inventory_optimization.php',
        'ai_demand_prediction.php',
        'ai_customer_preferences.php',
        'ai_anomaly_detection.php',
        'ai_virtual_assistant.php',
        'ai_waste_reduction.php',
    ];
    $navIcons = [
        'dashboard.php' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/></svg>',
        'pos.php' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="19" r="1.6" fill="currentColor"/><circle cx="17" cy="19" r="1.6" fill="currentColor"/><path d="M3 4h2.1l2.2 9.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 7H7.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'orders_history.php' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-2.64-6.36" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 4v5h-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'menu_management.php' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 4v7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10 4v7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 11v9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M15 4c0 2.2 1.8 4 4 4v12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'inventory.php' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 8.5 12 4l8 4.5v7L12 20l-8-4.5v-7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4.5 8.5 12 13l7.5-4.5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        'purchase_orders.php' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" stroke="currentColor" stroke-width="1.8"/><rect x="8" y="2" width="8" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/><path d="M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'analytics.php' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M7 16V9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 16V5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M17 16v-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'ai_insights.php' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 4 7v5c0 5 3.4 8 8 9 4.6-1 8-4 8-9V7l-8-4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 11.5 11 13l3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'user_settings.php' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 0 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 0 1-4 0v-.2a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 0 1 0-4h.2a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 0 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 0 1 4 0v.2a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6H20a2 2 0 0 1 0 4h-.2a1 1 0 0 0-.9.6Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 17 5 12l5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 5h3a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    ];
    ?>
    <button class="kc-mobile-nav-toggle" type="button" aria-label="Open navigation" aria-controls="kc-sidebar-nav" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <aside class="sidebar-nav">
        <div class="sidebar-brand">
            <img src="<?php echo htmlspecialchars($brandLogoUrl); ?>" alt="Kin Cafe logo" class="sidebar-brand-logo">
        </div>
        <nav class="sidebar-menu" aria-label="Primary">
            <?php if (isset($pdo) && hasPermission($pdo, 'dashboard.view')): ?><a class="sidebar-item<?php echo $current === 'dashboard.php' ? ' active' : ''; ?>" href="dashboard.php" title="View dashboard stats"><span class="sidebar-icon"><?php echo $navIcons['dashboard.php']; ?></span><span>Dashboard</span></a><?php endif; ?>
            <?php if (isset($pdo) && hasPermission($pdo, 'pos.access')): ?><a class="sidebar-item<?php echo $current === 'pos.php' ? ' active' : ''; ?>" href="pos.php" title="Open point-of-sale interface"><span class="sidebar-icon"><?php echo $navIcons['pos.php']; ?></span><span>POS</span></a><?php endif; ?>
            <?php if (isset($pdo) && hasPermission($pdo, 'orders.view')): ?><a class="sidebar-item<?php echo $current === 'orders_history.php' ? ' active' : ''; ?>" href="orders_history.php" title="View order history"><span class="sidebar-icon"><?php echo $navIcons['orders_history.php']; ?></span><span>Orders</span></a><?php endif; ?>
            <?php if (isset($pdo) && hasPermission($pdo, 'menu.manage')): ?><a class="sidebar-item<?php echo $current === 'menu_management.php' ? ' active' : ''; ?>" href="menu_management.php" title="Manage menu items and categories"><span class="sidebar-icon"><?php echo $navIcons['menu_management.php']; ?></span><span>Menu</span></a><?php endif; ?>
            <?php if (isset($pdo) && hasPermission($pdo, 'inventory.manage')): ?><a class="sidebar-item<?php echo $current === 'inventory.php' ? ' active' : ''; ?>" href="inventory.php" title="Track inventory and stock levels"><span class="sidebar-icon"><?php echo $navIcons['inventory.php']; ?></span><span>Inventory</span></a><?php endif; ?>
            <?php if (isset($pdo) && hasPermission($pdo, 'inventory.manage')): ?><a class="sidebar-item<?php echo $current === 'purchase_orders.php' ? ' active' : ''; ?>" href="purchase_orders.php" title="Manage purchase orders and suppliers"><span class="sidebar-icon"><?php echo $navIcons['purchase_orders.php']; ?></span><span>Purchase Orders</span></a><?php endif; ?>
            <?php if (isset($pdo) && hasPermission($pdo, 'reports.view')): ?><a class="sidebar-item<?php echo $current === 'analytics.php' ? ' active' : ''; ?>" href="analytics.php" title="View analytics reports"><span class="sidebar-icon"><?php echo $navIcons['analytics.php']; ?></span><span>Analytics</span></a><?php endif; ?>
            <?php if (isset($pdo) && hasPermission($pdo, 'reports.view')): ?><a class="sidebar-item<?php echo in_array($current, $aiPages, true) ? ' active' : ''; ?>" href="ai_insights.php" title="Open the AI business suite"><span class="sidebar-icon"><?php echo $navIcons['ai_insights.php']; ?></span><span>AI Suite</span></a><?php endif; ?>
            <?php if (isset($pdo)): ?><a class="sidebar-item<?php echo $current === 'user_settings.php' ? ' active' : ''; ?>" href="user_settings.php" title="Update username, email, and password"><span class="sidebar-icon"><?php echo $navIcons['user_settings.php']; ?></span><span>Settings</span></a><?php endif; ?>
        </nav>
        <div class="sidebar-spacer"></div>
        <a class="sidebar-item logout" href="#" onclick="confirmLogout(event)" title="Sign out"><span class="sidebar-icon"><?php echo $navIcons['logout']; ?></span><span>Sign Out</span></a>
    </aside>
    <button class="kc-sidebar-overlay" type="button" aria-label="Close navigation"></button>
<script>
(() => {
    const key = 'kc_tab_id';
    const params = new URLSearchParams(window.location.search);
    let tab = params.get('tab');
    if (!tab) {
        tab = sessionStorage.getItem(key);
        if (!tab) {
            tab = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Math.random().toString(16).slice(2) + Date.now().toString(16));
            sessionStorage.setItem(key, tab);
        }
        params.set('tab', tab);
        const newUrl = window.location.pathname + '?' + params.toString() + window.location.hash;
        window.location.replace(newUrl);
        return;
    }
    sessionStorage.setItem(key, tab);

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('a[href]').forEach(a => {
            const href = a.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
            if (!href.includes('.php')) return;
            const url = new URL(href, window.location.origin + window.location.pathname.replace(/[^/]+$/, ''));
            if (!url.searchParams.get('tab')) {
                url.searchParams.set('tab', tab);
                a.setAttribute('href', url.pathname + '?' + url.searchParams.toString());
            }
        });

        const body = document.body;
        const toggle = document.querySelector('.kc-mobile-nav-toggle');
        const overlay = document.querySelector('.kc-sidebar-overlay');
        const sidebar = document.querySelector('.sidebar-nav');
        if (!toggle || !overlay || !sidebar) {
            return;
        }

        sidebar.id = sidebar.id || 'kc-sidebar-nav';
        const syncNavState = () => {
            const isOpen = body.classList.contains('kc-nav-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            overlay.toggleAttribute('hidden', !isOpen);
        };

        const closeNav = () => {
            body.classList.remove('kc-nav-open');
            syncNavState();
        };

        toggle.addEventListener('click', () => {
            body.classList.toggle('kc-nav-open');
            syncNavState();
        });

        overlay.addEventListener('click', closeNav);

        sidebar.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    closeNav();
                }
            });
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                closeNav();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeNav();
            }
        });

        syncNavState();
    });
})();

function confirmLogout(event) {
    event.preventDefault();
    const go = () => {
        const tab = new URLSearchParams(window.location.search).get('tab') || 'default';
        window.location.href = 'logout.php?tab=' + encodeURIComponent(tab);
    };
    if (window.KinAlertModal && typeof window.KinAlertModal.confirm === 'function') {
        window.KinAlertModal.confirm('Are you sure you want to sign out?', 'Sign Out', 'Sign Out', 'Cancel').then((ok) => {
            if (ok) go();
        });
        return;
    }
    if (confirm('Are you sure you want to sign out?')) {
        go();
    }
}
</script>
    <div class="kc-shell">
<?php
$kcNotifFeed = ['count' => 0, 'items' => []];
$kcNotifCsrf = '';
$kcUserName = '';
try {
    if (isset($pdo) && $pdo instanceof PDO && function_exists('getStaffNotificationFeed')) {
        $kcNotifFeed = getStaffNotificationFeed($pdo, isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : null, 16);
        $kcNotifCsrf = csrfToken('notifications');
    }
    if (isset($pdo) && isset($_SESSION['admin']) && function_exists('getCurrentUser')) {
        $kcUser = getCurrentUser($pdo);
        $kcUserName = (string) ($kcUser['username'] ?? '');
    }
} catch (Throwable $e) {
    $kcNotifFeed = ['count' => 0, 'items' => []];
}
$kcNotifCount = (int) ($kcNotifFeed['count'] ?? 0);
$kcNotifItems = $kcNotifFeed['items'] ?? [];
?>
        <div class="kc-topbar" role="banner">
            <div class="kc-topbar-spacer">
                <?php if (!empty($pageTitle) || !empty($title)): ?>
                    <h1 class="kc-topbar-title"><?php echo htmlspecialchars($pageTitle ?? $title); ?></h1>
                <?php endif; ?>
            </div>
            <div class="kc-notif" id="kcNotifRoot" data-csrf="<?php echo htmlspecialchars($kcNotifCsrf); ?>">
                <button type="button" class="kc-notif-bell" id="kcNotifToggle" aria-expanded="false" aria-controls="kcNotifPanel" title="Notifications">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5a5 5 0 0 0-5 5v2.1c0 .7-.2 1.4-.6 2L5.2 14.4A1.2 1.2 0 0 0 6.2 16.2h11.6a1.2 1.2 0 0 0 1-1.8l-1.2-1.8c-.4-.6-.6-1.3-.6-2V8.5a5 5 0 0 0-5-5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9.6 17.2a2.5 2.5 0 0 0 4.8 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    <span class="kc-notif-badge<?php echo $kcNotifCount > 0 ? ' is-on' : ''; ?>" id="kcNotifBadge" aria-label="<?php echo $kcNotifCount; ?> notifications"><?php echo $kcNotifCount > 9 ? '9+' : (string) $kcNotifCount; ?></span>
                </button>
                <div class="kc-notif-panel" id="kcNotifPanel" hidden>
                    <div class="kc-notif-panel-head">
                        <strong>Notifications</strong>
                        <span id="kcNotifCountLabel"><?php echo $kcNotifCount; ?> new</span>
                    </div>
                    <div class="kc-notif-list" id="kcNotifList">
                        <?php if (!$kcNotifItems): ?>
                            <div class="kc-notif-empty">You're all caught up.</div>
                        <?php else: ?>
                            <?php foreach ($kcNotifItems as $n): ?>
                                <article class="kc-notif-item is-<?php echo htmlspecialchars((string) ($n['severity'] ?? 'info')); ?>" data-key="<?php echo htmlspecialchars((string) ($n['key'] ?? '')); ?>">
                                    <a class="kc-notif-link" href="<?php echo htmlspecialchars((string) ($n['href'] ?? '#')); ?>">
                                        <span class="kc-notif-kind"><?php echo htmlspecialchars((string) ($n['meta'] ?? 'Alert')); ?></span>
                                        <strong><?php echo htmlspecialchars((string) ($n['title'] ?? 'Alert')); ?></strong>
                                        <p><?php echo nl2br(htmlspecialchars((string) ($n['preview'] ?? ''))); ?></p>
                                        <span class="kc-notif-meta"><?php echo htmlspecialchars((string) ($n['time'] ?? '')); ?></span>
                                    </a>
                                    <?php if (!empty($n['key'])): ?>
                                        <button type="button" class="kc-notif-dismiss" data-dismiss-key="<?php echo htmlspecialchars((string) $n['key']); ?>" aria-label="Dismiss">×</button>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($kcUserName)): ?>
                <div class="kc-topbar-user" title="Logged in as <?php echo htmlspecialchars($kcUserName); ?>">
                    <span class="kc-topbar-user-avatar"><?php echo htmlspecialchars(strtoupper(substr($kcUserName, 0, 1))); ?></span>
                    <span class="kc-topbar-user-name"><?php echo htmlspecialchars($kcUserName); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <script>
        (function () {
            const root = document.getElementById('kcNotifRoot');
            const toggle = document.getElementById('kcNotifToggle');
            const panel = document.getElementById('kcNotifPanel');
            const list = document.getElementById('kcNotifList');
            const badge = document.getElementById('kcNotifBadge');
            const countLabel = document.getElementById('kcNotifCountLabel');
            if (!root || !toggle || !panel) return;
            const csrf = root.getAttribute('data-csrf') || '';

            function currentCount() {
                return list ? list.querySelectorAll('.kc-notif-item').length : 0;
            }

            function syncCount(count) {
                const n = Number(count);
                if (badge) {
                    badge.textContent = n > 9 ? '9+' : String(Math.max(0, n));
                    badge.classList.toggle('is-on', n > 0);
                    badge.setAttribute('aria-label', n + ' notifications');
                }
                if (countLabel) {
                    countLabel.textContent = n + ' new';
                }
                if (list && n <= 0 && !list.querySelector('.kc-notif-empty')) {
                    list.innerHTML = '<div class="kc-notif-empty">You\'re all caught up.</div>';
                }
            }

            function closePanel() {
                panel.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }
            function openPanel() {
                panel.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
            }
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (panel.hidden) openPanel(); else closePanel();
            });
            document.addEventListener('click', function (e) {
                if (!root.contains(e.target)) closePanel();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closePanel();
            });

            root.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-dismiss-key]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const key = btn.getAttribute('data-dismiss-key') || '';
                if (!key) return;
                const body = new URLSearchParams();
                body.set('action', 'dismiss');
                body.set('notification_key', key);
                body.set('csrf_token', csrf);
                fetch('notifications_action.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
                    body: body.toString()
                }).then(function (res) { return res.json(); }).then(function (data) {
                    const item = btn.closest('.kc-notif-item');
                    if (item) item.remove();
                    syncCount(data && typeof data.count === 'number' ? data.count : currentCount());
                }).catch(function () {
                    const item = btn.closest('.kc-notif-item');
                    if (item) item.remove();
                    syncCount(currentCount());
                });
            });
        })();
        </script>

    <!-- Global Floating Top-Right Toast Notification Container -->
    <div id="kcToastContainer" class="kc-toast-container" aria-live="polite" aria-atomic="true"></div>

    <script>
    (function () {
        window.showToast = function (type, title, message, duration) {
            const container = document.getElementById('kcToastContainer');
            if (!container) return;

            duration = duration || 4500;
            const toast = document.createElement('div');
            toast.className = 'kc-toast kc-toast-' + (type || 'info');
            
            let iconSymbol = 'i';
            if (type === 'success') iconSymbol = '✓';
            else if (type === 'error' || type === 'danger') iconSymbol = '✕';
            else if (type === 'warning') iconSymbol = '!';

            const safeTitle = title ? String(title) : '';
            const safeMsg = message ? String(message) : '';

            const titleHtml = safeTitle ? '<div class="kc-toast-title">' + escapeHtml(safeTitle) + '</div>' : '';
            const msgHtml = '<div class="kc-toast-message">' + escapeHtml(safeMsg) + '</div>';

            toast.innerHTML = 
                '<div class="kc-toast-icon-badge">' + iconSymbol + '</div>' +
                '<div class="kc-toast-content">' + titleHtml + msgHtml + '</div>' +
                '<button type="button" class="kc-toast-close" aria-label="Close">&times;</button>' +
                '<div class="kc-toast-progress" style="animation-duration: ' + duration + 'ms;"></div>';

            const closeBtn = toast.querySelector('.kc-toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    toast.classList.add('hide');
                    setTimeout(function () { toast.remove(); }, 300);
                });
            }

            container.appendChild(toast);

            setTimeout(function () {
                if (toast.parentNode) {
                    toast.classList.add('hide');
                    setTimeout(function () { toast.remove(); }, 350);
                }
            }, duration);
        };
    })();
    </script>

