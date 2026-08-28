<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

requireLogin();

if (!hasPermission('settings.manage') && getUserRole() !== 'supervisor') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$pdo = getDbConnection();
$jsonFile = __DIR__ . '/backups/outbox.json';

$emails = [];
if (is_file($jsonFile)) {
    $raw = @file_get_contents($jsonFile);
    if ($raw) {
        $emails = json_decode($raw, true) ?: [];
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_outbox') {
    if (csrfVerify('clear_outbox')) {
        @file_put_contents($jsonFile, json_encode([], JSON_PRETTY_PRINT));
        $htmlFile = __DIR__ . '/backups/outbox_emails.html';
        @file_put_contents($htmlFile, '<html><body><h1>Outbox Cleared</h1></body></html>');
        $emails = [];
        $message = 'Outbox cleared successfully.';
    }
}

$search = strtolower(trim((string) ($_GET['q'] ?? '')));
if ($search !== '') {
    $emails = array_filter($emails, static function ($m) use ($search) {
        return strpos(strtolower($m['to_email'] ?? ''), $search) !== false
            || strpos(strtolower($m['subject'] ?? ''), $search) !== false
            || strpos(strtolower($m['delivered_via'] ?? ''), $search) !== false;
    });
}

include 'includes/header.php';
?>

<div class="main-content analytics-admin-page">
    <div class="page-hero analytics-hero">
        <div>
            <h1 class="page-title">Free Email Outbox & Delivery Center</h1>
            <p class="page-subtitle">View and inspect all emails delivered by the system in real time.</p>
        </div>
        <div class="analytics-hero-actions">
            <a class="btn btn-outline-secondary" href="user_settings.php?tab=general">SMTP Settings</a>
            <a class="btn btn-outline-primary" href="outbox.php">Refresh Inbox</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h5 class="mb-1">Local Mail Outbox Feed</h5>
                    <p class="text-muted mb-0">Total Emails Logged: <strong><?php echo count($emails); ?></strong></p>
                </div>
                <div class="d-flex gap-2">
                    <form method="get" class="form-inline">
                        <input type="text" name="q" class="form-control mr-2" placeholder="Search email or subject..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-secondary">Filter</button>
                    </form>
                    <?php if ($emails): ?>
                    <form method="post" onsubmit="return confirm('Clear outbox history?');">
                        <input type="hidden" name="action" value="clear_outbox">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken('clear_outbox')); ?>">
                        <button type="submit" class="btn btn-outline-danger">Clear Outbox</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="outbox-feed">
        <?php foreach ($emails as $index => $m): ?>
            <div class="card mb-3 shadow-sm border">
                <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                    <div>
                        <span class="badge badge-info mr-2"><?php echo htmlspecialchars($m['delivered_via'] ?? 'Outbox'); ?></span>
                        <strong>To:</strong> <?php echo htmlspecialchars($m['to_name'] ? $m['to_name'] . ' <' . $m['to_email'] . '>' : $m['to_email']); ?>
                    </div>
                    <span class="text-muted small"><?php echo htmlspecialchars($m['timestamp'] ?? ''); ?></span>
                </div>
                <div class="card-body">
                    <h5 class="card-title text-primary"><?php echo htmlspecialchars($m['subject'] ?? '(No Subject)'); ?></h5>
                    <p class="text-muted small mb-2"><strong>From:</strong> <?php echo htmlspecialchars(($m['from_name'] ?? 'Kin Cafe') . ' <' . ($m['from_email'] ?? 'noreply@kincafe.local') . '>'); ?></p>
                    <hr>
                    <div class="email-preview-box p-3 bg-light rounded border">
                        <?php if (!empty($m['html_body'])): ?>
                            <?php echo $m['html_body']; ?>
                        <?php else: ?>
                            <pre class="mb-0" style="white-space:pre-wrap;"><?php echo htmlspecialchars($m['text_body'] ?? ''); ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$emails): ?>
            <div class="card text-center p-5">
                <h4 class="text-muted">No emails in outbox</h4>
                <p class="text-muted">Emails sent by Kin Cafe (password resets, notifications, test emails) will appear here.</p>
                <div>
                    <a href="user_settings.php?tab=general" class="btn btn-primary">Send Test Email</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
