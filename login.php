<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

if (isUserLoggedIn()) { header('Location: ' . SITE_URL . '/index'); exit; }
handleMockGoogleAuth($conn, 'user');

$error = '';
$success = '';
$tab = $_GET['tab'] ?? 'login';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    validateCsrf();
    checkLoginAttempts('user');
    

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_blocked = 0 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        clearLoginAttempts('user');
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $redirect = $_GET['redirect'] ?? (SITE_URL . '/index');
        if(!str_starts_with($redirect, 'http')) $redirect = SITE_URL . '/' . ltrim($redirect, '/');
        header('Location: ' . $redirect);
        exit;
    } else {
        registerFailedAttempt('user');
        $error = 'Invalid email or password.';
        $tab = 'login';
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

    if (!$name || !$email || !$password) { $error = 'All fields are required.'; $tab = 'register'; }
    elseif ($password !== $confirm) { $error = 'Passwords do not match.'; $tab = 'register'; }
    elseif (strlen($password) < 8) { $error = 'Password must be at least 8 characters.'; $tab = 'register'; }
    else {
        // Use PDO to check for existing email
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) { 
            $error = 'Email already registered.'; 
            $tab = 'register'; 
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $phone, $hash])) {
                $uid = $pdo->lastInsertId();
                $_SESSION['user_id'] = $uid;
                $_SESSION['user_name'] = $name;
                $redirect = $_GET['redirect'] ?? (SITE_URL . '/index');
                if(!str_starts_with($redirect, 'http')) $redirect = SITE_URL . '/' . ltrim($redirect, '/');
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login / Register – LuxeStore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .auth-container { width:100%; max-width:460px; padding:2rem 1.25rem; }
    .auth-card { padding:2.5rem; position:relative; overflow:hidden; }
    [data-theme="light"] .auth-card { background:#fff; border:1px solid rgba(108,99,255,0.1); box-shadow:0 15px 50px rgba(0,0,0,0.05); }
    .tab-switcher { display:flex; background:var(--glass); border-radius:50px; padding:4px; margin-bottom:2rem; border:1px solid var(--glass-border); }
    .tab-btn { flex:1; padding:0.65rem; border-radius:50px; border:none; background:none; font-family:var(--font); font-size:0.875rem; font-weight:600; cursor:pointer; transition:var(--transition); color:var(--text-secondary); }
    .tab-btn.active { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff !important; box-shadow:0 4px 15px rgba(108,99,255,0.2); }
    .input-group { position:relative; }
    .input-group .input-icon { position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1.1rem; }
    .input-group .form-control { padding-left:2.85rem; height:3rem; }
    .input-group .toggle-pw { position:absolute; right:1rem; top:50%; transform:translateY(-50%); color:var(--text-muted); cursor:pointer; background:none; border:none; padding:5px; font-size:1.1rem; }
    .divider { display:flex; align-items:center; gap:1rem; margin:1.5rem 0; color:var(--text-muted); font-size:0.8rem; font-weight:600; }
    .divider::before, .divider::after { content:''; flex:1; height:1px; background:var(--border); }
  </style>
</head>
<body>
<div class="auth-container">
  <!-- Logo -->
  <div style="text-align:center; margin-bottom:2rem;">
    <a href="index.php" style="font-size:2rem; font-weight:900; background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; text-decoration:none;">✦ LuxeStore</a>
    <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.25rem;">Your premium shopping destination</p>
  </div>

  <div class="glass-card auth-card">
    <!-- Tabs -->
    <div class="tab-switcher">
      <button class="tab-btn <?= $tab==='login'?'active':'' ?>" onclick="switchAuthTab('login')">Sign In</button>
      <button class="tab-btn <?= $tab==='register'?'active':'' ?>" onclick="switchAuthTab('register')">Create Account</button>
    </div>

    <?php if($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <div id="loginForm" style="display:<?= $tab==='login'?'block':'none' ?>">
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <i class="bi bi-envelope input-icon"></i>
            <input type="email" name="email" class="form-control" placeholder="you@email.com" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-group">
            <i class="bi bi-lock input-icon"></i>
            <input type="password" name="password" class="form-control" id="loginPw" placeholder="Enter password" required>
            <button type="button" class="toggle-pw" onclick="togglePw('loginPw',this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button type="submit" name="login" class="btn-primary" style="width:100%; justify-content:center; padding:0.875rem; font-size:1rem; margin-top:0.5rem;">
          <i class="bi bi-box-arrow-in-right"></i> Sign In to Account
        </button>
      </form>
    </div>

    <!-- Register Form -->
    <div id="registerForm" style="display:<?= $tab==='register'?'block':'none' ?>">
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <div class="input-group">
            <i class="bi bi-person input-icon"></i>
            <input type="text" name="name" class="form-control" placeholder="Your full name" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <i class="bi bi-envelope input-icon"></i>
            <input type="email" name="email" class="form-control" placeholder="you@email.com" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <div class="input-group">
            <i class="bi bi-telephone input-icon"></i>
            <input type="tel" name="phone" class="form-control" placeholder="+91 98765 43210">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-group">
            <i class="bi bi-lock input-icon"></i>
            <input type="password" name="password" class="form-control" id="regPw" placeholder="Min. 8 characters" required>
            <button type="button" class="toggle-pw" onclick="togglePw('regPw',this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <div class="input-group">
            <i class="bi bi-lock-fill input-icon"></i>
            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
          </div>
        </div>
        <button type="submit" name="register" class="btn-primary" style="width:100%; justify-content:center; padding:0.875rem; font-size:1rem; margin-top:0.5rem;">
          <i class="bi bi-person-plus"></i> Create Account
        </button>
      </form>
    </div>

    <div class="divider">OR</div>

    <button onclick="simulateGoogleLogin(event)" class="btn-primary-luxury google-btn-mock" style="width:100%; justify-content:center; padding:0.875rem; font-size:1rem; margin-top:0.5rem; background:#fff; color:#444; border:1px solid #ddd; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48" style="margin-right:10px;">
          <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
          <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
          <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
          <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
        </svg>
        Continue with Google
    </button>

    <p style="text-align:center; margin-top:1.5rem; font-size:0.8rem; color:var(--text-muted);">
      By continuing, you agree to our Terms & Privacy Policy.
    </p>
  </div>

  <p style="text-align:center; margin-top:1.25rem;">
    <a href="<?= SITE_URL ?>/index" style="color:var(--text-muted); font-size:0.85rem; text-decoration:none;"><i class="bi bi-arrow-left"></i> Back to Store</a>
  </p>
</div>

<script>
const savedTheme = localStorage.getItem('luxeTheme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);

function switchAuthTab(tab) {
  document.getElementById('loginForm').style.display = tab === 'login' ? 'block' : 'none';
  document.getElementById('registerForm').style.display = tab === 'register' ? 'block' : 'none';
  document.querySelectorAll('.tab-btn').forEach((b,i) => {
    b.classList.toggle('active', (i===0 && tab==='login') || (i===1 && tab==='register'));
  });
}

function togglePw(id, btn) {
  const input = document.getElementById(id);
  const isText = input.type === 'text';
  input.type = isText ? 'password' : 'text';
  btn.innerHTML = isText ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
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
