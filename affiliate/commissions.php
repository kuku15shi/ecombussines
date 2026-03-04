<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/AffiliateClass.php';
require_once __DIR__ . '/../config/affiliate_auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAffiliateLogin();
$affId = $_SESSION['affiliate_id'];

$stmt = $pdo->prepare("SELECT c.*, o.order_number 
                       FROM affiliate_commissions c 
                       JOIN orders o ON c.order_id = o.id 
                       WHERE c.affiliate_id = ? 
                       ORDER BY c.created_at DESC");
$stmt->execute([$affId]);
$commissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Commissions - <?= SITE_NAME ?></title>
    <?php include 'includes/head.php'; ?>
    <style>
        .table thead th {
            background: rgba(0,0,0,0.02);
            color: #13f34bff;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            border: none;
            padding: 1.25rem 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 style="font-weight: 800; font-size: 2rem;">Earnings History</h1>
                <p class="text-secondary mb-0">Detailed list of all commissions earned via your referrals.</p>
            </div>
            <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        </header>

        <div class="glass-card overflow-hidden" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">ORDER NUMBER</th>
                            <th>DATE</th>
                            <th>ORDER TOTAL</th>
                            <th>COMMISSION</th>
                            <th>STATUS</th>
                            <th class="pe-4">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($commissions)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No commissions recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($commissions as $row): ?>
                            <tr>
                                <td class="ps-4 fw-800">#<?= $row['order_number'] ?></td>
                                <td class="text-secondary"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td class="fw-500"><?= formatPrice($row['order_amount']) ?></td>
                                <td class="fw-800 text-success">+<?= formatPrice($row['commission_amount']) ?></td>
                                <td>
                                    <span class="badge rounded-pill bg-<?= $row['status']==='paid'?'success':($row['status']==='verified'?'info':'warning') ?> px-3 py-2">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                                <td class="pe-4">
                                    <button class="btn btn-sm btn-outline-secondary rounded-3" title="View Details"><i class="bi bi-eye-fill"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
