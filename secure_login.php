<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/CoreSecurity.php';

$security = new CoreSecurity($pdo);

// Initialize Session if not already done
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Force HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
}

// 2. Add Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';");

$error = '';
$success = '';
$tab = $_GET['tab'] ?? 'login';

$ip = $_SERVER['REMOTE_ADDR'];

// Apply rate limiting for the IP based on DB attempts
$security->enforceRateLimit($ip);

$showCaptcha = false; // Turn true if attempts >= 3

// Determine if we need to show CAPTCHA based on attempts
$stmt = $pdo->prepare("SELECT attempts FROM login_attempts WHERE ip_address = ?");
$stmt->execute([$ip]);
$attemptRow = $stmt->fetch();
if ($attemptRow && $attemptRow['attempts'] >= 3) {
    $showCaptcha = true;
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    validateCsrf(); // Assumes getCsrfToken exists from config/security.php
    
    // Validate CAPTCHA if required
    if ($showCaptcha && (!isset($_POST['captcha']) || $_POST['captcha'] !== $_SESSION['captcha_code'])) {
        $error = "Invalid CAPTCHA code.";
    } else {
        $identifier = trim($_POST['identifier'] ?? ''); // Email or Phone
        $password = $_POST['password'] ?? '';
        
        // Find user by Email OR Phone using PDO
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR phone = ?) LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check if user is blocked natively
            if (!empty($user['is_blocked'])) {
                $error = "Your account is temporarily blocked. Contact support.";
            } else {
                // Determine if OTP is required
                if (isset($_POST['otp_code'])) {
                    if ($security->verifyOTP($identifier, $_POST['otp_code'])) {
                        // OTP Verified! Login successful
                        $security->clearLoginAttempts($ip);
                        
                        // Prevent Session Hijacking Setup
                        session_regenerate_id(true);
                        $_SESSION['client_ip'] = $ip;
                        $_SESSION['client_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                        $_SESSION['last_activity'] = time();
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        
                        // Update tracking columns
                        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                        $pdo->prepare("UPDATE users SET last_login_ip = ?, last_login_device = ? WHERE id = ?")->execute([$ip, $ua, $user['id']]);
                        
                        header('Location: index.php');
                        exit;
                    } else {
                        $error = "Invalid or expired OTP.";
                    }
                } else {
                    // Send OTP and show OTP field
                    $otp = $security->generateOTP($identifier, $user['id']);
                    // TODO: Replace with actual SMS/Email sending logic (e.g. WhatsApp API)
                    // mail($user['email'], "Your OTP", "Code: $otp");
                    
                    $success = "An OTP has been sent to your identifier. Please enter it below. (Mock OTP: $otp)";
                    $showOtpField = true;
                }
            }
        } else {
            // Failed Login
            $shouldShowCaptcha = $security->logFailedAttempt($ip, $identifier);
            if ($shouldShowCaptcha) $showCaptcha = true;
            $error = 'Invalid email/phone or password.';
        }
    }
}

// Handle Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    validateCsrf();
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validate Strong Password Rules
    $passValidation = $security->validateStrongPassword($password);

    if (!$name || !$email || !$password || !$phone) { $error = 'All fields are required.'; $tab = 'register'; }
    elseif ($password !== $confirm) { $error = 'Passwords do not match.'; $tab = 'register'; }
    elseif ($passValidation !== true) { $error = $passValidation; $tab = 'register'; }
    else {
        // Prevent enumerations - check existing
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $check->execute([$email, $phone]);
        if ($check->rowCount() > 0) {
            $error = 'Email or Phone already registered.'; 
            $tab = 'register'; 
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $phone, $hash])) {
                $success = "Registration successful! Please login.";
                $tab = 'login';
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

// Generate simple text CAPTCHA
if ($showCaptcha && empty($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = rand(1000, 9999);
}

// Generate new CSRF token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Access</title>
    <!-- Clean Bootstrap UI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .auth-card { background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; max-width: 450px; width: 100%; border: 1px solid #eaeaea; }
        .auth-header { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 30px; text-align: center; }
        .auth-body { padding: 30px; }
        .nav-pills .nav-link { border-radius: 50px; color: #555; font-weight: 500; }
        .nav-pills .nav-link.active { background-color: #1e3c72; color: #fff; }
        .form-control { border-radius: 8px; padding: 12px 15px; border: 1px solid #ccc; }
        .btn-primary { background-color: #1e3c72; border: none; border-radius: 8px; padding: 12px; font-weight: 600; width: 100%; }
        .btn-primary:hover { background-color: #152b53; }
        .captcha-box { background: #eef2f5; padding: 15px; border-radius: 8px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #333; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-header">
        <h3 class="fw-bold mb-0">Secure Portal</h3>
        <p class="mb-0 text-white-50">Enterprise Grade Authentication</p>
    </div>
    <div class="auth-body">
        
        <ul class="nav nav-pills nav-justified mb-4" id="pills-tab" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'login' ? 'active' : '' ?>" href="?tab=login">Login</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'register' ? 'active' : '' ?>" href="?tab=register">Register</a>
            </li>
        </ul>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($tab === 'login'): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-3">
                <label class="form-label">Email or Phone Number</label>
                <input type="text" name="identifier" class="form-control" required placeholder="Ex: you@email.com or +123456789">
            </div>
            
            <?php if (!isset($showOtpField)): ?>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Enter password">
            </div>
            <?php endif; ?>

            <?php if (isset($showOtpField) && $showOtpField): ?>
            <div class="mb-3">
                <label class="form-label text-success fw-bold">Enter OTP (2FA Required)</label>
                <input type="text" name="otp_code" class="form-control border-success" required placeholder="6-digit code">
                <!-- Keep inputs to retain via POST -->
                <input type="hidden" name="password" value="<?= htmlspecialchars($_POST['password']) ?>">
            </div>
            <?php endif; ?>

            <?php if ($showCaptcha): ?>
            <div class="mb-3">
                <label class="form-label text-danger">Security Check: Too many failed attempts</label>
                <div class="captcha-box"><?= $_SESSION['captcha_code'] ?? rand(1000, 9999) ?></div>
                <input type="text" name="captcha" class="form-control" required placeholder="Enter numbers above">
            </div>
            <?php endif; ?>

            <button type="submit" name="login" class="btn btn-primary mt-2">
                <?= isset($showOtpField) ? 'Verify Secure Login' : 'Secure Login' ?>
            </button>
        </form>
        <?php endif; ?>

        <?php if ($tab === 'register'): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Min 8 chars, 1 uppercase, 1 number, 1 symbol">
                <small class="text-muted">Requires strong password.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" name="register" class="btn btn-primary mt-2">Create Secure Account</button>
        </form>
        <?php endif; ?>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
