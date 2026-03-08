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
<html lang="en" data-theme="light">
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
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #1e293b;
        }
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            padding: 3.5rem;
            width: 100%;
            max-width: 540px;
            transition: transform 0.3s ease;
        }
        .auth-card:hover { transform: translateY(-5px); }
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border: 1.5px solid #e2e8f0;
            padding: 0.8rem 1.25rem;
            border-radius: 12px;
            font-size: 1rem;
            background: #f8fafc;
            color: #1e293b;
            transition: all 0.2s;
        }
        .form-control:focus {
            background: #fff;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .btn-luxury {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
            transition: all 0.3s;
        }
        .btn-luxury:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3);
            filter: brightness(1.05);
            color: white;
        }
        .google-btn {
            background: white;
            border: 1.5px solid #e2e8f0;
            color: #1e293b;
            padding: 0.8rem;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .google-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            color: #1e293b;
        }
        .separator {
            display: flex;
            align-items: center;
            text-align: center;
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 2rem 0;
        }
        .separator::before, .separator::after {
            content: '';
            flex: 1;
            border-bottom: 1.5px solid #f1f5f9;
        }
        .separator:not(:empty)::before { margin-right: 1.5rem; }
        .separator:not(:empty)::after { margin-left: 1.5rem; }
        
        .icon-badge {
            width: 64px;
            height: 64px;
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="text-center mb-5">
            <div class="icon-badge">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <h2 class="fw-800 mb-1" style="color: #1e293b;">Partner Application</h2>
            <p class="text-secondary small">Start your journey and earn up to <?= AFFILIATE_COMMISSION_PERCENT ?>% in rewards</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-4 px-4 py-3 mb-4 small fw-600 d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success border-0 rounded-4 px-4 py-5 mb-4 text-center">
            <div style="font-size: 2.5rem;" class="mb-3">✨</div>
            <h5 class="fw-800 mb-2" style="color: #15803d;">Application Received</h5>
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

            <button type="submit" class="btn btn-luxury w-100 mb-3">
                Submit Application <i class="bi bi-send-fill ms-2"></i>
            </button>

            <div class="separator">Quick Signup</div>

            <a href="auth-google.php" class="google-btn w-100">
                <img src="https://cdn-icons-png.flaticon.com/512/2991/2991148.png" width="20" alt="google">
                Apply with Google
            </a>
        </form>

        <div class="text-center mt-5">
            <p class="text-secondary small mb-3">Already a part of us? <a href="login.php" class="text-primary fw-800 text-decoration-none">Login Portal</a></p>
            <a href="../index.php" class="text-muted small fw-600 text-decoration-none"><i class="bi bi-house-door me-1"></i> Back to Storefront</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
