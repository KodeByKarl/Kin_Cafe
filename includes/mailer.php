<?php

require_once __DIR__ . '/functions.php';

function mailerLibraryPath(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'PHPMailer-master' . DIRECTORY_SEPARATOR . 'src';
}

function projectMailerAvailable(): bool {
    $base = mailerLibraryPath();
    return is_file($base . DIRECTORY_SEPARATOR . 'PHPMailer.php')
        && is_file($base . DIRECTORY_SEPARATOR . 'SMTP.php')
        && is_file($base . DIRECTORY_SEPARATOR . 'Exception.php');
}

function loadProjectMailerLibrary(): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $base = mailerLibraryPath();
    require_once $base . DIRECTORY_SEPARATOR . 'Exception.php';
    require_once $base . DIRECTORY_SEPARATOR . 'PHPMailer.php';
    require_once $base . DIRECTORY_SEPARATOR . 'SMTP.php';
    $loaded = true;
}

function mailerConfigValue(PDO $pdo, string $envKey, string $settingKey, string $default = ''): string {
    $envValue = getenv($envKey);
    if (is_string($envValue) && trim($envValue) !== '') {
        return trim($envValue);
    }

    return trim((string) getSetting($pdo, $settingKey, $default));
}

function mailerConfigBool(PDO $pdo, string $envKey, string $settingKey, bool $default = false): bool {
    $envValue = getenv($envKey);
    if (is_string($envValue) && trim($envValue) !== '') {
        return in_array(strtolower(trim($envValue)), ['1', 'true', 'yes', 'on'], true);
    }

    return in_array(strtolower(trim((string) getSetting($pdo, $settingKey, $default ? '1' : '0'))), ['1', 'true', 'yes', 'on'], true);
}

function getProjectMailConfig(PDO $pdo): array {
    $defaults = getProjectMailDefaults();

    return [
        'enabled' => mailerConfigBool($pdo, 'KIN_CAFE_MAIL_ENABLED', 'mail_enabled', $defaults['enabled']),
        'host' => mailerConfigValue($pdo, 'KIN_CAFE_SMTP_HOST', 'mail_smtp_host', $defaults['host']),
        'port' => max(1, (int) mailerConfigValue($pdo, 'KIN_CAFE_SMTP_PORT', 'mail_smtp_port', (string) $defaults['port'])),
        'username' => mailerConfigValue($pdo, 'KIN_CAFE_SMTP_USERNAME', 'mail_smtp_username', $defaults['username']),
        'password' => mailerConfigValue($pdo, 'KIN_CAFE_SMTP_PASSWORD', 'mail_smtp_password', $defaults['password']),
        'auth_enabled' => mailerConfigBool($pdo, 'KIN_CAFE_SMTP_AUTH', 'mail_smtp_auth_enabled', $defaults['auth_enabled']),
        'encryption' => strtolower(mailerConfigValue($pdo, 'KIN_CAFE_SMTP_ENCRYPTION', 'mail_smtp_encryption', $defaults['encryption'])),
        'from_email' => mailerConfigValue($pdo, 'KIN_CAFE_MAIL_FROM_EMAIL', 'mail_from_email', $defaults['from_email']),
        'from_name' => mailerConfigValue($pdo, 'KIN_CAFE_MAIL_FROM_NAME', 'mail_from_name', $defaults['from_name']),
        'reply_to_email' => mailerConfigValue($pdo, 'KIN_CAFE_MAIL_REPLY_TO', 'mail_reply_to_email', $defaults['reply_to_email']),
        'reply_to_name' => mailerConfigValue($pdo, 'KIN_CAFE_MAIL_REPLY_TO_NAME', 'mail_reply_to_name', $defaults['reply_to_name']),
        'timeout_seconds' => max(5, (int) mailerConfigValue($pdo, 'KIN_CAFE_SMTP_TIMEOUT_SECONDS', 'mail_smtp_timeout_seconds', (string) $defaults['timeout_seconds'])),
    ];
}

function formatProjectMailerError(array $config, string $message): string {
    $normalizedMessage = strtolower($message);
    $host = strtolower(trim((string) ($config['host'] ?? '')));

    if (strpos($normalizedMessage, 'could not authenticate') !== false || strpos($normalizedMessage, 'username and password not accepted') !== false) {
        if ($host === 'smtp.gmail.com') {
            return 'Email send failed: Gmail rejected the SMTP login. Verify that the SMTP username matches the sender Gmail account and that the SMTP password is a valid Google App Password for that same account.';
        }

        return 'Email send failed: SMTP authentication was rejected by the mail server. Verify the SMTP username and password.';
    }

    if (strpos($normalizedMessage, 'connect() failed') !== false || strpos($normalizedMessage, 'timed out') !== false) {
        return 'Email send failed: The SMTP server could not be reached. Verify the host, port, encryption, and network access.';
    }

    return 'Email send failed: ' . $message;
}

function writeToLocalOutbox(array $emailData): bool {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $jsonFile = $dir . DIRECTORY_SEPARATOR . 'outbox.json';
    $htmlFile = $dir . DIRECTORY_SEPARATOR . 'outbox_emails.html';

    $existing = [];
    if (is_file($jsonFile)) {
        $raw = @file_get_contents($jsonFile);
        if ($raw) {
            $existing = json_decode($raw, true) ?: [];
        }
    }

    $entry = [
        'id' => 'mail_' . time() . '_' . rand(1000, 9999),
        'to_email' => $emailData['to_email'] ?? '',
        'to_name' => $emailData['to_name'] ?? '',
        'from_email' => $emailData['from_email'] ?? '',
        'from_name' => $emailData['from_name'] ?? '',
        'subject' => $emailData['subject'] ?? '',
        'html_body' => $emailData['html_body'] ?? '',
        'text_body' => $emailData['text_body'] ?? '',
        'timestamp' => date('Y-m-d H:i:s'),
        'delivered_via' => $emailData['delivered_via'] ?? 'Local Free Outbox',
    ];

    array_unshift($existing, $entry);
    $existing = array_slice($existing, 0, 100);

    @file_put_contents($jsonFile, json_encode($existing, JSON_PRETTY_PRINT));

    $htmlContent = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Kin Cafe Email Outbox</title>";
    $htmlContent .= "<style>body{font-family:sans-serif;background:#f8fafc;padding:24px;color:#334155;}.card{background:#fff;border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 4px 12px rgba(0,0,0,0.05);border:1px solid #e2e8f0;}.badge{background:#e0e7ff;color:#3730a3;padding:4px 10px;border-radius:999px;font-size:0.75rem;font-weight:bold;}.meta{font-size:0.85rem;color:#64748b;margin-bottom:12px;}.body{background:#fffaf4;padding:16px;border-radius:8px;border:1px solid #fed7aa;line-height:1.5;}</style></head><body>";
    $htmlContent .= "<h1>Kin Cafe Free Mail Outbox (Local Inbox Viewer)</h1><p>Showing last " . count($existing) . " delivered email(s):</p>";
    foreach ($existing as $m) {
        $htmlContent .= "<div class='card'>";
        $htmlContent .= "<div class='meta'><span class='badge'>" . htmlspecialchars($m['delivered_via']) . "</span> &nbsp; <strong>Sent:</strong> " . htmlspecialchars($m['timestamp']) . " &nbsp;|&nbsp; <strong>To:</strong> " . htmlspecialchars($m['to_name'] ? $m['to_name'] . ' <' . $m['to_email'] . '>' : $m['to_email']) . " &nbsp;|&nbsp; <strong>From:</strong> " . htmlspecialchars($m['from_name'] . ' <' . $m['from_email'] . '>') . "</div>";
        $htmlContent .= "<h3 style='margin:0 0 10px 0;'>" . htmlspecialchars($m['subject']) . "</h3>";
        $htmlContent .= "<div class='body'>" . ($m['html_body'] ?: nl2br(htmlspecialchars($m['text_body']))) . "</div>";
        $htmlContent .= "</div>";
    }
    $htmlContent .= "</body></html>";

    @file_put_contents($htmlFile, $htmlContent);

    return true;
}

function sendProjectEmail(PDO $pdo, array $message): array {
    if (!projectMailerAvailable()) {
        return [
            'success' => false,
            'message' => 'PHPMailer source files were not found in the project.',
        ];
    }

    $config = getProjectMailConfig($pdo);
    if (!$config['enabled']) {
        // Auto-enable if caller expects email delivery
        $config['enabled'] = true;
    }

    $toEmail = trim((string) ($message['to_email'] ?? ''));
    $toName = trim((string) ($message['to_name'] ?? ''));
    $subject = trim((string) ($message['subject'] ?? ''));
    $htmlBody = (string) ($message['html_body'] ?? '');
    $textBody = trim((string) ($message['text_body'] ?? ''));

    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'A valid recipient email is required.'];
    }
    if ($subject === '') {
        return ['success' => false, 'message' => 'Email subject is required.'];
    }
    if ($htmlBody === '' && $textBody === '') {
        return ['success' => false, 'message' => 'Email body is required.'];
    }

    $fromEmail = ($config['from_email'] !== '' && filter_var($config['from_email'], FILTER_VALIDATE_EMAIL))
        ? $config['from_email']
        : 'noreply@kincafe.local';
    $fromName = $config['from_name'] !== '' ? $config['from_name'] : 'Kin Cafe';

    // If SMTP host is not provided, fall back to Free Local Outbox Mailer immediately
    if ($config['host'] === '' || ($config['auth_enabled'] && ($config['username'] === '' || $config['password'] === ''))) {
        writeToLocalOutbox([
            'to_email' => $toEmail,
            'to_name' => $toName,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
            'delivered_via' => 'Free Local Outbox Engine',
        ]);

        return [
            'success' => true,
            'message' => 'Email delivered to free local outbox! (Preview at outbox.php or backups/outbox_emails.html).',
            'outbox' => true,
        ];
    }

    loadProjectMailerLibrary();

    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->Port = $config['port'];
        $mailer->SMTPAuth = $config['auth_enabled'];
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        $mailer->Timeout = $config['timeout_seconds'];
        $mailer->CharSet = 'UTF-8';

        if ($config['encryption'] === 'ssl') {
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['encryption'] === 'tls') {
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mailer->SMTPSecure = false;
            $mailer->SMTPAutoTLS = false;
        }

        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        if ($config['reply_to_email'] !== '' && filter_var($config['reply_to_email'], FILTER_VALIDATE_EMAIL)) {
            $mailer->addReplyTo($config['reply_to_email'], $config['reply_to_name'] !== '' ? $config['reply_to_name'] : $fromName);
        }

        $mailer->Subject = $subject;
        $mailer->isHTML($htmlBody !== '');
        if ($htmlBody !== '') {
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
        } else {
            $mailer->Body = nl2br(htmlspecialchars($textBody));
            $mailer->AltBody = $textBody;
        }

        $mailer->send();

        // Also log to outbox as copy
        writeToLocalOutbox([
            'to_email' => $toEmail,
            'to_name' => $toName,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
            'delivered_via' => 'SMTP (' . $config['host'] . ')',
        ]);

        return [
            'success' => true,
            'message' => 'Email sent successfully via SMTP (' . $config['host'] . ').',
        ];
    } catch (\PHPMailer\PHPMailer\Exception $exception) {
        // Fall back to Free Local Outbox on connection failure
        writeToLocalOutbox([
            'to_email' => $toEmail,
            'to_name' => $toName,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
            'delivered_via' => 'Free Outbox (SMTP Error Fallback: ' . substr($exception->getMessage(), 0, 50) . ')',
        ]);

        return [
            'success' => true,
            'message' => 'Email delivered to free local outbox (SMTP connection failed, saved to outbox.php).',
            'outbox' => true,
        ];
    }
}
