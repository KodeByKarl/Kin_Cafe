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

function sendProjectEmail(PDO $pdo, array $message): array {
    if (!projectMailerAvailable()) {
        return [
            'success' => false,
            'message' => 'PHPMailer source files were not found in the project.',
        ];
    }

    $config = getProjectMailConfig($pdo);
    if (!$config['enabled']) {
        return [
            'success' => false,
            'message' => 'Email sending is disabled in settings.',
        ];
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
    if ($config['host'] === '') {
        return ['success' => false, 'message' => 'SMTP host is not configured.'];
    }
    if ($config['from_email'] === '' || !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'A valid From email is required in mail settings.'];
    }
    if ($config['auth_enabled'] && ($config['username'] === '' || $config['password'] === '')) {
        return ['success' => false, 'message' => 'SMTP username and password are required when SMTP auth is enabled.'];
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

        $mailer->setFrom($config['from_email'], $config['from_name']);
        $mailer->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        if ($config['reply_to_email'] !== '' && filter_var($config['reply_to_email'], FILTER_VALIDATE_EMAIL)) {
            $mailer->addReplyTo($config['reply_to_email'], $config['reply_to_name'] !== '' ? $config['reply_to_name'] : $config['from_name']);
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

        return [
            'success' => true,
            'message' => 'Email sent successfully.',
        ];
    } catch (\PHPMailer\PHPMailer\Exception $exception) {
        return [
            'success' => false,
            'message' => formatProjectMailerError($config, $exception->getMessage()),
        ];
    }
}
