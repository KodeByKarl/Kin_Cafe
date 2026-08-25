<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'reports.view');

$title = 'System Activity';

$mode = $_GET['mode'] ?? 'audit';
$export = ($_GET['export'] ?? '') === 'csv';

$today = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime('-7 days'));
$startDate = $_GET['start'] ?? $defaultStart;
$endDate = $_GET['end'] ?? $today;
$action = trim((string) ($_GET['action'] ?? ''));
$userId = (int) ($_GET['user_id'] ?? 0);
$limit = (int) ($_GET['limit'] ?? 200);
if ($limit <= 0 || $limit > 2000) {
    $limit = 200;
}

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kin-cafe-' . $mode . '-report-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($mode === 'sessions') {
        fputcsv($out, ['tab_id', 'username', 'role', 'ip_address', 'last_seen_at', 'expires_at', 'created_at']);
        $stmt = $pdo->query("SELECT s.tab_id, u.username, s.user_role, s.ip_address, s.last_seen_at, s.expires_at, s.created_at
            FROM auth_tab_sessions s
            JOIN users u ON u.id = s.user_id
            ORDER BY s.last_seen_at DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $row);
        }
        exit;
    }

    fputcsv($out, ['created_at', 'username', 'action', 'entity_type', 'entity_id', 'details_json']);
    $where = [];
    $params = [];

    if ($startDate !== '') {
        $where[] = 'DATE(a.created_at) >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $where[] = 'DATE(a.created_at) <= ?';
        $params[] = $endDate;
    }
    if ($action !== '') {
        $where[] = 'a.action = ?';
        $params[] = $action;
    }
    if ($userId > 0) {
        $where[] = 'a.user_id = ?';
        $params[] = $userId;
    }

    $sql = "SELECT a.created_at, COALESCE(u.username, 'System') AS username, a.action, a.entity_type, a.entity_id, a.details_json
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY a.created_at DESC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $row);
    }
    exit;
}

$users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
$distinctActions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

$auditRows = [];
$sessionRows = [];

if ($mode === 'sessions') {
    $sessionRows = $pdo->query("SELECT s.tab_id, u.username, s.user_role, s.ip_address, s.last_seen_at, s.expires_at, s.created_at
        FROM auth_tab_sessions s
        JOIN users u ON u.id = s.user_id
        ORDER BY s.last_seen_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $where = [];
    $params = [];

    if ($startDate !== '') {
        $where[] = 'DATE(a.created_at) >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $where[] = 'DATE(a.created_at) <= ?';
        $params[] = $endDate;
    }
    if ($action !== '') {
        $where[] = 'a.action = ?';
        $params[] = $action;
    }
    if ($userId > 0) {
        $where[] = 'a.user_id = ?';
        $params[] = $userId;
    }

    $sql = "SELECT a.created_at, COALESCE(u.username, 'System') AS username, a.action, a.entity_type, a.entity_id, a.details_json
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY a.created_at DESC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $auditRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>System Activity</h1>
            <small class="text-muted">Audit trail and active sessions (exportable to Excel).</small>
        </div>
        <div class="btn-group">
            <a class="btn btn-sm <?php echo $mode === 'audit' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="activity_report.php?mode=audit">Audit Logs</a>
            <a class="btn btn-sm <?php echo $mode === 'sessions' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="activity_report.php?mode=sessions">Active Sessions</a>
        </div>
    </div>

    <?php if ($mode === 'sessions'): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Active Sessions</span>
                <a class="btn btn-sm btn-outline-secondary" href="activity_report.php?mode=sessions&export=csv">Export CSV</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tab</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>IP</th>
                            <th>Last Seen</th>
                            <th>Expires</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessionRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['tab_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_role']); ?></td>
                                <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                <td><?php echo htmlspecialchars($row['last_seen_at']); ?></td>
                                <td><?php echo htmlspecialchars($row['expires_at']); ?></td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$sessionRows): ?>
                            <tr><td colspan="7" class="text-muted">No active sessions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card mb-3">
            <div class="card-header">Filters</div>
            <div class="card-body">
                <form method="get">
                    <input type="hidden" name="mode" value="audit">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label>Start Date</label>
                            <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label>End Date</label>
                            <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Action</label>
                            <select name="action" class="form-control">
                                <option value="">All</option>
                                <?php foreach ($distinctActions as $act): ?>
                                    <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $action === $act ? 'selected' : ''; ?>><?php echo htmlspecialchars($act); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>User</label>
                            <select name="user_id" class="form-control">
                                <option value="0">All</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo (int) $u['id']; ?>" <?php echo $userId === (int) $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label>Limit</label>
                            <input type="number" class="form-control" name="limit" min="50" max="2000" value="<?php echo (int) $limit; ?>">
                        </div>
                        <div class="form-group col-md-9 d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a class="btn btn-outline-secondary" href="activity_report.php?mode=audit&export=csv&start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>&action=<?php echo urlencode($action); ?>&user_id=<?php echo (int) $userId; ?>&limit=<?php echo (int) $limit; ?>">Export CSV</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Audit Logs</div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Entity ID</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['action']); ?></td>
                                <td><?php echo htmlspecialchars($row['entity_type']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['entity_id']); ?></td>
                                <td style="max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars((string) $row['details_json']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$auditRows): ?>
                            <tr><td colspan="6" class="text-muted">No audit log entries found for the selected filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
