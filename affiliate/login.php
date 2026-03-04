<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/AffiliateClass.php';
require_once __DIR__ . '/../config/affiliate_auth.php';

if (isAffiliateLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$affSystem = new AffiliateSystem($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $result = $affSystem->login($email, $password);
        if (is_array($result)) {
            $_SESSION['affiliate_id'] = $result['id'];
            $_SESSION['affiliate_name'] = $result['name'];
            header('Location: dashboard.php');
            exit;
        } elseif ($result === 'pending_approval') {
            $error = 'Your account is pending review. You will be notified once approved.';
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    } else {
        $error = 'All fields are required.';
    }
}
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Login - <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Outfit', sans-serif; 
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.05), transparent),
                        radial-gradient(circle at bottom left, rgba(235, 134, 18, 0.05), transparent),
                        #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .auth-card {
            background: rgba(255, 255, 255, 0.84);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
            padding: 3rem;
            width: 100%;
            max-width: 480px;
            margin: 2rem auto;
        }
        .form-label {
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }
        .form-control {
            border: 1.5px solid #e2e8f0;
            padding: 0.85rem 1.25rem;
            border-radius: 14px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .btn-luxury {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1.05rem;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
            transition: all 0.3s ease;
        }
        .btn-luxury:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.2);
            filter: brightness(1.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-card">
            <div class="text-center mb-5">
                <div class="mb-4">
                    <span class="p-3 rounded-circle bg-primary bg-opacity-10 d-inline-block">
                        <i class="bi bi-rocket-takeoff-fill text-primary" style="font-size: 1.5rem;"></i>
                    </span>
                </div>
                <h2 class="fw-900 mb-1">Partner Portal</h2>
                <p class="text-secondary small">Access your account and track performance.</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-4 px-4 py-3 mb-4 small fw-600 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="mb-4">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="partner@example.com" required>
                </div>
                
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <label class="form-label">Security Key</label>
                        <a href="#" class="text-primary x-small fw-700 text-decoration-none" style="font-size: 0.75rem;">FORGOT?</a>
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-luxury w-100 mb-4">
                    Authorize Login <i class="bi bi-arrow-right-short ms-1"></i>
                </button>

                <div class="position-relative text-center mb-4">
                    <hr class="opacity-10">
                    <span class="position-absolute translate-middle px-3 bg-white text-secondary x-small fw-700" style="top: 0; font-size: 0.75rem;">ONE CLICK AUTH</span>
                </div>

                <button type="button" onclick="simulateGoogleLogin(event)" class="btn w-100 py-3 rounded-4 d-flex align-items-center justify-content-center gap-2 border shadow-sm bg-white" style="font-weight:600; transition:0.3s; color:#1e293b; border-color: #e2e8f0 !important;">
                    <img src="https://cdn-icons-png.flaticon.com/512/2991/2991148.png" width="20" alt="google">
                    Continue with Google
                </button>
            </form>

            <div class="text-center mt-5">
                <p class="text-secondary small">Not a partner yet? <a href="register.php" class="text-primary fw-800 text-decoration-none">Apply Now</a></p>
                <a href="../index.php" class="text-muted x-small fw-600 text-decoration-none mt-3 d-inline-block"><i class="bi bi-house-door"></i> Back to Storefront</a>
            </div>
        </div>
    </div>

    <script>
        function simulateGoogleLogin(e) {
            const btn = e.currentTarget;
            btn.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Redirection...';
            btn.disabled = true;
            setTimeout(() => {
                window.location.href = 'auth-google.php';
            }, 800);
        }
    </script>
</body>
</html>
