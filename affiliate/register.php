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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $data = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'phone' => $_POST['phone'] ?? ''
    ];

    if ($data['name'] && $data['email'] && $data['password']) {
        try {
            if ($affSystem->register($data)) {
                $success = 'Application submitted! We will review your profile shortly.';
            } else {
                $error = 'Email already registered. Try logging in.';
            }
        } catch (Exception $e) {
            $error = 'Could not process registration. Try again.';
        }
    } else {
        $error = 'All required fields must be filled.';
    }
}
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Application - <?= SITE_NAME ?></title>
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
            max-width: 520px;
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
                    <span class="p-4 rounded-circle bg-primary bg-opacity-10 d-inline-block">
                        <i class="bi bi-person-plus-fill text-primary" style="font-size: 2rem;"></i>
                    </span>
                </div>
                <h2 class="fw-900 mb-1">Partner Registration</h2>
                <p class="text-secondary small">Start your journey and earn up to <?= AFFILIATE_COMMISSION_PERCENT ?>% in rewards.</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-4 px-4 py-3 mb-4 small fw-600 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success border-0 rounded-4 px-4 py-5 mb-4 text-center">
                <div style="font-size: 2.5rem;" class="mb-3">✨</div>
                <h5 class="fw-800 mb-2">Application Received</h5>
                <p class="small text-secondary mb-4"><?= $success ?></p>
                <a href="login.php" class="btn btn-luxury w-100">Continue to Login</a>
            </div>
            <?php else: ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Company / Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="John Doe or LLC" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Official Email</label>
                        <input type="email" name="email" class="form-control" placeholder="name@domain.com" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phone Reference</label>
                        <input type="tel" name="phone" class="form-control" placeholder="+91 ...">
                    </div>
                    
                    <div class="col-12 mb-4">
                        <label class="form-label">Access Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        <small class="text-secondary x-small mt-2 px-1">Ensure it's at least 8 characters for security.</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-luxury w-100 mb-4">
                    Submit Application <i class="bi bi-send-fill ms-2"></i>
                </button>

                <div class="position-relative text-center mb-4">
                    <hr class="opacity-10">
                    <span class="position-absolute translate-middle px-3 bg-white text-secondary x-small fw-700" style="top: 0; font-size: 0.75rem;">OR QUICK SIGNUP</span>
                </div>

                <button type="button" onclick="simulateGoogleLogin(event)" class="btn w-100 py-3 rounded-4 d-flex align-items-center justify-content-center gap-2 border shadow-sm bg-white" style="font-weight:600; transition:0.3s; color:#1e293b; border-color: #e2e8f0 !important;">
                    <img src="https://cdn-icons-png.flaticon.com/512/2991/2991148.png" width="20" alt="google">
                    Apply with Google
                </button>
            </form>

            <div class="text-center mt-5">
                <p class="text-secondary small">Already a part of us? <a href="login.php" class="text-primary fw-800 text-decoration-none">Login Portal</a></p>
                <a href="../index.php" class="text-muted x-small fw-600 text-decoration-none mt-3 d-inline-block"><i class="bi bi-house-door"></i> Back to Storefront</a>
            </div>
            <?php endif; ?>
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
