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
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 4rem 3rem;
            border-radius: 20px 20px 0 0;
            color: white;
            position: relative;
        }
        .avatar-box {
            width: 90px;
            height: 90px;
            border-radius: 50% !important;
            border: 4px solid rgba(255,255,255,0.2);
            object-fit: cover;
            background: rgba(255,255,255,0.1);
        }
        .settings-card {
            border-radius: 0 0 20px 20px !important;
            padding: 3rem !important;
            border-top: none !important;
        }
        .section-title-custom {
            font-size: 0.9rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .form-control {
            border: 1px solid #e2e8f0;
            padding: 1rem;
            border-radius: 12px;
            background: #f8fafc;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 style="font-weight: 800; font-size: 2rem;">Security & Profile</h1>
                <p class="text-secondary mb-0">Manage your identity and payout destinations.</p>
            </div>
            <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        </header>

        <div class="container-fluid px-0">
            <div class="profile-header shadow-lg">
                <div class="d-flex align-items-center gap-4">
                    <img src="<?= $affiliate['avatar'] ?: 'https://ui-avatars.com/api/?name='.urlencode($affiliate['name']).'&background=fff&color=6366f1' ?>" class="avatar-box">
                    <div>
                        <h2 class="fw-900 mb-1"><?= $affiliate['name'] ?></h2>
                        <div class="d-flex align-items-center gap-2 text-white-50">
                            <i class="bi bi-envelope"></i>
                            <span><?= $affiliate['email'] ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card settings-card">
                <?php if ($error): ?><div class="alert alert-danger rounded-4 py-3 mb-4 mx-2"><?= $error ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success rounded-4 py-3 mb-4 mx-2 text-center fw-600"><?= $success ?></div><?php endif; ?>

                <form method="POST" class="px-2">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="section-title-custom">
                                <i class="bi bi-person-fill"></i> Basic Account Information
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">DISPLAY NAME</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($affiliate['name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">PHONE NUMBER</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($affiliate['phone']) ?>" placeholder="e.g. +91 98765 43210">
                        </div>

                        <div class="col-12 mt-5">
                            <div class="section-title-custom">
                                <i class="bi bi-credit-card-2-front-fill"></i> Payout Configuration
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">PAYPAL EMAIL (OPTIONAL)</label>
                            <input type="email" name="paypal_email" class="form-control" value="<?= htmlspecialchars($affiliate['paypal_email']) ?>" placeholder="paypal@example.com">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label small fw-bold text-secondary">BANK INFO / UPI ID</label>
                            <textarea name="bank_details" class="form-control" rows="3" placeholder="Enter full Bank Details (Account No, IFSC) or UPI ID for settlements..."><?= htmlspecialchars($affiliate['bank_details']) ?></textarea>
                        </div>

                        <div class="col-12 mt-5">
                            <div class="section-title-custom">
                                <i class="bi bi-shield-lock-fill"></i> Security Update
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">CHANGE PASSWORD</label>
                            <input type="password" name="password" class="form-control" placeholder="Entrez un nouveau mot de passe pour changer">
                            <small class="text-secondary opacity-75 mt-2 d-block">Leave blank to keep your current password.</small>
                        </div>
                    </div>

                    <div class="mt-5 text-end">
                        <button type="submit" class="btn-primary-luxury px-5 py-3 rounded-4">
                            <i class="bi bi-check2-circle me-2"></i> Update My Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
