<?php
/**
 * admin_secure_panel/index.php
 * Separate admin login with IP restriction, activity logging, and role-based access.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/CoreSecurity.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$security = new CoreSecurity($pdo);

// CSRF Generation
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$ip = $_SERVER['REMOTE_ADDR'];

// Apply rate limit specifically for admin endpoints (more strict)
$security->enforceRateLimit($ip);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['admin_csrf'], $_POST['csrf'])) {
        die("Security Alert: Invalid CSRF Token.");
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    // IP Restriction Check (Optional based on admin setting)
    $ipAllowed = true;
    if ($admin && !empty($admin['allowed_ips'])) {
        $allowedIps = array_map('trim', explode(',', $admin['allowed_ips']));
        if (!in_array($ip, $allowedIps)) {
            $ipAllowed = false;
        }
    }
    
    if ($admin && password_verify($password, $admin['password']) && $ipAllowed) {
        // Success
        $security->clearLoginAttempts($ip);
        
        // Prevent fixation
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_ip'] = $ip;
        
        // Log activity
        $security->logAdminActivity($admin['id'], 'Login Successful', $ip);
        
        $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
        
        header('Location: dashboard.php');
        exit;
        
    } else {
        // Failed
        if ($admin) {
            $security->logAdminActivity($admin['id'], 'Failed Login Attempt' . (!$ipAllowed ? ' (IP Disallowed)' : ''), $ip);
        }
        $security->logFailedAttempt($ip, 'admin_'.$username);
        $error = "Invalid username or password, or access restricted from this IP.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Admin - Secure Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #111827; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #fff; }
        .admin-card { background: #1f2937; border-radius: 12px; box-shadow: 0 15px 35px rgba(0,0,0,0.5); border: 1px solid #374151; width: 100%; max-width: 400px; padding: 40px; }
        .form-control { background: #374151; border: 1px solid #4b5563; color: white; border-radius: 8px; padding: 12px; }
        .form-control:focus { background: #4b5563; border-color: #6366f1; color: white; box-shadow: 0 0 0 2px rgba(99,102,241,0.2); }
        .btn-admin { background: #6366f1; border: none; padding: 12px; border-radius: 8px; width: 100%; font-weight: 600; color: white; }
        .btn-admin:hover { background: #4f46e5; }
        .logo-box { text-align: center; margin-bottom: 30px; }
        .logo-box i { font-size: 40px; color: #6366f1; }
    </style>
</head>
<body>

<div class="admin-card">
    <div class="logo-box">
        <i class="fas fa-shield-alt"></i>
        <h4 class="mt-3">Secure Admin Gateway</h4>
        <p class="text-muted small">Authorized Personnel Only</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger p-2 small"><i class="fas fa-lock"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf" value="<?= $_SESSION['admin_csrf'] ?>">
        <div class="mb-4">
            <label class="form-label text-white-50 small text-uppercase fw-bold">Username</label>
            <input type="text" name="username" class="form-control" autocomplete="off" required>
        </div>
        <div class="mb-4">
            <label class="form-label text-white-50 small text-uppercase fw-bold">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-admin"><i class="fas fa-sign-in-alt"></i> Authenticate Core</button>
    </form>
    
    <div class="text-center mt-4">
        <small class="text-white-50">IP Address logged: <?= htmlspecialchars($ip) ?></small>
    </div>
</div>

</body>
</html>
