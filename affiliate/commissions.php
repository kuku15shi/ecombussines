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
            background: #f8fafc;
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            border: none;
            padding: 1.5rem 1rem;
        }
        .table tbody td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 style="font-weight: 800; font-size: 2.25rem; letter-spacing: -1px; color: #1e293b;">Earnings History</h1>
                <p class="text-secondary mb-0 fw-500">Detailed overview of all commissions generated via your unique referrals.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary rounded-4 px-3 d-none d-md-block" disabled><i class="bi bi-download me-2"></i> Export Data</button>
                <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
            </div>
        </header>

        <div class="glass-card overflow-hidden" style="padding: 0; border-radius: 24px;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th class="ps-4 border-0">TRANSACTION</th>
                            <th class="border-0">DATE</th>
                            <th class="border-0">SALE VALUE</th>
                            <th class="border-0">YOUR EARNING</th>
                            <th class="border-0">STATUS</th>
                            <th class="pe-4 border-0 text-end">DETAILS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($commissions)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="mb-3"><i class="bi bi-wallet2 text-muted fs-1 opacity-25"></i></div>
                                    <h5 class="fw-800 text-secondary">No earnings recorded yet</h5>
                                    <p class="text-muted small">Once you start sharing links, your sales will appear here.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($commissions as $row): ?>
                            <tr class="transition-all hover-bg-light">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary">
                                            <i class="bi bi-bag-check-fill"></i>
                                        </div>
                                        <div class="fw-800 text-main">Order #<?= $row['order_number'] ?></div>
                                    </div>
                                </td>
                                <td class="text-secondary fw-500 small"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td class="fw-600"><?= formatPrice($row['order_amount']) ?></td>
                                <td class="fw-900 text-success" style="font-size: 1.05rem;">+<?= formatPrice($row['commission_amount']) ?></td>
                                <td>
                                    <?php 
                                        $badgeClass = match($row['status']) {
                                            'paid' => 'bg-success',
                                            'verified' => 'bg-info',
                                            'pending' => 'bg-warning text-dark',
                                            'rejected' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                    ?>
                                    <span class="badge rounded-pill <?= $badgeClass ?> px-3 py-2 shadow-sm" style="font-size: 0.75rem; letter-spacing: 0.3px;">
                                        <?= $row['status'] === 'verified' ? 'APPROVED' : strtoupper($row['status']) ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-light btn-sm rounded-circle border shadow-sm" style="width: 32px; height: 32px;" title="View Order Details"><i class="bi bi-chevron-right text-primary x-small"></i></button>
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
