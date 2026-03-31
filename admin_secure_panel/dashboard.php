<?php
/**
 * admin_secure_panel/dashboard.php
 * Demonstrates role-based access, session hijacking prevention, and activity logs.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/CoreSecurity.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$security = new CoreSecurity($pdo);

// Prevent Session Hijacking & Auto-logout
$security->secureSession(30); // 30 mins timeout

// Role-based Access Check
if (empty($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$role = $_SESSION['admin_role'] ?? 'staff';

// Only 'superadmin' and 'admin' can access certain areas
$isSuperAdmin = $role === 'superadmin';

// Log page view
$security->logAdminActivity($_SESSION['admin_id'], 'Viewed Dashboard', $_SERVER['REMOTE_ADDR']);

// Example File Upload Handler Using CoreSecurity
$uploadMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['secure_file'])) {
    // Validate CSRF
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['admin_csrf'], $_POST['csrf'])) {
        die("Security Alert: Invalid CSRF Token.");
    }
    
    // Directory outside public html if possible, or protected
    $uploadDir = __DIR__ . '/../uploads/secure_assets/'; 
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    // Create an .htaccess inside the upload dir to prevent PHP execution
    if (!file_exists($uploadDir . '.htaccess')) {
        file_put_contents($uploadDir . '.htaccess', "php_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8");
    }

    $uploadResult = $security->secureImageUpload($_FILES['secure_file'], $uploadDir);
    if ($uploadResult['status']) {
        $uploadMsg = "<div class='alert alert-success'>Success! Encrypted Name: " . $uploadResult['filename'] . "</div>";
    } else {
        $uploadMsg = "<div class='alert alert-danger'>Error: " . $uploadResult['msg'] . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style> body { background: #f4f6f9; } </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#"><i class="fas fa-shield-alt"></i> Secure Control Panel</a>
        <span class="navbar-text">
            Logged in as: <strong><?= htmlspecialchars(strtoupper($role)) ?></strong>
            <a href="logout.php" class="btn btn-sm btn-outline-danger ms-3">Logout</a>
        </span>
    </div>
</nav>

<div class="container mt-5">
    <h3>Dashboard Overview</h3>
    <hr>
    
    <div class="row">
        <!-- Secure Upload Demo -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">Secure Image Upload</div>
                <div class="card-body">
                    <?= $uploadMsg ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= $_SESSION['admin_csrf'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Upload Image (JPG, PNG, WEBP ONLY)</label>
                            <input type="file" name="secure_file" class="form-control" accept="image/jpeg, image/png, image/webp" required>
                        </div>
                        <button class="btn btn-primary" type="submit">Upload Securely</button>
                    </form>
                    <small class="text-muted d-block mt-2">File will be renamed and MIME-validated server-side.</small>
                </div>
            </div>
        </div>

        <!-- Super Admin Demo -->
        <?php if ($isSuperAdmin): ?>
        <div class="col-md-6 mb-4">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white"><i class="fas fa-lock"></i> Super Admin Only Zone</div>
                <div class="card-body">
                    <p>Because you are a Super Admin, you can see this sensitive area (e.g., manage roles, view raw database logs).</p>
                    <button class="btn btn-danger btn-sm">Delete Activity Logs</button>
                    <button class="btn btn-warning btn-sm">Manage Allowed IPs</button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-md-6 mb-4">
            <div class="card bg-light text-muted">
                <div class="card-body text-center mt-4">
                    <i class="fas fa-lock fa-3x mb-3 text-secondary"></i>
                    <p>You do not have permission to view Super Admin controls.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
