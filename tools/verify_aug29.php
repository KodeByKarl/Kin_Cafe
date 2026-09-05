<?php
/**
 * Live verification of Aug 29 adviser items + PHP/HTML error scan.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/includes/db.php';
require_once $root . '/includes/functions.php';

$base = 'http://127.0.0.1/Kin_Cafe';
$results = [];
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $results, $fail;
    $results[] = [$ok ? 'PASS' : 'FAIL', $label, $detail];
    if (!$ok) {
        $fail++;
    }
}

function httpRequest(string $url, array $opts = []): array
{
    $cookieFile = $opts['cookie'] ?? '';
    $post = $opts['post'] ?? null;
    $follow = $opts['follow'] ?? true;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'KinCafeVerify/1.0',
    ]);
    if ($cookieFile !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if ($raw === false) {
        return ['code' => 0, 'headers' => '', 'body' => '', 'error' => $err, 'url' => $url];
    }
    return [
        'code' => $code,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
        'error' => '',
        'url' => $effective,
    ];
}

function extractCsrf(string $html): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function phpErrorHit(string $html): string
{
    if (preg_match('/(?:PHP (?:Fatal error|Parse error|Warning|Notice|Deprecated)|Uncaught (?:Error|Exception|PDOException)|<b>(?:Warning|Fatal error|Parse error|Notice)<\/b>)/i', $html, $m)) {
        if (preg_match('/.{0,40}' . preg_quote($m[0], '/') . '.{0,160}/i', $html, $snip)) {
            return trim(preg_replace('/\s+/', ' ', $snip[0]));
        }
        return $m[0];
    }
    return '';
}

function loginAs(string $base, string $username, string $password): array
{
    $cookie = tempnam(sys_get_temp_dir(), 'kc_aug29_');
    $loginPage = httpRequest($base . '/index.php', ['cookie' => $cookie, 'follow' => true]);
    $csrf = extractCsrf($loginPage['body']);
    $post = http_build_query([
        'action' => 'login',
        'username' => $username,
        'password' => $password,
        'csrf_token' => $csrf,
    ]);
    $res = httpRequest($base . '/index.php', ['cookie' => $cookie, 'post' => $post, 'follow' => true]);
    $tab = '';
    $haystack = ($res['url'] ?? '') . "\n" . ($res['headers'] ?? '') . "\n" . ($res['body'] ?? '');
    if (preg_match('/[?&]tab=([0-9a-fA-F-]{8,})/', $haystack, $m)) {
        $tab = $m[1];
    }
    return [$cookie, $res, $tab];
}

echo "Kin Cafe Aug 29 verification\n";
echo "============================\n";

$ping = httpRequest($base . '/index.php');
check('Apache is up', $ping['code'] >= 200 && $ping['code'] < 500, 'HTTP ' . $ping['code'] . ($ping['error'] ? ' ' . $ping['error'] : ''));
if ($ping['code'] === 0) {
    echo "Apache is not reachable.\n";
    exit(1);
}

check('Login page has password eye script', str_contains($ping['body'], 'password-toggle.js'));
check('Login page has no PHP error', phpErrorHit($ping['body']) === '', phpErrorHit($ping['body']));

$backupDir = getBackupDirectory();
check('Backup directory exists', is_dir($backupDir), $backupDir);
$jsonBackups = glob($backupDir . DIRECTORY_SEPARATOR . 'backup-*.json') ?: [];
check('JSON backup files exist on disk', count($jsonBackups) > 0, (string) count($jsonBackups));

[$adminCookie, $adminRes, $adminTab] = loginAs($base, 'admin', 'admin123');
$q = static function (string $path) use ($base, $adminCookie, $adminTab): array {
    $sep = str_contains($path, '?') ? '&' : '?';
    $url = $base . '/' . ltrim($path, '/');
    if ($adminTab !== '') {
        $url .= $sep . 'tab=' . rawurlencode($adminTab);
    }
    return httpRequest($url, ['cookie' => $adminCookie]);
};

$pages = [
    'dashboard.php' => 'Dashboard',
    'pos.php' => 'Point of Sale',
    'inventory.php' => 'Inventory',
    'menu_management.php' => 'Menu',
    'orders_history.php' => 'Order History',
    'user_settings.php' => 'Settings',
    'analytics.php' => 'Data Analytics',
    'ai_recommendation_system.php' => 'Recommendation',
    'process_order.php' => null,
];

foreach ($pages as $path => $expect) {
    $res = $q($path);
    $err = phpErrorHit($res['body']);
    $okCode = $res['code'] === 200 || ($path === 'process_order.php' && in_array($res['code'], [200, 405, 400], true));
    check($path . ' HTTP', $okCode, 'HTTP ' . $res['code'] . ' bytes=' . strlen($res['body']));
    if ($expect !== null) {
        check($path . ' has no PHP error', $err === '', $err);
        check($path . ' renders', str_contains($res['body'], $expect) || str_contains($res['body'], 'kc-shell'), substr(preg_replace('/\s+/', ' ', $res['body']), 0, 120));
    }
}

$pos = $q('pos.php');
check('POS hide-cart button', str_contains($pos['body'], 'id="posCartToggle"'));
check('POS add-ons are collapsible details', str_contains($pos['body'], '<details class="pos-recommendations-panel"'));
check('POS View all is in-page button', str_contains($pos['body'], 'id="posRecommendationsViewAll"'));
check('POS add-ons overlay uses page back', str_contains($pos['body'], 'id="posRecommendationsOverlay"') && str_contains($pos['body'], 'id="posRecommendationsBack"'));
check('POS discount row exists', str_contains($pos['body'], 'id="cart-discount-row"'));
check('POS store discount checkbox exists', str_contains($pos['body'], 'id="isStoreDiscount"') && str_contains($pos['body'], 'Store Discount (10% Off)'));
check('POS PWD discount checkbox exists', str_contains($pos['body'], 'id="isPwdSenior"'));
check('POS save button present', str_contains($pos['body'], 'pos-complete-btn'));
check('POS live-search/password scripts loaded', str_contains($pos['body'], 'live-search.js') && str_contains($pos['body'], 'password-toggle.js') && str_contains($pos['body'], 'alert-modal.js'));

$inv = $q('inventory.php');
check('Inventory stock table is in HTML', str_contains($inv['body'], 'id="inventoryTable"'));
check('Inventory stock panel is not hidden', str_contains($inv['body'], 'data-inventory-panel="stock"') && !preg_match('/data-inventory-panel="stock"[^>]*\bhidden\b/', $inv['body']));
$invPanel = $q('inventory.php?panel=reordering');
check('Inventory reordering panel keeps session', $invPanel['code'] === 200 && str_contains($invPanel['body'], 'id="inventoryTable"') && phpErrorHit($invPanel['body']) === '' && !str_contains($invPanel['body'], 'Access denied'), phpErrorHit($invPanel['body']) ?: ('HTTP ' . $invPanel['code']));
check('Inventory expiry blink classes exist', str_contains($inv['body'], 'is-expiring-stock') || str_contains($inv['body'], 'is-expired-stock') || str_contains($inv['body'], 'is-low-stock'));
check('Inventory live search marker', str_contains($inv['body'], 'data-live-search-target'));
check('Inventory back button', str_contains($inv['body'], 'kc-page-back') && str_contains($inv['body'], 'inventoryTabBack'));

$analytics = $q('analytics.php');
check('Analytics back button', str_contains($analytics['body'], 'kc-page-back') && str_contains($analytics['body'], 'analyticsTabBack'));

$menu = $q('menu_management.php');
check('Menu history is a dropdown', str_contains($menu['body'], 'kc-history-disclosure') && str_contains($menu['body'], '<details'));
check('Menu live search marker', str_contains($menu['body'], 'data-live-search-target'));

$orders = $q('orders_history.php');
check('Orders default page size is 20', str_contains($orders['body'], 'name="limit"') && (str_contains($orders['body'], 'value="20"') || preg_match('/limit=20/', $orders['url'] . $orders['body'])));
check('Orders timestamps use AM/PM', (bool) preg_match('/\b(AM|PM)\b/', $orders['body']));

$settings = $q('user_settings.php');
check('Settings activity includes completed orders copy', str_contains($settings['body'], 'completed orders'));
check('Settings back button', str_contains($settings['body'], 'kc-page-back'));

$notif = $q('dashboard.php');
check('Notification items carry details', str_contains($notif['body'], 'data-details=') || str_contains($notif['body'], 'You\'re all caught up') || str_contains($notif['body'], 'kc-notif-empty'));

$rec = $q('ai_recommendation_system.php');
check('Recommendation module opens for admin', $rec['code'] === 200 && !str_contains($rec['body'], 'Access denied'), 'HTTP ' . $rec['code']);

$js = (string) file_get_contents($root . '/assets/js/script.js');
check('formatCurrency uses thousand separators', str_contains($js, "toLocaleString('en-US'") && str_contains($js, 'minimumFractionDigits'));
check('Cart remove uses confirm modal', str_contains($js, 'Remove this item from the cart?'));
check('Insufficient payment alert includes amounts', str_contains($js, 'Insufficient payment. Total is'));
check('Save button uses is-ready class', str_contains($js, "classList.toggle('is-ready'"));

$css = (string) file_get_contents($root . '/assets/css/ui-modern.css');
check('Expiry blink keyframes exist', str_contains($css, '@keyframes kc-expiry-blink') && str_contains($css, '@keyframes kc-expired-blink'));

$feed = getStaffNotificationFeed($pdo, 1, 8);
check('Notification feed returns array', isset($feed['items']) && is_array($feed['items']));
if (!empty($feed['items'][0]['time'])) {
    check('Notification times use AM/PM', (bool) preg_match('/\b(AM|PM)\b/', (string) $feed['items'][0]['time']), (string) $feed['items'][0]['time']);
}

[$cashierCookie, $cashierRes, $cashierTab] = loginAs($base, 'cashier', 'cashier123');
$cq = static function (string $path) use ($base, $cashierCookie, $cashierTab): array {
    $sep = str_contains($path, '?') ? '&' : '?';
    $url = $base . '/' . ltrim($path, '/');
    if ($cashierTab !== '') {
        $url .= $sep . 'tab=' . rawurlencode($cashierTab);
    }
    return httpRequest($url, ['cookie' => $cashierCookie]);
};
$cpos = $cq('pos.php');
check('Cashier POS loads', $cpos['code'] === 200 && str_contains($cpos['body'], 'posCartToggle'), 'HTTP ' . $cpos['code'] . ' err=' . phpErrorHit($cpos['body']));
$crec = $cq('ai_recommendation_system.php');
check('Cashier can open recommendation View-all page', $crec['code'] === 200 && !str_contains($crec['body'], 'Access denied'), 'HTTP ' . $crec['code'] . ' ' . substr(preg_replace('/\s+/', ' ', $crec['body']), 0, 100));
$cinv = $cq('inventory.php');
check('Cashier still blocked from inventory', $cinv['code'] === 403 || str_contains($cinv['body'], 'Access denied'), 'HTTP ' . $cinv['code']);

echo "\n";
foreach ($results as [$status, $label, $detail]) {
    echo '[' . $status . '] ' . $label;
    if ($detail !== '') {
        echo ' — ' . $detail;
    }
    echo "\n";
}
echo "\n" . (count($results) - $fail) . '/' . count($results) . " checks passed";
if ($fail) {
    echo ", {$fail} failed";
}
echo ".\n";
exit($fail > 0 ? 1 : 0);
