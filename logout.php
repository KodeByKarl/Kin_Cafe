<?php
session_start();
require 'includes/db.php';

if (function_exists('sendAuthenticatedNoStoreHeaders')) {
    sendAuthenticatedNoStoreHeaders();
}

if (isset($_SESSION['admin'])) {
    logAuditEvent($pdo, 'logout', 'user', (int) $_SESSION['admin'], ['tab' => $_SESSION['kc_tab'] ?? null]);
}

$tabId = authTabId();
$logoutAll = isset($_GET['all']) && $_GET['all'] === '1';

if ($logoutAll) {
    authLogoutAll($pdo);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    header('Location: index.php?logged_out=1');
    exit;
}

authLogoutTab($pdo, $tabId);
unset($_SESSION['admin'], $_SESSION['role']);
header('Location: index.php?logged_out=1&tab=' . urlencode($tabId));
exit;
?>
