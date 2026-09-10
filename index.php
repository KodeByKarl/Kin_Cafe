<?php
session_start();

require 'includes/db.php';
require_once 'includes/mailer.php';

if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php?tab=' . urlencode(authTabId()));
    exit;
}

$error = '';
$success = '';
$resetError = '';
$activePanel = 'login';
$loginUsername = '';
$resetUsername = '';
$resetEmail = '';
$resetOtp = '';
$resetStage = 'request';

function clearPendingPasswordReset(): void {
    unset($_SESSION['password_reset']);
}

function normalizePasswordResetIdentity(string $value): string {
    return strtolower(trim($value));
}

$pendingPasswordReset = $_SESSION['password_reset'] ?? null;
if (is_array($pendingPasswordReset)) {
    $expiresAt = (int) ($pendingPasswordReset['expires_at'] ?? 0);
    if ($expiresAt <= time()) {
        clearPendingPasswordReset();
        $pendingPasswordReset = null;
    } else {
        $resetStage = 'verify';
        $activePanel = 'forgot';
        $resetUsername = (string) ($pendingPasswordReset['username'] ?? '');
        $resetEmail = (string) ($pendingPasswordReset['email'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'login'));

    if ($action === 'request_password_otp') {
        $activePanel = 'forgot';
        $resetUsername = trim((string) ($_POST['reset_username'] ?? ''));
        $resetEmail = trim((string) ($_POST['reset_email'] ?? ''));
        $resetPassword = (string) ($_POST['reset_password'] ?? '');
        $resetConfirmPassword = (string) ($_POST['reset_confirm_password'] ?? '');
        $resetToken = (string) ($_POST['csrf_token'] ?? '');
        $resetStage = 'request';

        if (!csrfValidate($resetToken, 'forgot_password')) {
            $resetError = 'Security validation failed. Refresh the page and try again.';
        } elseif ($resetUsername === '' || $resetEmail === '' || $resetPassword === '' || $resetConfirmPassword === '') {
            $resetError = 'Fill in your username, email, and new password.';
        } elseif (!filter_var($resetEmail, FILTER_VALIDATE_EMAIL)) {
            $resetError = 'Enter a valid email address.';
        } elseif (strlen($resetPassword) < 8) {
            $resetError = 'New password must be at least 8 characters.';
        } elseif (passwordContainsWhitespace($resetPassword)) {
            $resetError = 'Password cannot contain spaces.';
        } elseif ($resetPassword !== $resetConfirmPassword) {
            $resetError = 'New password and confirmation do not match.';
        } else {
            $normalizedResetUsername = normalizePasswordResetIdentity($resetUsername);
            $normalizedResetEmail = normalizePasswordResetIdentity($resetEmail);

            $stmt = $pdo->prepare('SELECT id, username, email, is_active FROM users WHERE LOWER(TRIM(username)) = ? LIMIT 1');
            $stmt->execute([$normalizedResetUsername]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $storedEmail = normalizePasswordResetIdentity((string) ($user['email'] ?? ''));

            if (!$user || empty($user['is_active']) || $storedEmail === '' || $storedEmail !== $normalizedResetEmail) {
                $resetError = 'We could not verify that username and email combination for an active account.';
            } else {
                $otp = generatePasswordResetOtp();
                $otpMeta = createPasswordResetOtp($pdo, (int) $user['id'], (string) $user['email'], $otp);

                $mailResult = sendProjectEmail($pdo, [
                    'to_email' => (string) $user['email'],
                    'to_name' => (string) $user['username'],
                    'subject' => 'Kin Cafe Password Reset OTP',
                    'html_body' => '<p>Your Kin Cafe password reset code is <strong style="font-size:1.25rem;letter-spacing:0.18em;">' . htmlspecialchars($otp) . '</strong>.</p><p>This code expires in 10 minutes. If you did not request this reset, you can ignore this email.</p>',
                    'text_body' => "Your Kin Cafe password reset code is {$otp}.\n\nThis code expires in 10 minutes. If you did not request this reset, you can ignore this email.",
                ]);

                if (empty($mailResult['success'])) {
                    clearPasswordResetOtps($pdo, (int) $user['id'], (string) $user['email']);
                    $resetError = (string) ($mailResult['message'] ?? 'Unable to send the password reset OTP.');
                } else {
                    $_SESSION['password_reset'] = [
                        'user_id' => (int) $user['id'],
                        'username' => (string) $user['username'],
                        'email' => (string) $user['email'],
                        'password_hash' => password_hash($resetPassword, PASSWORD_DEFAULT),
                        'expires_at' => strtotime((string) $otpMeta['expires_at']),
                    ];
                    $pendingPasswordReset = $_SESSION['password_reset'];
                    $resetStage = 'verify';
                    $success = 'An OTP has been sent to your registered email. Enter it below to finish resetting your password.';
                    logAuditEvent($pdo, 'password_reset_otp_requested', 'user', (int) $user['id'], [
                        'username' => $user['username'],
                        'method' => 'login_forgot_password',
                    ]);
                }
            }
        }
    } elseif ($action === 'verify_password_otp') {
        $activePanel = 'forgot';
        $resetStage = 'verify';
        $resetOtp = trim((string) ($_POST['reset_otp'] ?? ''));
        $resetToken = (string) ($_POST['csrf_token'] ?? '');

        $pendingPasswordReset = $_SESSION['password_reset'] ?? null;
        if (!csrfValidate($resetToken, 'forgot_password')) {
            $resetError = 'Security validation failed. Refresh the page and try again.';
        } elseif (!is_array($pendingPasswordReset)) {
            $resetError = 'Your password reset session has expired. Request a new OTP.';
            clearPendingPasswordReset();
            $resetStage = 'request';
        } elseif ((int) ($pendingPasswordReset['expires_at'] ?? 0) <= time()) {
            clearPasswordResetOtps($pdo, (int) ($pendingPasswordReset['user_id'] ?? 0), (string) ($pendingPasswordReset['email'] ?? ''));
            clearPendingPasswordReset();
            $pendingPasswordReset = null;
            $resetError = 'Your OTP has expired. Request a new OTP.';
            $resetStage = 'request';
        } elseif ($resetOtp === '') {
            $resetError = 'Enter the OTP sent to your email.';
        } else {
            $resetUsername = (string) ($pendingPasswordReset['username'] ?? '');
            $resetEmail = (string) ($pendingPasswordReset['email'] ?? '');

            $verifyResult = verifyPasswordResetOtp(
                $pdo,
                (int) ($pendingPasswordReset['user_id'] ?? 0),
                (string) ($pendingPasswordReset['email'] ?? ''),
                $resetOtp
            );

            if (empty($verifyResult['success'])) {
                $resetError = (string) ($verifyResult['message'] ?? 'Unable to verify the OTP.');
                if (strpos(strtolower($resetError), 'request a new otp') !== false) {
                    clearPendingPasswordReset();
                    $pendingPasswordReset = null;
                    $resetStage = 'request';
                }
            } else {
                $updatePasswordStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $updatePasswordStmt->execute([
                    (string) ($pendingPasswordReset['password_hash'] ?? ''),
                    (int) $pendingPasswordReset['user_id'],
                ]);
                clearPasswordResetOtps($pdo, (int) $pendingPasswordReset['user_id'], (string) $pendingPasswordReset['email']);
                logAuditEvent($pdo, 'password_reset', 'user', (int) $pendingPasswordReset['user_id'], [
                    'username' => $pendingPasswordReset['username'],
                    'method' => 'login_forgot_password_otp',
                ]);

                $success = 'Password reset successfully. You can now sign in with your new password.';
                $mailResult = sendProjectEmail($pdo, [
                    'to_email' => (string) $pendingPasswordReset['email'],
                    'to_name' => (string) $pendingPasswordReset['username'],
                    'subject' => 'Kin Cafe Password Reset Confirmation',
                    'html_body' => '<p>Your Kin Cafe account password was reset successfully.</p><p>If you did not make this change, contact a supervisor immediately.</p>',
                    'text_body' => "Your Kin Cafe account password was reset successfully.\n\nIf you did not make this change, contact a supervisor immediately.",
                ]);
                if (!empty($mailResult['success'])) {
                    $success .= ' A confirmation email has been sent.';
                }

                $loginUsername = (string) $pendingPasswordReset['username'];
                clearPendingPasswordReset();
                $pendingPasswordReset = null;
                $resetUsername = '';
                $resetEmail = '';
                $resetOtp = '';
                $resetStage = 'request';
                $activePanel = 'login';
            }
        }
    } else {
        $loginUsername = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $loginToken = (string) ($_POST['csrf_token'] ?? '');

        if (!csrfValidate($loginToken, 'login')) {
            $error = 'Security validation failed. Refresh the page and try again.';
        } else {
            $lockStatus = getLoginLockoutStatus($pdo, $loginUsername);
            if (!empty($lockStatus['locked'])) {
                $error = formatLoginCooldownMessage((int) $lockStatus['remaining_seconds']);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$loginUsername]);
                $user = $stmt->fetch();

                if ($user && !empty($user['is_active']) && password_verify($password, $user['password'])) {
                    clearFailedLoginAttempts($pdo, $loginUsername);
                    $tabId = authTabId();
                    authLoginToTab($pdo, $tabId, $user);
                    authBootstrap($pdo);
                    logAuditEvent($pdo, 'login', 'user', (int) $user['id'], ['username' => $user['username'], 'tab' => $tabId]);
                    header('Location: dashboard.php?tab=' . urlencode($tabId));
                    exit;
                }

                $lockStatus = recordFailedLoginAttempt($pdo, $loginUsername);
                if (!empty($lockStatus['locked'])) {
                    $error = formatLoginCooldownMessage((int) $lockStatus['remaining_seconds']);
                } else {
                    $attemptsLeft = (int) ($lockStatus['attempts_remaining'] ?? 0);
                    $error = 'Invalid username, password, or inactive account.';
                    if ($attemptsLeft > 0) {
                        $error .= ' ' . $attemptsLeft . ' attempt' . ($attemptsLeft === 1 ? '' : 's') . ' remaining before a temporary lockout.';
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    $brandLogoPath = 'assets/images/kin-cafe-logo.jpg';
    $brandLogoVersion = @filemtime(__DIR__ . '/assets/images/kin-cafe-logo.jpg') ?: time();
    $brandLogoUrl = $brandLogoPath . '?v=' . $brandLogoVersion;
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kin Café</title>
    <link rel="icon" type="image/jpeg" href="<?php echo htmlspecialchars($brandLogoUrl); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #2a1d16;
            --muted: rgba(255, 247, 240, 0.72);
            --glass: rgba(255, 252, 248, 0.88);
            --line: rgba(42, 29, 22, 0.12);
            --accent: #6f4a33;
            --accent-deep: #563828;
        }

        * { box-sizing: border-box; }

        body.login-page {
            margin: 0;
            min-height: 100vh;
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
            background-color: #2a1d16;
            background-image: url('assets/images/KinBG.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        body.login-page::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(circle at 18% 20%, rgba(255, 214, 170, 0.18), transparent 36%),
                linear-gradient(180deg, rgba(18, 12, 9, 0.42), rgba(18, 12, 9, 0.72));
            pointer-events: none;
        }

        .login-shell {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: clamp(20px, 4vw, 48px);
        }

        .login-panel {
            width: min(100%, 420px);
            animation: loginRise 520ms cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes loginRise {
            from { opacity: 0; transform: translateY(18px) scale(0.985); }
            to { opacity: 1; transform: none; }
        }

        .login-card {
            background: var(--glass);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 28px;
            box-shadow: 0 24px 60px rgba(10, 6, 4, 0.35);
            overflow: hidden;
        }

        .login-card-head {
            padding: 28px 28px 8px;
            text-align: center;
        }

        .brand-logo img {
            width: min(148px, 46vw);
            height: auto;
            display: block;
            margin: 0 auto 14px;
            border-radius: 50%;
            box-shadow: 0 10px 28px rgba(42, 29, 22, 0.18);
        }

        .brand-title {
            margin: 0;
            font-family: 'Fraunces', Georgia, serif;
            font-size: clamp(1.7rem, 4vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--ink);
        }

        .login-card-body {
            padding: 18px 28px 28px;
        }

        .login-form-stack,
        .forgot-password-panel .login-form-stack {
            display: grid;
            gap: 14px;
        }

        .login-field label {
            display: block;
            margin: 0 0 6px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgba(42, 29, 22, 0.58);
        }

        .login-field input {
            width: 100%;
            min-height: 52px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.72);
            padding: 0 16px;
            font: inherit;
            font-size: 1rem;
            color: var(--ink);
            outline: none;
            transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }

        .login-field .kc-password-wrap {
            position: relative;
        }

        .login-field .kc-password-wrap input {
            padding-right: 52px;
        }

        .kc-password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: 0;
            background: transparent;
            color: rgba(42, 29, 22, 0.55);
            cursor: pointer;
            display: grid;
            place-items: center;
            padding: 0;
        }

        .kc-password-toggle svg {
            width: 20px;
            height: 20px;
        }

        .login-form-meta {
            display: flex;
            justify-content: flex-end;
            min-height: 20px;
        }

        .forgot-password-trigger {
            border: 0;
            background: transparent;
            padding: 0;
            color: var(--accent);
            font: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
        }

        .forgot-password-trigger:hover,
        .forgot-password-trigger:focus {
            color: var(--accent-deep);
            text-decoration: underline;
        }

        .login-submit {
            width: 100%;
            min-height: 52px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-deep) 100%);
            color: #fffaf4;
            font: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 160ms ease, filter 160ms ease;
        }

        .login-submit:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }

        .login-alert {
            margin: 0 0 14px;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 0.92rem;
            line-height: 1.45;
        }

        .login-alert.is-error {
            background: rgba(176, 58, 46, 0.1);
            color: #8a2f26;
        }

        .login-alert.is-success {
            background: rgba(34, 120, 78, 0.1);
            color: #1f6b45;
        }

        .forgot-password-panel {
            display: none;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
        }

        .forgot-password-panel.is-open {
            display: block;
            animation: loginRise 360ms ease both;
        }

        .forgot-password-copy h2 {
            margin: 0 0 4px;
            font-family: 'Fraunces', Georgia, serif;
            font-size: 1.15rem;
            color: var(--ink);
        }

        .forgot-password-copy p {
            margin: 0 0 14px;
            color: rgba(42, 29, 22, 0.62);
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .forgot-password-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .forgot-password-actions button {
            min-height: 48px;
            border-radius: 12px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-reset {
            border: 0;
            background: var(--accent);
            color: #fffaf4;
        }

        .btn-cancel {
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.7);
            color: var(--ink);
        }

        .login-form-stack.is-hidden {
            display: none;
        }

        @media (max-width: 480px) {
            .login-card-head,
            .login-card-body {
                padding-left: 20px;
                padding-right: 20px;
            }

            .forgot-password-actions {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-panel,
            .forgot-password-panel.is-open {
                animation: none;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-shell">
        <div class="login-panel">
            <div class="login-card">
                <div class="login-card-head">
                    <div class="brand-logo">
                        <img src="<?php echo htmlspecialchars($brandLogoUrl); ?>" alt="Kin Café">
                    </div>
                    <h1 class="brand-title">Kin Café</h1>
                </div>
                <div class="login-card-body">
                    <?php if ($success): ?>
                        <div class="login-alert is-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="login-alert is-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($resetError): ?>
                        <div class="login-alert is-error"><?php echo htmlspecialchars($resetError); ?></div>
                    <?php endif; ?>

                    <form method="post" class="login-form-stack<?php echo $activePanel === 'forgot' ? ' is-hidden' : ''; ?>" id="loginForm">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken('login')); ?>">
                        <div class="login-field">
                            <label for="username">Username</label>
                            <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($loginUsername); ?>" required autocomplete="username" placeholder="admin">
                        </div>
                        <div class="login-field">
                            <label for="password">Password</label>
                            <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="••••••••" data-no-spaces="1">
                        </div>
                        <div class="login-form-meta">
                            <button type="button" class="forgot-password-trigger" id="forgotPasswordTrigger" aria-controls="forgotPasswordPanel" aria-expanded="<?php echo $activePanel === 'forgot' ? 'true' : 'false'; ?>">Forgot password?</button>
                        </div>
                        <button type="submit" class="login-submit">Sign In</button>
                    </form>

                    <div class="forgot-password-panel<?php echo $activePanel === 'forgot' ? ' is-open' : ''; ?>" id="forgotPasswordPanel">
                        <div class="forgot-password-copy">
                            <h2><?php echo $resetStage === 'verify' ? 'Enter OTP' : 'Reset password'; ?></h2>
                            <p><?php echo $resetStage === 'verify'
                                ? 'Check your email for the one-time code.'
                                : 'Username, registered email, and a new password.'; ?></p>
                        </div>
                        <form method="post" class="login-form-stack">
                            <input type="hidden" name="action" value="<?php echo $resetStage === 'verify' ? 'verify_password_otp' : 'request_password_otp'; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken('forgot_password')); ?>">
                            <?php if ($resetStage === 'verify'): ?>
                                <div class="login-field">
                                    <label for="reset_username">Username</label>
                                    <input type="text" name="reset_username" id="reset_username" value="<?php echo htmlspecialchars($resetUsername); ?>" readonly>
                                </div>
                                <div class="login-field">
                                    <label for="reset_email">Email</label>
                                    <input type="email" name="reset_email" id="reset_email" value="<?php echo htmlspecialchars($resetEmail); ?>" readonly>
                                </div>
                                <div class="login-field">
                                    <label for="reset_otp">OTP</label>
                                    <input type="text" name="reset_otp" id="reset_otp" value="<?php echo htmlspecialchars($resetOtp); ?>" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required>
                                </div>
                            <?php else: ?>
                                <div class="login-field">
                                    <label for="reset_username">Username</label>
                                    <input type="text" name="reset_username" id="reset_username" value="<?php echo htmlspecialchars($resetUsername); ?>" required>
                                </div>
                                <div class="login-field">
                                    <label for="reset_email">Email</label>
                                    <input type="email" name="reset_email" id="reset_email" value="<?php echo htmlspecialchars($resetEmail); ?>" required>
                                </div>
                                <div class="login-field">
                                    <label for="reset_password">New password</label>
                                    <input type="password" name="reset_password" id="reset_password" minlength="8" required data-no-spaces="1" pattern="\S{8,}" title="At least 8 characters with no spaces">
                                </div>
                                <div class="login-field">
                                    <label for="reset_confirm_password">Confirm</label>
                                    <input type="password" name="reset_confirm_password" id="reset_confirm_password" minlength="8" required data-no-spaces="1" pattern="\S{8,}" title="At least 8 characters with no spaces">
                                </div>
                            <?php endif; ?>
                            <div class="forgot-password-actions">
                                <button type="submit" class="btn-reset"><?php echo $resetStage === 'verify' ? 'Verify OTP' : 'Send OTP'; ?></button>
                                <button type="button" class="btn-cancel" id="forgotPasswordCancel">Back</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        const key = 'kc_tab_id';
        const params = new URLSearchParams(window.location.search);
        const isSessionTab = function (value) {
            return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(value || '')) || String(value || '') === 'default' || /^[0-9a-f]{20,}$/i.test(String(value || ''));
        };
        let tab = params.get('tab');
        if (!tab || !isSessionTab(tab)) {
            tab = sessionStorage.getItem(key);
            if (!tab || !isSessionTab(tab)) {
                tab = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Math.random().toString(16).slice(2) + Date.now().toString(16));
                sessionStorage.setItem(key, tab);
            }
            params.set('tab', tab);
            window.location.replace(window.location.pathname + '?' + params.toString() + window.location.hash);
            return;
        }
        sessionStorage.setItem(key, tab);
    })();

    (function () {
        const params = new URLSearchParams(window.location.search);
        if (params.get('logged_out') === '1') {
            try {
                sessionStorage.removeItem('kc_tab_id');
            } catch (e) {}
            params.delete('logged_out');
            const next = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
            window.history.replaceState({}, '', next);
        }
        // After logout, Back should not restore a cached admin page.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    })();

    (function () {
        const trigger = document.getElementById('forgotPasswordTrigger');
        const panel = document.getElementById('forgotPasswordPanel');
        const cancel = document.getElementById('forgotPasswordCancel');
        const loginForm = document.getElementById('loginForm');
        if (!trigger || !panel) return;

        function setPanelState(isOpen) {
            panel.classList.toggle('is-open', isOpen);
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (loginForm) loginForm.classList.toggle('is-hidden', isOpen);
        }

        trigger.addEventListener('click', function () {
            setPanelState(!panel.classList.contains('is-open'));
        });
        if (cancel) cancel.addEventListener('click', function () { setPanelState(false); });
        setPanelState(panel.classList.contains('is-open'));
    })();
    </script>
    <script src="assets/js/password-toggle.js"></script>
</body>
</html>
