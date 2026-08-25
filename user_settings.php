<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';

$title = 'User Settings';
$errors = [];
$success = '';
$activeSettingsTab = isset($_GET['tab']) && in_array($_GET['tab'], ['profile', 'security', 'users', 'notifications', 'general', 'database'], true) ? $_GET['tab'] : 'profile';

$userId = (int) $_SESSION['admin'];
requirePermission($pdo, 'dashboard.view');
$currentUserRole = getCurrentUserRole($pdo);

$notificationPreferenceDefaults = [
    'pending_orders' => '1',
    'low_stock' => '1',
    'expiring_ingredients' => '1',
    'unavailable_items' => '1',
    'backup_health' => '1',
];

$notificationPreferenceLabels = [
    'pending_orders' => [
        'title' => 'Pending Orders',
        'description' => 'Show alerts when orders are waiting for staff completion.',
    ],
    'low_stock' => [
        'title' => 'Low Stock Ingredients',
        'description' => 'Warn when ingredient stock drops below the operating threshold.',
    ],
    'expiring_ingredients' => [
        'title' => 'Expiring Ingredients',
        'description' => 'Highlight ingredients approaching expiration.',
    ],
    'unavailable_items' => [
        'title' => 'Unavailable Menu Items',
        'description' => 'Notify when menu items cannot be ordered due to stock, expiry, or manual disable.',
    ],
    'backup_health' => [
        'title' => 'Backup Health',
        'description' => 'Alert when backups are failing or have not completed recently.',
    ],
];

$notificationPreferences = [];
foreach ($notificationPreferenceDefaults as $prefKey => $defaultValue) {
    $notificationPreferences[$prefKey] = getUserPreference($pdo, $userId, 'notifications.' . $prefKey, $defaultValue) === '1';
}

$assistantSettings = [
    'enabled' => getSetting($pdo, 'ai_assistant_enabled', '1') === '1',
    'provider' => (string) getSetting($pdo, 'ai_assistant_provider', 'openai-compatible'),
    'endpoint' => (string) getSetting($pdo, 'ai_assistant_endpoint', 'https://api.openai.com/v1/chat/completions'),
    'model' => (string) getSetting($pdo, 'ai_assistant_model', 'gpt-4o-mini'),
    'timeout_seconds' => (string) getSetting($pdo, 'ai_assistant_timeout_seconds', '20'),
    'system_prompt' => (string) getSetting($pdo, 'ai_assistant_system_prompt', 'You are the Kin Cafe virtual assistant. Answer only with information grounded in the provided business context. If the answer is not supported by the context, say that the system does not currently have enough verified data.'),
];
$assistantApiKeyStored = trim((string) getSetting($pdo, 'ai_assistant_api_key', '')) !== '';

$mailSettings = [
    'enabled' => getSetting($pdo, 'mail_enabled', '1') === '1',
    'host' => (string) getSetting($pdo, 'mail_smtp_host', ''),
    'port' => (string) getSetting($pdo, 'mail_smtp_port', '587'),
    'username' => (string) getSetting($pdo, 'mail_smtp_username', ''),
    'auth_enabled' => getSetting($pdo, 'mail_smtp_auth_enabled', '1') === '1',
    'encryption' => (string) getSetting($pdo, 'mail_smtp_encryption', 'tls'),
    'from_email' => (string) getSetting($pdo, 'mail_from_email', ''),
    'from_name' => (string) getSetting($pdo, 'mail_from_name', 'Kin Cafe'),
    'reply_to_email' => (string) getSetting($pdo, 'mail_reply_to_email', ''),
    'reply_to_name' => (string) getSetting($pdo, 'mail_reply_to_name', 'Kin Cafe'),
    'timeout_seconds' => (string) getSetting($pdo, 'mail_smtp_timeout_seconds', '20'),
];
$mailPasswordStored = trim((string) getSetting($pdo, 'mail_smtp_password', '')) !== '';
$mailLibraryAvailable = projectMailerAvailable();

$userStmt = $pdo->prepare("SELECT id, username, email, created_at, role, is_active FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $activeSettingsTab = 'profile';
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($username === '') {
            $errors[] = 'Username is required.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if (!$errors) {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
            $checkStmt->execute([$username, $userId]);

            if ($checkStmt->fetch()) {
                $errors[] = 'That username is already in use.';
            } elseif ($email !== '') {
                $emailCheckStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                $emailCheckStmt->execute([$email, $userId]);
                if ($emailCheckStmt->fetch()) {
                    $errors[] = 'That email address is already in use.';
                }
            }

            if (!$errors) {
                $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $updateStmt->execute([$username, $email !== '' ? $email : null, $userId]);

                $userStmt->execute([$userId]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: $user;

                logAuditEvent($pdo, 'profile_updated', 'user', $userId, [
                    'username' => $username,
                    'email' => $email !== '' ? $email : null,
                ]);

                $success = 'Profile details updated.';
            }
        }
    } elseif ($action === 'change_password') {
        $activeSettingsTab = 'profile';
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errors[] = 'Fill in all password fields.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            $passwordStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $passwordStmt->execute([$userId]);
            $passwordHash = $passwordStmt->fetchColumn();

            if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePasswordStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updatePasswordStmt->execute([$newHash, $userId]);
                $success = 'Password changed successfully.';
            }
        }
    } elseif ($action === 'create_user' && $currentUserRole === 'supervisor') {
        $activeSettingsTab = 'users';
        $newUsername = trim($_POST['new_username'] ?? '');
        $newEmail = trim($_POST['new_email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $newRole = $_POST['new_role'] ?? 'cashier';

        if ($newUsername === '' || $newPassword === '') {
            $errors[] = 'New username and password are required.';
        } elseif (!in_array($newRole, ['supervisor', 'cashier'], true)) {
            $errors[] = 'Select a valid role.';
        } else {
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $checkStmt->execute([$newUsername]);
            if ($checkStmt->fetch()) {
                $errors[] = 'That username already exists.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, ?, 1)');
                $stmt->execute([
                    $newUsername,
                    password_hash($newPassword, PASSWORD_DEFAULT),
                    $newEmail !== '' ? $newEmail : null,
                    $newRole,
                ]);
                logAuditEvent($pdo, 'user_created', 'user', (int) $pdo->lastInsertId(), ['username' => $newUsername, 'role' => $newRole]);
                $success = 'User account created.';
            }
        }
    } elseif ($action === 'update_user_role' && $currentUserRole === 'supervisor') {
        $activeSettingsTab = 'users';
        $managedUserId = (int) ($_POST['managed_user_id'] ?? 0);
        $managedRole = $_POST['managed_role'] ?? 'cashier';
        $managedActive = isset($_POST['managed_active']) ? 1 : 0;

        if ($managedUserId <= 0 || !in_array($managedRole, ['supervisor', 'cashier'], true)) {
            $errors[] = 'Invalid user update request.';
        } elseif ($managedUserId === $userId && $managedActive === 0) {
            $errors[] = 'You cannot deactivate the currently logged-in account.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET role = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$managedRole, $managedActive, $managedUserId]);
            logAuditEvent($pdo, 'user_updated', 'user', $managedUserId, ['role' => $managedRole, 'is_active' => $managedActive]);
            $success = 'User permissions updated.';
        }
    } elseif ($action === 'delete_account') {
        $activeSettingsTab = 'profile';
        $token = (string) ($_POST['csrf_token'] ?? '');
        $password = (string) ($_POST['delete_password'] ?? '');
        $confirm = isset($_POST['delete_confirm']) ? 1 : 0;

        if (!csrfValidate($token, 'delete_account')) {
            $errors[] = 'Security validation failed. Please refresh the page and try again.';
        } elseif ($confirm !== 1) {
            $errors[] = 'You must confirm that you understand this action cannot be undone.';
        } elseif ($password === '') {
            $errors[] = 'Password is required to delete your account.';
        } else {
            $passwordStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $passwordStmt->execute([$userId]);
            $hash = (string) ($passwordStmt->fetchColumn() ?: '');
            if ($hash === '' || !password_verify($password, $hash)) {
                $errors[] = 'Password is incorrect.';
            } else {
                deleteUserAccount($pdo, $userId);
                authLogoutAll($pdo);
                session_destroy();
                header('Location: index.php');
                exit;
            }
        }
    } elseif ($action === 'update_notifications') {
        $activeSettingsTab = 'notifications';
        foreach ($notificationPreferenceDefaults as $prefKey => $defaultValue) {
            $enabled = isset($_POST['notify_' . $prefKey]) ? '1' : '0';
            setUserPreference($pdo, $userId, 'notifications.' . $prefKey, $enabled);
            $notificationPreferences[$prefKey] = $enabled === '1';
        }
        $success = 'Notification settings updated.';
    } elseif ($action === 'dismiss_notification') {
        $activeSettingsTab = 'notifications';
        $notifKey = trim((string) ($_POST['notification_key'] ?? ''));
        if ($notifKey !== '' && tableExists($pdo, 'notification_reads')) {
            $stmt = $pdo->prepare("INSERT INTO notification_reads (user_id, notification_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE dismissed_at = NOW()");
            $stmt->execute([$userId, $notifKey]);
            $success = 'Notification dismissed.';
        }
    } elseif ($action === 'update_general_settings') {
        $activeSettingsTab = 'general';

        if ($currentUserRole !== 'supervisor') {
            $errors[] = 'Only supervisors can change system-wide AI settings.';
        } else {
            $assistantSettings['enabled'] = isset($_POST['ai_assistant_enabled']);
            $assistantSettings['provider'] = trim((string) ($_POST['ai_assistant_provider'] ?? 'openai-compatible'));
            $assistantSettings['endpoint'] = trim((string) ($_POST['ai_assistant_endpoint'] ?? ''));
            $assistantSettings['model'] = trim((string) ($_POST['ai_assistant_model'] ?? ''));
            $assistantSettings['timeout_seconds'] = trim((string) ($_POST['ai_assistant_timeout_seconds'] ?? '20'));
            $assistantSettings['system_prompt'] = trim((string) ($_POST['ai_assistant_system_prompt'] ?? ''));

            $submittedApiKey = trim((string) ($_POST['ai_assistant_api_key'] ?? ''));
            $clearApiKey = isset($_POST['ai_assistant_clear_api_key']);
            $storedApiKey = (string) getSetting($pdo, 'ai_assistant_api_key', '');
            $nextApiKey = $clearApiKey ? '' : ($submittedApiKey !== '' ? $submittedApiKey : $storedApiKey);

            if ($assistantSettings['provider'] === '') {
                $errors[] = 'AI provider label is required.';
            }
            if ($assistantSettings['endpoint'] === '' || !filter_var($assistantSettings['endpoint'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Enter a valid AI endpoint URL.';
            }
            if ($assistantSettings['model'] === '') {
                $errors[] = 'AI model is required.';
            }

            $timeoutSeconds = (int) $assistantSettings['timeout_seconds'];
            if ($timeoutSeconds < 5 || $timeoutSeconds > 120) {
                $errors[] = 'AI timeout must be between 5 and 120 seconds.';
            }

            if ($assistantSettings['system_prompt'] === '') {
                $errors[] = 'System prompt is required.';
            }

            if (!$errors) {
                setSetting($pdo, 'ai_assistant_enabled', $assistantSettings['enabled'] ? '1' : '0');
                setSetting($pdo, 'ai_assistant_provider', $assistantSettings['provider']);
                setSetting($pdo, 'ai_assistant_endpoint', $assistantSettings['endpoint']);
                setSetting($pdo, 'ai_assistant_model', $assistantSettings['model']);
                setSetting($pdo, 'ai_assistant_timeout_seconds', (string) $timeoutSeconds);
                setSetting($pdo, 'ai_assistant_system_prompt', $assistantSettings['system_prompt']);
                setSetting($pdo, 'ai_assistant_api_key', $nextApiKey);
                $assistantApiKeyStored = $nextApiKey !== '';

                logAuditEvent($pdo, 'setting_updated', 'setting', null, [
                    'area' => 'ai_assistant',
                    'enabled' => $assistantSettings['enabled'] ? 1 : 0,
                    'provider' => $assistantSettings['provider'],
                    'endpoint' => $assistantSettings['endpoint'],
                    'model' => $assistantSettings['model'],
                    'timeout_seconds' => $timeoutSeconds,
                    'api_key_stored' => $assistantApiKeyStored ? 1 : 0,
                ]);

                $success = 'AI assistant settings updated.';
            }
        }
    } elseif ($action === 'update_mail_settings') {
        $activeSettingsTab = 'general';

        if ($currentUserRole !== 'supervisor') {
            $errors[] = 'Only supervisors can change system-wide email settings.';
        } else {
            $mailSettings['enabled'] = isset($_POST['mail_enabled']);
            $mailSettings['host'] = trim((string) ($_POST['mail_smtp_host'] ?? ''));
            $mailSettings['port'] = trim((string) ($_POST['mail_smtp_port'] ?? '587'));
            $mailSettings['username'] = trim((string) ($_POST['mail_smtp_username'] ?? ''));
            $mailSettings['auth_enabled'] = isset($_POST['mail_smtp_auth_enabled']);
            $mailSettings['encryption'] = strtolower(trim((string) ($_POST['mail_smtp_encryption'] ?? 'tls')));
            $mailSettings['from_email'] = trim((string) ($_POST['mail_from_email'] ?? ''));
            $mailSettings['from_name'] = trim((string) ($_POST['mail_from_name'] ?? 'Kin Cafe'));
            $mailSettings['reply_to_email'] = trim((string) ($_POST['mail_reply_to_email'] ?? ''));
            $mailSettings['reply_to_name'] = trim((string) ($_POST['mail_reply_to_name'] ?? 'Kin Cafe'));
            $mailSettings['timeout_seconds'] = trim((string) ($_POST['mail_smtp_timeout_seconds'] ?? '20'));

            $submittedMailPassword = trim((string) ($_POST['mail_smtp_password'] ?? ''));
            $clearMailPassword = isset($_POST['mail_clear_smtp_password']);
            $storedMailPassword = (string) getSetting($pdo, 'mail_smtp_password', '');
            $nextMailPassword = $clearMailPassword ? '' : ($submittedMailPassword !== '' ? $submittedMailPassword : $storedMailPassword);

            $mailPort = (int) $mailSettings['port'];
            $mailTimeout = (int) $mailSettings['timeout_seconds'];

            if (!in_array($mailSettings['encryption'], ['tls', 'ssl', 'none'], true)) {
                $errors[] = 'Select a valid SMTP encryption option.';
            }
            if ($mailPort < 1 || $mailPort > 65535) {
                $errors[] = 'SMTP port must be between 1 and 65535.';
            }
            if ($mailTimeout < 5 || $mailTimeout > 120) {
                $errors[] = 'Mail timeout must be between 5 and 120 seconds.';
            }
            if ($mailSettings['from_name'] === '') {
                $errors[] = 'From name is required.';
            }
            if ($mailSettings['from_email'] !== '' && !filter_var($mailSettings['from_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid From email address.';
            }
            if ($mailSettings['reply_to_email'] !== '' && !filter_var($mailSettings['reply_to_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid Reply-To email address.';
            }

            if ($mailSettings['enabled']) {
                if ($mailSettings['host'] === '') {
                    $errors[] = 'SMTP host is required when email is enabled.';
                }
                if ($mailSettings['from_email'] === '') {
                    $errors[] = 'From email is required when email is enabled.';
                }
                if ($mailSettings['auth_enabled'] && ($mailSettings['username'] === '' || $nextMailPassword === '')) {
                    $errors[] = 'SMTP username and password are required when SMTP auth is enabled.';
                }
            }

            if (!$errors) {
                setSetting($pdo, 'mail_enabled', $mailSettings['enabled'] ? '1' : '0');
                setSetting($pdo, 'mail_smtp_host', $mailSettings['host']);
                setSetting($pdo, 'mail_smtp_port', (string) $mailPort);
                setSetting($pdo, 'mail_smtp_username', $mailSettings['username']);
                setSetting($pdo, 'mail_smtp_password', $nextMailPassword);
                setSetting($pdo, 'mail_smtp_auth_enabled', $mailSettings['auth_enabled'] ? '1' : '0');
                setSetting($pdo, 'mail_smtp_encryption', $mailSettings['encryption']);
                setSetting($pdo, 'mail_from_email', $mailSettings['from_email']);
                setSetting($pdo, 'mail_from_name', $mailSettings['from_name']);
                setSetting($pdo, 'mail_reply_to_email', $mailSettings['reply_to_email']);
                setSetting($pdo, 'mail_reply_to_name', $mailSettings['reply_to_name']);
                setSetting($pdo, 'mail_smtp_timeout_seconds', (string) $mailTimeout);
                $mailPasswordStored = $nextMailPassword !== '';

                logAuditEvent($pdo, 'setting_updated', 'setting', null, [
                    'area' => 'mail',
                    'enabled' => $mailSettings['enabled'] ? 1 : 0,
                    'host' => $mailSettings['host'],
                    'port' => $mailPort,
                    'auth_enabled' => $mailSettings['auth_enabled'] ? 1 : 0,
                    'encryption' => $mailSettings['encryption'],
                    'from_email' => $mailSettings['from_email'],
                    'from_name' => $mailSettings['from_name'],
                    'reply_to_email' => $mailSettings['reply_to_email'],
                    'timeout_seconds' => $mailTimeout,
                    'password_stored' => $mailPasswordStored ? 1 : 0,
                ]);

                $success = 'Email settings updated.';
            }
        }
    } elseif ($action === 'send_test_email') {
        $activeSettingsTab = 'general';

        if ($currentUserRole !== 'supervisor') {
            $errors[] = 'Only supervisors can send test emails.';
        } else {
            $testRecipient = trim((string) ($_POST['mail_test_recipient'] ?? ''));
            if ($testRecipient === '' || !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid test recipient email address.';
            } else {
                $result = sendProjectEmail($pdo, [
                    'to_email' => $testRecipient,
                    'to_name' => 'Kin Cafe Test Recipient',
                    'subject' => 'Kin Cafe SMTP Test Email',
                    'html_body' => '<h2>Kin Cafe Email Test</h2><p>This confirms that the Kin Cafe SMTP configuration is working.</p><p>Sent at: ' . htmlspecialchars(date('Y-m-d H:i:s')) . '</p>',
                    'text_body' => "Kin Cafe Email Test\n\nThis confirms that the Kin Cafe SMTP configuration is working.\nSent at: " . date('Y-m-d H:i:s'),
                ]);

                if ($result['success']) {
                    logAuditEvent($pdo, 'test_email_sent', 'setting', null, [
                        'recipient' => $testRecipient,
                    ]);
                    $success = 'Test email sent to ' . $testRecipient . '.';
                } else {
                    $errors[] = $result['message'];
                }
            }
        }
    } elseif ($action === 'create_backup') {
        $activeSettingsTab = 'database';
        if ($currentUserRole !== 'supervisor') {
            $errors[] = 'Only supervisors can create database backups.';
        } else {
            try {
                $backup = runAutomaticBackup($pdo, 'manual', true);
                if ($backup) {
                    logAuditEvent($pdo, 'backup_created', 'setting', null, ['file' => $backup['file_name']]);
                    $success = 'Database backup created: ' . htmlspecialchars($backup['file_name']);
                } else {
                    $errors[] = 'Failed to create database backup.';
                }
            } catch (Exception $e) {
                $errors[] = 'Backup error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'save_backup_schedule') {
        $activeSettingsTab = 'database';
        if ($currentUserRole !== 'supervisor') {
            $errors[] = 'Only supervisors can change backup schedule settings.';
        } else {
            $autoEnabled = isset($_POST['auto_backup_enabled']) ? '1' : '0';
            $autoTime = trim((string) ($_POST['auto_backup_time'] ?? '17:00'));
            setSetting($pdo, 'auto_backup_enabled', $autoEnabled);
            setSetting($pdo, 'auto_backup_time', $autoTime);
            logAuditEvent($pdo, 'setting_updated', 'setting', null, [
                'area' => 'auto_backup',
                'enabled' => $autoEnabled,
                'time' => $autoTime,
            ]);
            $success = 'Automatic daily backup settings updated.';
        }
    } elseif ($action === 'export_sql') {
        $activeSettingsTab = 'database';
        if ($currentUserRole !== 'supervisor') {
            $errors[] = 'Only supervisors can export database SQL dumps.';
        } else {
            $sqlContent = generateSqlDatabaseDump($pdo);
            $filename = 'kin_cafe_db_' . date('Y-m-d_His') . '.sql';
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($sqlContent));
            echo $sqlContent;
            exit;
        }
    } elseif ($action === 'download_backup') {
        $activeSettingsTab = 'database';
        $fileName = basename(trim((string) ($_POST['file_name'] ?? '')));
        $backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
        $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileName;

        if ($fileName === '' || !file_exists($filePath)) {
            $errors[] = 'Requested backup file not found.';
        } else {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }

    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
}

$dismissedKeys = [];
if (tableExists($pdo, 'notification_reads')) {
    $stmt = $pdo->prepare("SELECT notification_key FROM notification_reads WHERE user_id = ?");
    $stmt->execute([$userId]);
    $dismissedKeys = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

$notificationCards = [];
$pendingOrdersCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending'")->fetchColumn();
$pendingKey = 'pending_orders_' . $pendingOrdersCount;
if ($notificationPreferences['pending_orders'] && $pendingOrdersCount > 0 && !in_array('pending_orders', $dismissedKeys, true) && !in_array($pendingKey, $dismissedKeys, true)) {
    $notificationCards[] = [
        'key' => $pendingKey,
        'severity' => 'warning',
        'eyebrow' => 'Pending Orders',
        'title' => $pendingOrdersCount . ' pending order' . ($pendingOrdersCount === 1 ? '' : 's') . ' require completion.',
        'body' => 'Staff still need to complete these saved POS orders.',
        'meta' => 'Orders module',
        'action_href' => 'orders_history.php',
        'action_label' => 'Review orders',
    ];
}

$lowStockCount = 0;
$expiringCount = 0;
if (tableExists($pdo, 'ingredients')) {
    $lowStockCount = (int) $pdo->query("SELECT COUNT(*) FROM ingredients WHERE deleted_at IS NULL AND stock_quantity < 10")->fetchColumn();
    $expiringCount = (int) $pdo->query("SELECT COUNT(*) FROM ingredients WHERE deleted_at IS NULL AND expiration_date IS NOT NULL AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
}

$lowStockKey = 'low_stock_' . $lowStockCount;
if ($notificationPreferences['low_stock'] && $lowStockCount > 0 && !in_array('low_stock', $dismissedKeys, true) && !in_array($lowStockKey, $dismissedKeys, true)) {
    $notificationCards[] = [
        'key' => $lowStockKey,
        'severity' => 'danger',
        'eyebrow' => 'Inventory Alert',
        'title' => $lowStockCount . ' ingredient' . ($lowStockCount === 1 ? ' is' : 's are') . ' low on stock.',
        'body' => 'Restock soon to avoid unavailable menu items during service.',
        'meta' => 'Inventory module',
        'action_href' => 'inventory.php?tab=reordering&filter=low',
        'action_label' => 'Review reorder list',
    ];
}

$expiringKey = 'expiring_ingredients_' . $expiringCount;
if ($notificationPreferences['expiring_ingredients'] && $expiringCount > 0 && !in_array('expiring_ingredients', $dismissedKeys, true) && !in_array($expiringKey, $dismissedKeys, true)) {
    $notificationCards[] = [
        'key' => $expiringKey,
        'severity' => 'warning',
        'eyebrow' => 'Expiry Alert',
        'title' => $expiringCount . ' ingredient' . ($expiringCount === 1 ? '' : 's') . ' expire within 30 days.',
        'body' => 'Check stock rotation and remove affected items if needed.',
        'meta' => 'Inventory module',
        'action_href' => 'inventory.php?tab=waste&filter=expiring',
        'action_label' => 'Review expiring stock',
    ];
}

$unavailableSnapshot = ['total' => 0, 'items' => []];
if (!empty($notificationPreferences['unavailable_items']) && tableExists($pdo, 'menu_items')) {
    $unavailableSnapshot = getUnavailableMenuItemsSnapshot($pdo, 5);
}
$unavailableCount = (int) ($unavailableSnapshot['total'] ?? 0);
$unavailableKey = 'unavailable_items_' . $unavailableCount;
if (!empty($notificationPreferences['unavailable_items']) && $unavailableCount > 0 && !in_array('unavailable_items', $dismissedKeys, true) && !in_array($unavailableKey, $dismissedKeys, true)) {
    $previewNames = array_map(static fn(array $row) => (string) ($row['name'] ?? ''), $unavailableSnapshot['items'] ?? []);
    $previewNames = array_values(array_filter($previewNames));
    $body = $previewNames
        ? ('Examples: ' . implode(', ', array_slice($previewNames, 0, 3)) . '. Check POS or Menu for exact stock/expiry reasons.')
        : 'Open POS or Menu Management to see why each item cannot be ordered.';
    $notificationCards[] = [
        'key' => $unavailableKey,
        'severity' => 'danger',
        'eyebrow' => 'Menu Availability',
        'title' => $unavailableCount . ' menu item' . ($unavailableCount === 1 ? ' is' : 's are') . ' unavailable for ordering.',
        'body' => $body,
        'meta' => 'POS / Menu module',
        'action_href' => hasPermission($pdo, 'menu.manage') ? 'menu_management.php' : 'pos.php',
        'action_label' => 'Review unavailable items',
    ];
}

if ($notificationPreferences['backup_health'] && tableExists($pdo, 'backups') && !in_array('backup_health', $dismissedKeys, true)) {
    $latestBackup = $pdo->query("SELECT status, reason, created_at FROM backups ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
    $latestSuccessfulBackup = $pdo->query("SELECT created_at FROM backups WHERE status = 'success' ORDER BY created_at DESC LIMIT 1")->fetchColumn();
    $needsBackupAttention = false;
    $backupTitle = '';
    $backupBody = '';

    if ($latestBackup && (string) ($latestBackup['status'] ?? '') !== 'success') {
        $needsBackupAttention = true;
        $backupTitle = 'Recent backup needs attention.';
        $backupBody = 'The latest backup did not complete successfully.';
    } elseif (!$latestSuccessfulBackup) {
        $needsBackupAttention = true;
        $backupTitle = 'No successful backup recorded yet.';
        $backupBody = 'Create a backup to protect current system data.';
    } elseif (strtotime((string) $latestSuccessfulBackup) < strtotime('-7 days')) {
        $needsBackupAttention = true;
        $backupTitle = 'Backup is older than 7 days.';
        $backupBody = 'Run a new backup to keep recovery points current.';
    }

    if ($needsBackupAttention) {
        $notificationCards[] = [
            'key' => 'backup_health',
            'severity' => 'info',
            'eyebrow' => 'Backup Health',
            'title' => $backupTitle,
            'body' => $backupBody,
            'meta' => 'Analytics module',
            'action_href' => 'analytics.php',
            'action_label' => 'Open analytics',
        ];
    }
}

$enabledNotificationCount = count(array_filter($notificationPreferences));
$activeNotificationCount = count($notificationCards);

$managedUsers = [];
if (getCurrentUserRole($pdo) === 'supervisor') {
    $managedUsers = $pdo->query('SELECT id, username, email, role, is_active, created_at FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
}

$backups = [];
if (tableExists($pdo, 'backups')) {
    $backups = $pdo->query("SELECT * FROM backups ORDER BY created_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
}
$autoBackupEnabled = getSetting($pdo, 'auto_backup_enabled', '1') === '1';
$autoBackupTime = getSetting($pdo, 'auto_backup_time', '17:00');
?>

<?php include 'includes/header.php'; ?>

<div class="main-content settings-admin-page">
    <div class="page-hero">
        <div>
            <h1 class="page-title">Settings</h1>
            <p class="page-subtitle">Manage your account, users, and system preferences.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error:</strong>
            <ul class="mb-0 pl-3">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="card settings-card">
        <div class="card-body p-0">
            <div class="settings-nav">
                <button type="button" class="settings-nav-item<?php echo $activeSettingsTab === 'profile' ? ' active' : ''; ?>" data-tab="profile">Profile</button>
                <button type="button" class="settings-nav-item<?php echo $activeSettingsTab === 'security' ? ' active' : ''; ?>" data-tab="security">Security</button>
                <button type="button" class="settings-nav-item<?php echo $activeSettingsTab === 'notifications' ? ' active' : ''; ?>" data-tab="notifications">Notifications</button>
                <?php if ($currentUserRole === 'supervisor'): ?>
                <button type="button" class="settings-nav-item<?php echo $activeSettingsTab === 'users' ? ' active' : ''; ?>" data-tab="users">User Accounts</button>
                <button type="button" class="settings-nav-item<?php echo $activeSettingsTab === 'general' ? ' active' : ''; ?>" data-tab="general">General System</button>
                <?php endif; ?>
                <button type="button" class="settings-nav-item<?php echo $activeSettingsTab === 'database' ? ' active' : ''; ?>" data-tab="database">Database</button>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <div>
            <!-- PROFILE TAB -->
            <section class="settings-tab-panel<?php echo $activeSettingsTab === 'profile' ? ' active' : ''; ?>" data-panel="profile">
                <div class="card settings-card settings-summary-card">
                    <div class="card-body">
                        <h2>Account Summary</h2>
                        <div class="settings-summary-grid-modern">
                            <div class="settings-summary-box"><span>Username</span><strong><?php echo htmlspecialchars($user['username']); ?></strong></div>
                            <div class="settings-summary-box"><span>Email</span><strong><?php echo htmlspecialchars($user['email'] ?: 'Not set'); ?></strong></div>
                            <div class="settings-summary-box"><span>Role</span><strong><?php echo htmlspecialchars(ucfirst($user['role'])); ?></strong></div>
                            <div class="settings-summary-box"><span>Member Since</span><strong><?php echo htmlspecialchars(date('F j, Y', strtotime($user['created_at']))); ?></strong></div>
                        </div>
                    </div>
                </div>

                <div class="card settings-card">
                    <div class="card-header"><h4 class="mb-0">Profile Details</h4></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="username">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" maxlength="50" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="email">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Profile</button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- SECURITY TAB -->
            <section class="settings-tab-panel<?php echo $activeSettingsTab === 'security' ? ' active' : ''; ?>" data-panel="security">
                <div class="card settings-card">
                    <div class="card-header"><h4 class="mb-0">Change Password</h4></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="new_password">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- USER ACCOUNTS TAB -->
            <section class="settings-tab-panel<?php echo $activeSettingsTab === 'users' ? ' active' : ''; ?>" data-panel="users">
                <?php if ($currentUserRole === 'supervisor'): ?>
                <div class="card settings-card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:16px;">
                            <div>
                                <h4 class="mb-1 font-weight-bold">User Accounts & Access Management</h4>
                                <p class="text-muted mb-0">Manage system user accounts, assign Cashier or Supervisor roles, and toggle account activation.</p>
                            </div>
                            <button type="button" class="btn btn-primary font-weight-bold" data-toggle="modal" data-target="#createUserModal">
                                ➕ Create User Account
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card settings-card">
                    <div class="card-header"><h4 class="mb-0">User Permissions</h4></div>
                    <div class="card-body table-responsive">
                        <table class="table table-hover mb-0" style="background:#fffdfa;">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Active Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($managedUsers as $managedUser): ?>
                                <tr>
                                    <form method="post">
                                        <input type="hidden" name="action" value="update_user_role">
                                        <input type="hidden" name="managed_user_id" value="<?php echo $managedUser['id']; ?>">
                                        <td><strong class="text-dark"><?php echo htmlspecialchars($managedUser['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($managedUser['email'] ?: 'Not set'); ?></td>
                                        <td>
                                            <?php $normalizedRole = normalizeRole((string) $managedUser['role']); ?>
                                            <select name="managed_role" class="form-control form-control-sm" style="max-width:140px;">
                                                <option value="cashier" <?php echo $normalizedRole === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                                <option value="supervisor" <?php echo $normalizedRole === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" name="managed_active" id="userActive_<?php echo $managedUser['id']; ?>" class="custom-control-input" value="1" <?php echo !empty($managedUser['is_active']) ? 'checked' : ''; ?>>
                                                <label class="custom-control-label font-weight-bold" for="userActive_<?php echo $managedUser['id']; ?>">Active</label>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <button type="submit" class="btn btn-sm btn-outline-primary font-weight-bold">Save Changes</button>
                                        </td>
                                    </form>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal: Create User Account -->
                <div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content" style="background:#fffdfa;">
                            <form method="post">
                                <input type="hidden" name="action" value="create_user">
                                <div class="modal-header">
                                    <h5 class="modal-title font-weight-bold" id="createUserModalLabel">Create New User Account</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Username</label>
                                        <input type="text" class="form-control" name="new_username" maxlength="50" placeholder="e.g. cashier1" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="font-weight-bold">Email Address (Optional)</label>
                                        <input type="email" class="form-control" name="new_email" maxlength="100" placeholder="e.g. cashier1@kincafe.local">
                                    </div>
                                    <div class="form-group">
                                        <label class="font-weight-bold">Password</label>
                                        <input type="password" class="form-control" name="new_password" minlength="8" placeholder="At least 8 characters" required>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="font-weight-bold">Role</label>
                                        <select class="form-control" name="new_role">
                                            <option value="cashier">Cashier</option>
                                            <option value="supervisor">Supervisor</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary font-weight-bold">Create Account</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card settings-card"><div class="card-body"><p class="mb-0 text-muted">Only supervisors can manage additional accounts.</p></div></div>
                <?php endif; ?>
            </section>

            <section class="settings-tab-panel<?php echo $activeSettingsTab === 'notifications' ? ' active' : ''; ?>" data-panel="notifications">
                <div class="card settings-card settings-summary-card">
                    <div class="card-body">
                        <h2>Notification Center</h2>
                        <div class="settings-summary-grid-modern settings-notification-summary-grid">
                            <div class="settings-summary-box"><span>Active Alerts</span><strong><?php echo $activeNotificationCount; ?></strong></div>
                            <div class="settings-summary-box"><span>Enabled Rules</span><strong><?php echo $enabledNotificationCount; ?></strong></div>
                            <div class="settings-summary-box"><span>Pending Orders</span><strong><?php echo $pendingOrdersCount; ?></strong></div>
                            <div class="settings-summary-box"><span>Inventory Warnings</span><strong><?php echo $lowStockCount + $expiringCount; ?></strong></div>
                        </div>
                    </div>
                </div>

                <div class="card settings-card">
                    <div class="card-header"><h4 class="mb-0">Notification Preferences</h4></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="update_notifications">
                            <div class="settings-notification-pref-list">
                                <?php foreach ($notificationPreferenceLabels as $prefKey => $prefMeta): ?>
                                    <label class="settings-notification-pref">
                                        <span class="settings-notification-pref-copy">
                                            <strong><?php echo htmlspecialchars($prefMeta['title']); ?></strong>
                                            <small><?php echo htmlspecialchars($prefMeta['description']); ?></small>
                                        </span>
                                        <span class="settings-notification-toggle">
                                            <input type="checkbox" name="notify_<?php echo htmlspecialchars($prefKey); ?>" value="1" <?php echo !empty($notificationPreferences[$prefKey]) ? 'checked' : ''; ?>>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Save Notification Settings</button>
                        </form>
                    </div>
                </div>

                <div class="card settings-card">
                    <div class="card-header"><h4 class="mb-0">Live System Alerts</h4></div>
                    <div class="card-body">
                        <?php if ($notificationCards): ?>
                            <div class="settings-notification-list">
                                <?php foreach ($notificationCards as $notification): ?>
                                    <article class="settings-notification-card is-<?php echo htmlspecialchars($notification['severity']); ?>">
                                        <div class="settings-notification-content">
                                            <span class="settings-notification-eyebrow"><?php echo htmlspecialchars($notification['eyebrow']); ?></span>
                                            <h5><?php echo htmlspecialchars($notification['title']); ?></h5>
                                            <p><?php echo htmlspecialchars($notification['body']); ?></p>
                                            <span class="settings-notification-meta"><?php echo htmlspecialchars($notification['meta']); ?></span>
                                        </div>
                                         <div class="d-flex flex-column align-items-end gap-2">
                                             <a class="btn btn-sm btn-outline-secondary mb-1" href="<?php echo htmlspecialchars($notification['action_href']); ?>"><?php echo htmlspecialchars($notification['action_label']); ?></a>
                                             <?php if (!empty($notification['key'])): ?>
                                             <form method="post" class="d-inline">
                                                 <input type="hidden" name="action" value="dismiss_notification">
                                                 <input type="hidden" name="notification_key" value="<?php echo htmlspecialchars($notification['key']); ?>">
                                                 <button type="submit" class="btn btn-sm btn-light text-muted" style="font-size:0.75rem;">Dismiss</button>
                                             </form>
                                             <?php endif; ?>
                                         </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="settings-notification-empty">
                                <h5>No active alerts</h5>
                                <p class="text-muted mb-0">The enabled notification rules are currently clear.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="settings-tab-panel<?php echo $activeSettingsTab === 'general' ? ' active' : ''; ?>" data-panel="general">
                <div class="card settings-card settings-summary-card">
                    <div class="card-body">
                        <h2>AI Assistant Integration</h2>
                        <div class="settings-summary-grid-modern">
                            <div class="settings-summary-box"><span>Assistant Status</span><strong><?php echo $assistantSettings['enabled'] ? 'Enabled' : 'Disabled'; ?></strong></div>
                            <div class="settings-summary-box"><span>Provider</span><strong><?php echo htmlspecialchars($assistantSettings['provider']); ?></strong></div>
                            <div class="settings-summary-box"><span>Model</span><strong><?php echo htmlspecialchars($assistantSettings['model']); ?></strong></div>
                            <div class="settings-summary-box"><span>API Key</span><strong><?php echo $assistantApiKeyStored ? 'Stored' : 'Not stored'; ?></strong></div>
                        </div>
                    </div>
                </div>

                <div class="card settings-card settings-summary-card">
                    <div class="card-body">
                        <h2>Email Delivery</h2>
                        <div class="settings-summary-grid-modern">
                            <div class="settings-summary-box"><span>Mailer Library</span><strong><?php echo $mailLibraryAvailable ? 'Detected' : 'Missing'; ?></strong></div>
                            <div class="settings-summary-box"><span>Mail Status</span><strong><?php echo $mailSettings['enabled'] ? 'Enabled' : 'Disabled'; ?></strong></div>
                            <div class="settings-summary-box"><span>SMTP Host</span><strong><?php echo $mailSettings['host'] !== '' ? htmlspecialchars($mailSettings['host']) : 'Not set'; ?></strong></div>
                            <div class="settings-summary-box"><span>SMTP Password</span><strong><?php echo $mailPasswordStored ? 'Stored' : 'Not stored'; ?></strong></div>
                        </div>
                    </div>
                </div>

                <?php if ($currentUserRole === 'supervisor'): ?>
                <div class="card settings-card">
                    <div class="card-header"><h4 class="mb-0">Virtual Assistant Provider</h4></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="update_general_settings">
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="ai_assistant_enabled" name="ai_assistant_enabled" value="1" <?php echo $assistantSettings['enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ai_assistant_enabled">Enable external AI for the Virtual Assistant module</label>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="ai_assistant_provider">Provider Label</label>
                                    <input type="text" class="form-control" id="ai_assistant_provider" name="ai_assistant_provider" value="<?php echo htmlspecialchars($assistantSettings['provider']); ?>" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="ai_assistant_model">Model</label>
                                    <input type="text" class="form-control" id="ai_assistant_model" name="ai_assistant_model" value="<?php echo htmlspecialchars($assistantSettings['model']); ?>" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="ai_assistant_timeout_seconds">Timeout (seconds)</label>
                                    <input type="number" class="form-control" id="ai_assistant_timeout_seconds" name="ai_assistant_timeout_seconds" min="5" max="120" value="<?php echo htmlspecialchars($assistantSettings['timeout_seconds']); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="ai_assistant_endpoint">Endpoint</label>
                                <input type="url" class="form-control" id="ai_assistant_endpoint" name="ai_assistant_endpoint" value="<?php echo htmlspecialchars($assistantSettings['endpoint']); ?>" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-9">
                                    <label for="ai_assistant_api_key">API Key</label>
                                    <input type="password" class="form-control" id="ai_assistant_api_key" name="ai_assistant_api_key" placeholder="<?php echo $assistantApiKeyStored ? 'Leave blank to keep the saved key' : 'Paste provider API key'; ?>">
                                </div>
                                <div class="form-group col-md-3 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" id="ai_assistant_clear_api_key" name="ai_assistant_clear_api_key" value="1">
                                        <label class="form-check-label" for="ai_assistant_clear_api_key">Clear saved key</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="ai_assistant_system_prompt">System Prompt</label>
                                <textarea class="form-control" id="ai_assistant_system_prompt" name="ai_assistant_system_prompt" rows="5" required><?php echo htmlspecialchars($assistantSettings['system_prompt']); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Save AI Settings</button>
                        </form>
                        <p class="text-muted mt-3 mb-0">Environment variables `KIN_CAFE_AI_API_KEY`, `KIN_CAFE_AI_MODEL`, `KIN_CAFE_AI_ENDPOINT`, `KIN_CAFE_AI_ENABLED`, `KIN_CAFE_AI_PROVIDER`, and `KIN_CAFE_AI_SYSTEM_PROMPT` override these saved settings when present.</p>
                    </div>
                </div>

                <div class="card settings-card">
                    <div class="card-header"><h4 class="mb-0">SMTP Email Settings</h4></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="update_mail_settings">
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="mail_enabled" name="mail_enabled" value="1" <?php echo $mailSettings['enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mail_enabled">Enable application email sending</label>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-5">
                                    <label for="mail_smtp_host">SMTP Host</label>
                                    <input type="text" class="form-control" id="mail_smtp_host" name="mail_smtp_host" value="<?php echo htmlspecialchars($mailSettings['host']); ?>" placeholder="smtp.gmail.com">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="mail_smtp_port">Port</label>
                                    <input type="number" class="form-control" id="mail_smtp_port" name="mail_smtp_port" min="1" max="65535" value="<?php echo htmlspecialchars($mailSettings['port']); ?>">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="mail_smtp_encryption">Encryption</label>
                                    <select class="form-control" id="mail_smtp_encryption" name="mail_smtp_encryption">
                                        <option value="tls" <?php echo $mailSettings['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS / STARTTLS</option>
                                        <option value="ssl" <?php echo $mailSettings['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo $mailSettings['encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="mail_smtp_timeout_seconds">Timeout</label>
                                    <input type="number" class="form-control" id="mail_smtp_timeout_seconds" name="mail_smtp_timeout_seconds" min="5" max="120" value="<?php echo htmlspecialchars($mailSettings['timeout_seconds']); ?>">
                                </div>
                            </div>
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="mail_smtp_auth_enabled" name="mail_smtp_auth_enabled" value="1" <?php echo $mailSettings['auth_enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mail_smtp_auth_enabled">Use SMTP authentication</label>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="mail_smtp_username">SMTP Username</label>
                                    <input type="text" class="form-control" id="mail_smtp_username" name="mail_smtp_username" value="<?php echo htmlspecialchars($mailSettings['username']); ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="mail_smtp_password">SMTP Password</label>
                                    <input type="password" class="form-control" id="mail_smtp_password" name="mail_smtp_password" placeholder="<?php echo $mailPasswordStored ? 'Leave blank to keep the saved password' : 'Enter SMTP password'; ?>">
                                </div>
                            </div>
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="mail_clear_smtp_password" name="mail_clear_smtp_password" value="1">
                                <label class="form-check-label" for="mail_clear_smtp_password">Clear saved SMTP password</label>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="mail_from_email">From Email</label>
                                    <input type="email" class="form-control" id="mail_from_email" name="mail_from_email" value="<?php echo htmlspecialchars($mailSettings['from_email']); ?>" placeholder="noreply@kincafe.local">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="mail_from_name">From Name</label>
                                    <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" value="<?php echo htmlspecialchars($mailSettings['from_name']); ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="mail_reply_to_email">Reply-To Email</label>
                                    <input type="email" class="form-control" id="mail_reply_to_email" name="mail_reply_to_email" value="<?php echo htmlspecialchars($mailSettings['reply_to_email']); ?>" placeholder="support@kincafe.local">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="mail_reply_to_name">Reply-To Name</label>
                                    <input type="text" class="form-control" id="mail_reply_to_name" name="mail_reply_to_name" value="<?php echo htmlspecialchars($mailSettings['reply_to_name']); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Email Settings</button>
                        </form>
                        <p class="text-muted mt-3 mb-0">Environment variables `KIN_CAFE_MAIL_ENABLED`, `KIN_CAFE_SMTP_HOST`, `KIN_CAFE_SMTP_PORT`, `KIN_CAFE_SMTP_USERNAME`, `KIN_CAFE_SMTP_PASSWORD`, `KIN_CAFE_SMTP_ENCRYPTION`, and `KIN_CAFE_MAIL_FROM_EMAIL` override the saved settings when present.</p>
                    </div>
                </div>

                <div class="card settings-card">
                    <div class="card-header"><h4 class="mb-0">Send Test Email</h4></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="send_test_email">
                            <div class="form-row align-items-end">
                                <div class="form-group col-md-8">
                                    <label for="mail_test_recipient">Recipient Email</label>
                                    <input type="email" class="form-control" id="mail_test_recipient" name="mail_test_recipient" value="<?php echo htmlspecialchars((string) ($user['email'] ?? '')); ?>" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <button type="submit" class="btn btn-outline-primary btn-block">Send Test Email</button>
                                </div>
                            </div>
                        </form>
                        <p class="text-muted mb-0">Use this after saving SMTP details to confirm the app can deliver real email through the configured server.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="card settings-card"><div class="card-body"><p class="mb-0 text-muted">Only supervisors can change AI provider and email delivery settings.</p></div></div>
                <?php endif; ?>
            </section>

            <!-- DATABASE BACKUP TAB -->
            <section class="settings-tab-panel<?php echo $activeSettingsTab === 'database' ? ' active' : ''; ?>" data-panel="database">
                <!-- Top Action Banner -->
                <div class="card settings-card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:16px;">
                            <div>
                                <h4 class="mb-1 font-weight-bold">Database Backup & Maintenance</h4>
                                <p class="text-muted mb-0">Create instant system snapshots, configure automatic daily 5:00 PM backups, or export raw SQL dumps.</p>
                            </div>
                            <?php if ($currentUserRole === 'supervisor'): ?>
                            <div class="d-flex align-items-center" style="gap:10px;">
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="export_sql">
                                    <button type="submit" class="btn btn-outline-secondary font-weight-bold">📥 Export SQL Dump</button>
                                </form>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="create_backup">
                                    <button type="submit" class="btn btn-primary font-weight-bold">💾 Create Backup Now</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Automatic 5:00 PM Schedule Settings Card -->
                <?php if ($currentUserRole === 'supervisor'): ?>
                <div class="card settings-card mb-4">
                    <div class="card-body">
                        <h5 class="font-weight-bold mb-3">Automatic Daily Backup Schedule</h5>
                        <form method="post">
                            <input type="hidden" name="action" value="save_backup_schedule">
                            <div class="form-row align-items-center">
                                <div class="form-group col-md-5 mb-3 mb-md-0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="auto_backup_enabled" id="autoBackupEnabled" class="custom-control-input" value="1" <?php echo $autoBackupEnabled ? 'checked' : ''; ?>>
                                        <label class="custom-control-label font-weight-bold" for="autoBackupEnabled">Enable Automatic Daily Backup</label>
                                    </div>
                                    <small class="form-text text-muted">Automatically generates a database snapshot once per day at the designated time.</small>
                                </div>
                                <div class="form-group col-md-4 mb-3 mb-md-0">
                                    <label class="font-weight-bold mb-1">Scheduled Daily Time</label>
                                    <select name="auto_backup_time" class="form-control">
                                        <option value="17:00" <?php echo $autoBackupTime === '17:00' ? 'selected' : ''; ?>>5:00 PM (Default Store Closing Time)</option>
                                        <option value="18:00" <?php echo $autoBackupTime === '18:00' ? 'selected' : ''; ?>>6:00 PM</option>
                                        <option value="19:00" <?php echo $autoBackupTime === '19:00' ? 'selected' : ''; ?>>7:00 PM</option>
                                        <option value="20:00" <?php echo $autoBackupTime === '20:00' ? 'selected' : ''; ?>>8:00 PM</option>
                                        <option value="12:00" <?php echo $autoBackupTime === '12:00' ? 'selected' : ''; ?>>12:00 PM (Noon)</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3 mb-0 text-right">
                                    <button type="submit" class="btn btn-primary font-weight-bold btn-block">Save Schedule</button>
                                </div>
                            </div>
                        </form>
                        <div class="mt-3 p-3 rounded" style="background:#f8f3eb; border:1px solid rgba(142,104,77,0.2);">
                            <small class="text-muted">
                                <strong>Status:</strong> Automatic daily backup is <strong><?php echo $autoBackupEnabled ? 'ENABLED' : 'DISABLED'; ?></strong> for <strong><?php echo date('g:i A', strtotime($autoBackupTime)); ?></strong> daily. 
                                <?php
                                    $todayBackupRan = false;
                                    foreach ($backups as $b) {
                                        if (in_array($b['reason'], ['scheduled_5pm', 'scheduled_daily'], true) && date('Y-m-d', strtotime($b['created_at'])) === date('Y-m-d')) {
                                            $todayBackupRan = true;
                                            break;
                                        }
                                    }
                                ?>
                                <?php if ($todayBackupRan): ?>
                                    <span class="badge badge-success ml-2">✓ Today's 5:00 PM Backup Completed</span>
                                <?php else: ?>
                                    <span class="badge badge-info ml-2">⏳ Next scheduled run: Today at <?php echo date('g:i A', strtotime($autoBackupTime)); ?></span>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Backup History Table -->
                <div class="card settings-card">
                    <div class="card-body">
                        <h5 class="font-weight-bold mb-3">Backup History & Snapshots</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" style="background:#fffdfa;">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Trigger Reason</th>
                                        <th>Status</th>
                                        <th>Created Date & Time</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $bk): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark"><?php echo htmlspecialchars($bk['file_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                                $reasonLabel = strtoupper($bk['reason']);
                                                if ($bk['reason'] === 'scheduled_5pm') $reasonLabel = 'SCHEDULED (5:00 PM)';
                                                elseif ($bk['reason'] === 'manual') $reasonLabel = 'MANUAL BACKUP';
                                                elseif ($bk['reason'] === 'auto') $reasonLabel = 'SYSTEM AUTO';
                                            ?>
                                            <span class="badge badge-secondary"><?php echo $reasonLabel; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $bk['status'] === 'success' ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo strtoupper($bk['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($bk['created_at'])); ?></td>
                                        <td class="text-right">
                                            <?php if ($bk['status'] === 'success'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="download_backup">
                                                <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($bk['file_name']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary font-weight-bold">📥 Download JSON</button>
                                            </form>
                                            <?php else: ?>
                                                <span class="text-muted small"><?php echo htmlspecialchars($bk['message'] ?: 'Failed'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$backups): ?>
                                        <tr><td colspan="5" class="text-muted text-center py-4">No backups recorded yet. Click "Create Backup Now" above to generate one.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.settings-nav-item[data-tab]').forEach((button) => {
        button.addEventListener('click', function () {
            const tab = button.getAttribute('data-tab');
            document.querySelectorAll('.settings-nav-item').forEach((item) => item.classList.remove('active'));
            document.querySelectorAll('.settings-tab-panel').forEach((panel) => panel.classList.remove('active'));
            button.classList.add('active');
            const target = document.querySelector('.settings-tab-panel[data-panel="' + tab + '"]');
            if (target) {
                target.classList.add('active');
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
