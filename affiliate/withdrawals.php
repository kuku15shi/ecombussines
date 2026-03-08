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
    
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if ($amount >= AFFILIATE_MIN_WITHDRAWAL) {
        if ($amount <= $affiliate['balance']) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO affiliate_withdrawals (affiliate_id, amount, payment_method, notes, status) 
                                      VALUES (?, ?, ?, ?, 'pending')");
                $stmt->execute([$affId, $amount, $method, $notes]);
                
                $stmt = $pdo->prepare("UPDATE affiliates SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $affId]);
                
                $pdo->commit();
                $success = 'Withdrawal request submitted successfully!';
                
                // Refresh data
                $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE id = ?");
                $stmt->execute([$affId]);
                $affiliate = $stmt->fetch();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Something went wrong. Please try again.';
            }
        } else {
            $error = 'Insufficient balance.';
        }
    } else {
        $error = 'Minimum withdrawal amount is ' . formatPrice(AFFILIATE_MIN_WITHDRAWAL);
    }
}

$stmt = $pdo->prepare("SELECT * FROM affiliate_withdrawals WHERE affiliate_id = ? ORDER BY created_at DESC");
$stmt->execute([$affId]);
$withdrawals = $stmt->fetchAll();

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Withdrawals - <?= SITE_NAME ?></title>
    <?php include 'includes/head.php'; ?>
    <style>
        .method-check {
            display: none;
        }
        .method-card {
            border: 2px solid #f1f5f9;
            padding: 1.5rem 1rem;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: #f8fafc;
            color: #64748b;
        }
        .method-card:hover {
            border-color: #e2e8f0;
            background: #fff;
        }
        .method-check:checked + .method-card {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
        }
        .method-card i {
            display: block;
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }
        .withdrawal-stats-card {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 style="font-weight: 800; font-size: 2rem;">Payout Center</h1>
                <p class="text-secondary mb-0">Withdraw your earned commissions safely.</p>
            </div>
            <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        </header>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="withdrawal-stats-card mb-4">
                    <div class="small fw-700 text-white-50 mb-2 text-uppercase" style="letter-spacing: 1px;">Available to Withdraw</div>
                    <div class="display-5 fw-900 mb-3"><?= formatPrice($affiliate['balance']) ?></div>
                    <div class="d-flex align-items-center gap-2 small text-white-50">
                        <i class="bi bi-shield-check"></i>
                        <span>Minimum payout is <?= formatPrice(AFFILIATE_MIN_WITHDRAWAL) ?></span>
                    </div>
                </div>

                <div class="glass-card">
                    <h5 class="fw-800 mb-4">Request New Payout</h5>
                    
                    <?php if ($error): ?><div class="alert alert-danger rounded-4 py-3 mb-4"><?= $error ?></div><?php endif; ?>
                    <?php if ($success): ?><div class="alert alert-success rounded-4 py-3 mb-4 text-center fw-600"><?= $success ?></div><?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-700 small text-uppercase text-secondary">Withdrawal Amount</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-light rounded-start-4 px-3" style="font-weight: 800;"><?= CURRENCY ?></span>
                                <input type="number" name="amount" class="form-control border-0 bg-light rounded-end-4 py-3 ps-0" step="0.01" min="<?= AFFILIATE_MIN_WITHDRAWAL ?>" max="<?= $affiliate['balance'] ?>" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-700 small text-uppercase text-secondary d-block">Payout Method</label>
                            <div class="row g-3">
                                <div class="col-4">
                                    <input type="radio" name="method" value="PayPal" id="meth_pp" class="method-check" checked required>
                                    <label for="meth_pp" class="method-card">
                                        <i class="bi bi-paypal"></i>
                                        <span class="small fw-700">PayPal</span>
                                    </label>
                                </div>
                                <div class="col-4">
                                    <input type="radio" name="method" value="Bank Transfer" id="meth_bank" class="method-check" required>
                                    <label for="meth_bank" class="method-card">
                                        <i class="bi bi-bank"></i>
                                        <span class="small fw-700">Bank</span>
                                    </label>
                                </div>
                                <div class="col-4">
                                    <input type="radio" name="method" value="UPI" id="meth_upi" class="method-check" required>
                                    <label for="meth_upi" class="method-card">
                                        <i class="bi bi-phone-vibrate"></i>
                                        <span class="small fw-700">UPI</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-700 small text-uppercase text-secondary">Payment Credentials</label>
                            <textarea name="notes" class="form-control bg-light border-0 rounded-4 p-3" rows="3" required placeholder="Enter your PayPal ID, UPI ID, or Bank Account Details..."><?= htmlspecialchars($affiliate['paypal_email'] ?: $affiliate['bank_details']) ?></textarea>
                        </div>

                        <button type="submit" class="btn-primary-luxury w-100 py-3 rounded-4" <?= $affiliate['balance'] < AFFILIATE_MIN_WITHDRAWAL ? 'disabled' : '' ?>>
                            <i class="bi bi-lightning-charge-fill me-2"></i> Submit Payout Order
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="glass-card overflow-hidden" style="padding: 0; min-height: 100%;">
                    <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center bg-white">
                        <h5 class="fw-800 mb-0">Withdrawal Logs</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary);">
                                    <th class="ps-4">DATE</th>
                                    <th>AMOUNT</th>
                                    <th>METHOD</th>
                                    <th class="pe-4">STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($withdrawals)): ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted">No withdrawal history.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($withdrawals as $w): ?>
                                    <tr>
                                        <td class="ps-4 text-secondary"><?= date('M d, Y', strtotime($w['created_at'])) ?></td>
                                        <td class="fw-800"><?= formatPrice($w['amount']) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-<?= $w['payment_method']=='PayPal'?'paypal':($w['payment_method']=='Bank Transfer'?'bank':'phone-vibrate') ?> text-primary"></i>
                                                <span class="fw-500"><?= $w['payment_method'] ?></span>
                                            </div>
                                        </td>
                                        <td class="pe-4">
                                            <span class="badge rounded-pill bg-<?= $w['status']=='completed'?'success':($w['status']=='pending'?'warning':'danger') ?> px-3 py-2">
                                                <?= ucfirst($w['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
