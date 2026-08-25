<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'reports.view');

$title = 'Reference Monitor';

$type = trim((string) ($_GET['type'] ?? ''));
$key = trim((string) ($_GET['key'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 200);
if ($limit <= 0 || $limit > 2000) $limit = 200;

$counts = $pdo->query("SELECT resource_type, resource_key, ref_count, last_event_at
    FROM resource_refcounts
    ORDER BY last_event_at DESC
    LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

$events = [];
if ($type !== '' && $key !== '') {
    $stmt = $pdo->prepare("SELECT * FROM resource_ref_events WHERE resource_type = ? AND resource_key = ? ORDER BY id DESC LIMIT {$limit}");
    $stmt->execute([$type, $key]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>Reference Monitor</h1>
            <small class="text-muted">Tracks reference count changes and zero-reference cleanup triggers.</small>
        </div>
        <div class="btn-group">
            <a class="btn btn-outline-secondary" href="refcount_monitor_export.php?format=csv">Export Events (CSV)</a>
            <a class="btn btn-outline-secondary" href="refcount_monitor_export.php?format=excel">Export Events (Excel)</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card">
                <div class="card-header">Resources</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Key</th>
                                <th class="text-right">Count</th>
                                <th>Last</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($counts as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['resource_type']); ?></td>
                                    <td><a href="refcount_monitor.php?type=<?php echo urlencode($r['resource_type']); ?>&key=<?php echo urlencode($r['resource_key']); ?>"><?php echo htmlspecialchars($r['resource_key']); ?></a></td>
                                    <td class="text-right"><?php echo (int) $r['ref_count']; ?></td>
                                    <td><?php echo htmlspecialchars((string) $r['last_event_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$counts): ?>
                                <tr><td colspan="4" class="text-muted">No refcount activity yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Events</span>
                    <?php if ($type !== '' && $key !== ''): ?>
                        <small class="text-muted"><?php echo htmlspecialchars($type . ':' . $key); ?></small>
                    <?php endif; ?>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event</th>
                                <th class="text-right">Delta</th>
                                <th class="text-right">New</th>
                                <th>Tag</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $e): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($e['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($e['event_type']); ?></td>
                                    <td class="text-right"><?php echo (int) $e['delta']; ?></td>
                                    <td class="text-right"><?php echo (int) $e['new_count']; ?></td>
                                    <td><?php echo htmlspecialchars((string) $e['ref_tag']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $e['by_user_id']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$events): ?>
                                <tr><td colspan="6" class="text-muted">Select a resource to view event history.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

