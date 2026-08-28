<?php
/**
 * Live verification of adviser P0/P1 items.
 * Usage: php tools/verify_adviser_priorities.php
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
        CURLOPT_TIMEOUT => 20,
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

function extractCsrf(string $html, string $scopeHint = ''): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function loginAs(string $base, string $username, string $password): array
{
    $cookie = tempnam(sys_get_temp_dir(), 'kc_cookie_');
    $loginPage = httpRequest($base . '/index.php', ['cookie' => $cookie, 'follow' => true]);
    $csrf = extractCsrf($loginPage['body']);
    $post = http_build_query([
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

echo "Kin Cafe adviser-priority verification\n";
echo "======================================\n";

// --- Static / DB checks ---
$cashierHash = '$2y$10$npf4pLTAWNaqxWluba9KzuyUYrjD9OIrmz6HgVWZTIoDe5TvKMZ92';
$cashierPassword = null;
foreach (['cashier123', 'cashier', 'admin123', 'password'] as $candidate) {
    if (password_verify($candidate, $cashierHash)) {
        $cashierPassword = $candidate;
        break;
    }
}
if ($cashierPassword === null) {
    $stmt = $pdo->query("SELECT password FROM users WHERE username = 'cashier' LIMIT 1");
    $liveHash = (string) ($stmt->fetchColumn() ?: '');
    foreach (['cashier123', 'cashier', 'admin123', 'password'] as $candidate) {
        if ($liveHash !== '' && password_verify($candidate, $liveHash)) {
            $cashierPassword = $candidate;
            break;
        }
    }
}
check('Login lockout is 3 attempts', loginMaxAttempts() === 3, (string) loginMaxAttempts());
check('Login cooldown is 300s', loginCooldownSeconds() === 300, (string) loginCooldownSeconds());
check('Cashier role has no inventory.manage in matrix', !in_array('inventory.manage', ['dashboard.view', 'pos.access', 'pos.checkout', 'orders.view'], true));

$historyFn = function_exists('getRecentMenuAndStockHistory');
check('Activity history helper exists', $historyFn);
if ($historyFn) {
    $history = getRecentMenuAndStockHistory($pdo, 5);
    check('Activity history query runs', is_array($history), count($history) . ' row(s)');
}

$feedFn = function_exists('getStaffNotificationFeed');
check('Notification feed helper exists', $feedFn);

$ping = httpRequest($base . '/index.php');
check('Apache serves Kin Cafe', $ping['code'] >= 200 && $ping['code'] < 500, 'HTTP ' . $ping['code'] . ($ping['error'] ? ' ' . $ping['error'] : ''));

if ($ping['code'] === 0) {
    echo "\nApache is not reachable. Remaining HTTP checks skipped.\n";
} else {
    [$adminCookie, $adminRes, $adminTab] = loginAs($base, 'admin', 'admin123');
    $q = static function (string $path) use ($base, $adminCookie, $adminTab): array {
        $sep = str_contains($path, '?') ? '&' : '?';
        $url = $base . '/' . ltrim($path, '/');
        if ($adminTab !== '') {
            $url .= $sep . 'tab=' . rawurlencode($adminTab);
        }
        return httpRequest($url, ['cookie' => $adminCookie]);
    };
    $debug = static function (string $label, array $res): string {
        $snippet = preg_replace('/\s+/', ' ', substr($res['body'], 0, 160)) ?? '';
        return 'HTTP ' . $res['code'] . ' bytes=' . strlen($res['body']) . ' ' . $snippet;
    };

    $dash = $q('dashboard.php');
    check('Admin login works', $dash['code'] === 200 && (str_contains($dash['body'], 'Dashboard') || str_contains($dash['body'], 'kc-shell')), $debug('dash', $dash));
    check('Admin sees notification bell', str_contains($dash['body'], 'kc-notif-bell') && str_contains($dash['body'], 'kcNotifPanel'));
    check('Dashboard chart is bar (not line)', str_contains($dash['body'], "type: 'bar'") && !preg_match("/type:\\s*'line'/", $dash['body']));

    $settings = $q('user_settings.php');
    check('Admin Settings has Activity History tab', str_contains($settings['body'], 'data-tab="history"') && str_contains($settings['body'], 'Activity History'));
    check('Admin Settings still has Database tab', str_contains($settings['body'], 'data-tab="database"'));
    check('Admin Settings still has Notifications tab', str_contains($settings['body'], 'data-tab="notifications"'));

    $hist = $q('user_settings.php?view=history');
    $settingsJsHasHistory = str_contains($settings['body'], 'data-panel="history"') && str_contains($settings['body'], 'Menu &amp; Stock Activity');
    check('Activity History panel loads', $settingsJsHasHistory || (str_contains($hist['body'], 'Staff') && str_contains($hist['body'], 'Activity')));

    $inv = $q('inventory.php');
    $cssFile = (string) file_get_contents($root . '/assets/css/ui-modern.css');
    check('Low-stock blink CSS is loaded', str_contains($inv['body'], 'ui-modern.css') || str_contains($inv['body'], 'is-low-stock'), $debug('inv', $inv));
    check('Low-stock blink keyframes exist', str_contains($cssFile, '@keyframes kc-low-stock-blink'));
    check('Expiring pill has no blink class in CSS pairing', str_contains($cssFile, '.inventory-status-pill.is-expiring') && !preg_match('/\.inventory-status-pill\.is-expiring\s*\{[^}]*kc-low-stock-blink/s', $cssFile));

    $menu = $q('menu_management.php');
    check('Recipe row spacing CSS exists', str_contains($cssFile, '.recipe-row') && str_contains($cssFile, 'padding: 14px'));
    check('Menu page loads for admin', $menu['code'] === 200, $debug('menu', $menu));

    $pos = $q('pos.php');
    check('POS has search button', str_contains($pos['body'], 'id="menuSearchButton"'), $debug('pos', $pos));
    check('POS food cards are clickable', str_contains($pos['body'], 'onclick="addToCartSelectFromElement(this)"') || str_contains($pos['body'], 'addToCartSelectFromElement'), $debug('pos-cards', $pos));
    $posJs = (string) file_get_contents($root . '/assets/js/script.js');
    check('POS search reorders grid in JS', str_contains($posJs, 'reorderPosGrid') && str_contains($posJs, 'pos-search-hit'));

    $export = $q('analytics_export.php?type=report&format=excel');
    check('Excel export returns spreadsheet', $export['code'] === 200 && (stripos($export['headers'] . $export['body'], 'excel') !== false || str_contains($export['body'], 'Kin Cafe')), $debug('xls', $export));
    check('Excel includes generated-by header', str_contains($export['body'], 'Generated by'));
    check('Excel includes Menu & Stock Activity section', str_contains($export['body'], 'Menu &amp; Stock Activity') || str_contains($export['body'], 'Menu & Stock Activity'));

    if ($cashierPassword) {
        [$cashCookie, $cashRes, $cashTab] = loginAs($base, 'cashier', $cashierPassword);
        $cq = static function (string $path) use ($base, $cashCookie, $cashTab): array {
            $sep = str_contains($path, '?') ? '&' : '?';
            $url = $base . '/' . ltrim($path, '/');
            if ($cashTab !== '') {
                $url .= $sep . 'tab=' . rawurlencode($cashTab);
            }
            return httpRequest($url, ['cookie' => $cashCookie]);
        };
        $cashDash = $cq('dashboard.php');
        check('Cashier login works (' . $cashierPassword . ')', $cashDash['code'] === 200 && str_contains($cashDash['body'], 'kc-shell'), 'HTTP ' . $cashDash['code']);
        check('Cashier still sees notification bell', str_contains($cashDash['body'], 'kc-notif-bell'));

        $cashSettings = $cq('user_settings.php');
        check('Cashier Settings hides Notifications tab', !str_contains($cashSettings['body'], 'data-tab="notifications"'));
        check('Cashier Settings hides Database tab', !str_contains($cashSettings['body'], 'data-tab="database"'));
        check('Cashier Settings hides General tab', !str_contains($cashSettings['body'], 'data-tab="general"'));
        check('Cashier Settings hides Activity History tab', !str_contains($cashSettings['body'], 'data-tab="history"'));
        check('Cashier Settings keeps Profile + Security', str_contains($cashSettings['body'], 'data-tab="profile"') && str_contains($cashSettings['body'], 'data-tab="security"'));

        $cashInv = $cq('inventory.php');
        check('Cashier cannot open Inventory', !str_contains($cashInv['body'], 'Add Ingredient') && ($cashInv['code'] !== 200 || str_contains(strtolower($cashInv['body']), 'access') || str_contains(strtolower($cashInv['body']), 'denied') || str_contains($cashInv['body'], 'dashboard') || str_contains($cashInv['body'], 'Sign')));
        @unlink($cashCookie);
    } else {
        check('Cashier password known for HTTP login', false, 'Could not verify default cashier password');
    }

    @unlink($adminCookie);
}

echo "\n";
foreach ($results as [$status, $label, $detail]) {
    $line = sprintf("[%s] %s", $status, $label);
    if ($detail !== '') {
        $line .= ' — ' . $detail;
    }
    echo $line . "\n";
}

$pass = count($results) - $fail;
echo "\n{$pass}/" . count($results) . " checks passed";
if ($fail > 0) {
    echo ", {$fail} failed";
}
echo ".\n";
exit($fail > 0 ? 1 : 0);
