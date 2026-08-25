<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'reports.view');

$format = strtolower(trim((string) ($_GET['format'] ?? 'csv')));
$limit = (int) ($_GET['limit'] ?? 5000);
if ($limit <= 0 || $limit > 50000) $limit = 5000;

$fileBase = 'kin-cafe-refcount-events-' . date('Ymd-His');

$stmt = $pdo->prepare("SELECT resource_type, resource_key, delta, new_count, event_type, ref_tag, by_user_id, meta_json, created_at
    FROM resource_ref_events
    ORDER BY id DESC
    LIMIT {$limit}");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$header = ['resource_type', 'resource_key', 'delta', 'new_count', 'event_type', 'ref_tag', 'by_user_id', 'meta_json', 'created_at'];

if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileBase . '.xls"');
    echo "<table border='1'><thead><tr>";
    foreach ($header as $h) echo "<th>" . htmlspecialchars((string) $h) . "</th>";
    echo "</tr></thead><tbody>";
    foreach ($rows as $r) {
        echo "<tr>";
        foreach ($header as $h) echo "<td>" . htmlspecialchars((string) ($r[$h] ?? '')) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileBase . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, $header);
foreach ($rows as $r) {
    $line = [];
    foreach ($header as $h) $line[] = $r[$h] ?? '';
    fputcsv($out, $line);
}
fclose($out);
exit;

