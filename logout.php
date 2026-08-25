<?php
session_start();
require 'includes/db.php';

if (isset($_SESSION['admin'])) {
    logAuditEvent($pdo, 'logout', 'user', (int) $_SESSION['admin'], ['tab' => $_SESSION['kc_tab'] ?? null]);
}

$tabId = authTabId();
$logoutAll = isset($_GET['all']) && $_GET['all'] === '1';

if ($logoutAll) {
    authLogoutAll($pdo);
    session_destroy();
    header('Location: index.php');
    exit;
}

authLogoutTab($pdo, $tabId);
unset($_SESSION['admin'], $_SESSION['role']);
header('Location: index.php?tab=' . urlencode($tabId));
exit;
?>
