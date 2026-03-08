<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/AffiliateClass.php';
require_once __DIR__ . '/../config/affiliate_auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAffiliateLogin();
$affId = $_SESSION['affiliate_id'];

// Get fresh data
$stmt = $pdo->prepare("SELECT * FROM affiliates WHERE id = ?");
$stmt->execute([$affId]);
$affiliate = $stmt->fetch();

if (!$affiliate) {
    header('Location: logout.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $paypal = $_POST['paypal_email'] ?? '';
    $bank = $_POST['bank_details'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE affiliates SET name = ?, phone = ?, paypal_email = ?, bank_details = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $paypal, $bank, $hashed, $affId]);
        } else {
            $stmt = $pdo->prepare("UPDATE affiliates SET name = ?, phone = ?, paypal_email = ?, bank_details = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $paypal, $bank, $affId]);
        }
        
        $success = 'Your profile has been updated successfully!';
        // Refresh local data
        $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE id = ?");
        $stmt->execute([$affId]);
        $affiliate = $stmt->fetch();
        $_SESSION['affiliate_name'] = $affiliate['name'];
    } catch (Exception $e) {
        $error = 'Update failed. Please check your data.';
    }
}
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Account Settings - <?= SITE_NAME ?></title>
    <?php include 'includes/head.php'; ?>
    <style>
        .profile-header-banner {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 3rem;
            border-radius: 24px;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px -10px rgba(99, 102, 241, 0.4);
        }
        .profile-header-banner::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        .avatar-lg {
            width: 100px;
            height: 100px;
            border-radius: 30px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
            background: white;
        }
        .form-label-custom {
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
        }
        .input-group-custom {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            transition: all 0.2s ease;
        }
        .input-group-custom:focus-within {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .input-group-custom .form-control {
            background: transparent;
            border: none;
            padding: 1rem;
            box-shadow: none;
        }
        .input-group-custom .input-group-text {
            background: transparent;
            border: none;
            padding-left: 1.25rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 style="font-weight: 800; font-size: 2.25rem; letter-spacing: -1px; color: #1e293b;">Security & Profile</h1>
                <p class="text-secondary mb-0 fw-500 text-truncate">Manage your digital identity and payout destinations.</p>
            </div>
            <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-danger rounded-4 py-3 mb-4 px-4 d-flex align-items-center gap-3 border-0 transition-all">
                <i class="bi bi-exclamation-octagon-fill fs-4"></i>
                <span class="fw-600"><?= $error ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success rounded-4 py-3 mb-4 px-4 d-flex align-items-center gap-3 border-0 transition-all shadow-sm">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                <span class="fw-600 text-success"><?= $success ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="profile-header-banner">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($affiliate['name']) ?>&background=fff&color=6366f1&size=200" class="avatar-lg shadow-sm">
                    <div class="flex-grow-1">
                        <div class="badge bg-white bg-opacity-20 rounded-pill px-3 py-2 mb-2" style="font-size: 0.7rem; font-weight: 800; letter-spacing: 0.5px;">VERIFIED PARTNER</div>
                        <h2 class="fw-900 mb-1" style="font-size: 1.75rem;"><?= htmlspecialchars($affiliate['name']) ?></h2>
                        <div class="d-flex align-items-center gap-2 text-white-50 small">
                            <i class="bi bi-envelope-at-fill"></i>
                            <span><?= htmlspecialchars($affiliate['email']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="glass-card mb-4 border-0 shadow-sm">
                        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                            <div class="icon-box mb-0" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; width: 44px; height: 44px; font-size: 1.4rem;">
                                <i class="bi bi-person-bounding-box"></i>
                            </div>
                            <h5 class="fw-800 mb-0">General Information</h5>
                        </div>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label-custom">Display Name</label>
                                <div class="input-group-custom">
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($affiliate['name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Contact Number</label>
                                <div class="input-group-custom">
                                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($affiliate['phone']) ?>" placeholder="+1 (555) 000-0000">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label-custom">Account Email (Permanent)</label>
                                <div class="input-group-custom bg-light opacity-75">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($affiliate['email']) ?>" readonly>
                                </div>
                                <p class="text-muted small mt-2 mb-0 px-2"><i class="bi bi-info-circle me-1"></i> For security, email changes require manual administrative review.</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card border-0 shadow-sm">
                        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                            <div class="icon-box mb-0" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; width: 44px; height: 44px; font-size: 1.4rem;">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <h5 class="fw-800 mb-0">Payout Destinations</h5>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label-custom">PayPal Wallet Address</label>
                            <div class="input-group-custom">
                                <span class="input-group-text"><i class="bi bi-paypal text-primary"></i></span>
                                <input type="email" name="paypal_email" class="form-control" placeholder="your-paypal@example.com" value="<?= htmlspecialchars($affiliate['paypal_email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label-custom">Direct Payout (Bank / UPI / Wire)</label>
                            <textarea name="bank_details" class="form-control" rows="4" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem;" placeholder="Bank Name:&#10;Account Number:&#10;IFSC Code / SWIFT:&#10;UPI ID:"><?= htmlspecialchars($affiliate['bank_details'] ?? '') ?></textarea>
                            <p class="text-muted small mt-2 mb-0 px-2"><i class="bi bi-shield-check me-1"></i> Carefully verify your details to ensure lightning-fast payouts.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="glass-card h-100 border-0 shadow-sm">
                        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                            <div class="icon-box mb-0" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; width: 44px; height: 44px; font-size: 1.4rem;">
                                <i class="bi bi-key-fill"></i>
                            </div>
                            <h5 class="fw-800 mb-0">Authentication</h5>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label-custom">New Private Password</label>
                            <div class="input-group-custom">
                                <input type="password" name="password" class="form-control" placeholder="Min 8 characters ••••••••">
                            </div>
                        </div>
                        <div class="mb-5">
                            <label class="form-label-custom">Confirm New Password</label>
                            <div class="input-group-custom">
                                <input type="password" class="form-control" placeholder="Match new password ••••••••">
                            </div>
                            <p class="text-secondary small mt-3 px-1"><i class="bi bi-shield-lock me-1"></i> Leave both fields empty if you don't wish to rotate your credentials.</p>
                        </div>

                        <button type="submit" class="btn btn-primary-luxury w-100 py-3 rounded-4 shadow-lg mt-auto">
                            <i class="bi bi-check-all fs-5 me-2"></i> Save All Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
