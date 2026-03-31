<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

if (isAdminLoggedIn()) {
  header('Location: index.php');
  exit;
}
handleMockGoogleAuth($conn, 'admin');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validateCsrf();
  checkLoginAttempts('admin');

  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $admin = $stmt->fetch();

  if ($admin && password_verify($password, $admin['password'])) {
    clearLoginAttempts('admin');
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    header('Location: index.php');
    exit;
  } else {
    registerFailedAttempt('admin');
    $error = 'Invalid credentials.';
  }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login – MIZ MAX</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: #0a0a1a;
    }

    .login-card {
      width: 100%;
      max-width: 440px;
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(30px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 24px;
      padding: 3rem;
      box-shadow: 0 40px 100px rgba(0, 0, 0, 0.4);
    }

    .input-group {
      position: relative;
    }

    .input-group .icon {
      position: absolute;
      left: 1.25rem;
      top: 50%;
      transform: translateY(-50%);
      color: rgba(255, 255, 255, 0.3);
      font-size: 1.1rem;
    }

    .input-group .form-control {
      padding-left: 3.25rem;
      height: 3.5rem;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #fff;
    }

    .input-group .form-control:focus {
      background: rgba(255, 255, 255, 0.08);
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.15);
    }

    .form-label {
      color: rgba(255, 255, 255, 0.7);
      font-weight: 600;
      margin-bottom: 0.6rem;
    }

    .btn-admin {
      background: linear-gradient(135deg, var(--primary), #FA709A);
      border: none;
      color: #fff;
      font-weight: 700;
      border-radius: 16px;
      transition: all 0.3s ease;
    }

    .btn-admin:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(108, 99, 255, 0.4);
    }

    .google-btn-mock {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #fff !important;
      font-weight: 600 !important;
      border-radius: 16px !important;
    }

    .google-btn-mock:hover {
      background: rgba(255, 255, 255, 0.1) !important;
      transform: translateY(-2px);
    }
  </style>
</head>

<body>
  <div style="width:100%; max-width:420px; padding:1.25rem;">
    <div style="text-align:center; margin-bottom:2rem;">
      <div
        style="font-size:2rem; font-weight:900; background:linear-gradient(135deg,var(--primary),#FA709A); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; margin-bottom:0.25rem;">
        ✦ MIZ MAX</div>
      <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:2px;">Admin Panel
      </div>
    </div>
    <div class="login-card">
      <h2 style="font-size:1.4rem; font-weight:800; margin-bottom:0.5rem;">Welcome back 👋</h2>
      <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:2rem;">Sign in to access the admin dashboard
      </p>
      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <i class="bi bi-envelope icon"></i>
            <input type="email" name="email" class="form-control" placeholder="admin@MIZ MAX.com" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-group">
            <i class="bi bi-lock icon"></i>
            <input type="password" name="password" class="form-control" id="adminPw" placeholder="Enter password"
              required>
            <button type="button" onclick="togglePw()"
              style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(240,240,255,0.4);cursor:pointer;">
              <i class="bi bi-eye" id="pwIcon"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-admin"
          style="width:100%; justify-content:center; padding:1.1rem; font-size:1.05rem; margin-top:0.5rem;">
          <i class="bi bi-shield-check" style="margin-right: 8px;"></i> Access Admin Panel
        </button>
      </form>

      <div
        style="display:flex; align-items:center; gap:1rem; margin:1.5rem 0; color:var(--text-muted); font-size:0.8rem;">
        <div style="flex:1; height:1px; background:var(--border);"></div>
        OR
        <div style="flex:1; height:1px; background:var(--border);"></div>
      </div>

      <button onclick="simulateGoogleLogin(event)" class="google-btn-mock"
        style="width:100%; justify-content:center; padding:1.1rem; font-size:1rem; display:flex; align-items:center; cursor:pointer;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48" style="margin-right:12px;">
          <path fill="#FFC107"
            d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z" />
          <path fill="#FF3D00"
            d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z" />
          <path fill="#4CAF50"
            d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z" />
          <path fill="#1976D2"
            d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z" />
        </svg>
        Continue with Google
      </button>

      <div style="text-align:center; margin-top:1.5rem; font-size:0.8rem; color:var(--text-muted);">
        Default: admin@MIZ MAX.com / admin123
      </div>
    </div>
    <p style="text-align:center; margin-top:1.25rem;">
      <a href="../index.php" style="color:var(--text-muted); font-size:0.85rem; text-decoration:none;"><i
          class="bi bi-arrow-left"></i> Back to Store</a>
    </p>
  </div>
  <script>
    function togglePw() {
      const input = document.getElementById('adminPw');
      const icon = document.getElementById('pwIcon');
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.className = input.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
    }

    function simulateGoogleLogin(e) {
      const btn = e.currentTarget;
      btn.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Connecting...';
      btn.disabled = true;

      setTimeout(() => {
        window.location.href = 'auth-google.php';
      }, 1000);
    }
  </script>
</body>

</html>