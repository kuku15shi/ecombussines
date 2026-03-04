<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    
    $stmt = $pdo->prepare("SELECT * FROM affiliate_commissions WHERE id = ?");
    $stmt->execute([$id]);
    $comm = $stmt->fetch();
    
    if ($comm) {
        $affId = $comm['affiliate_id'];
        $amount = $comm['commission_amount'];
        
        if ($action === 'verify' && $comm['status'] === 'pending') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE affiliate_commissions SET status = 'verified' WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE affiliates SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?")->execute([$amount, $amount, $affId]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        } elseif ($action === 'pay' && $comm['status'] === 'verified') {
            $pdo->prepare("UPDATE affiliate_commissions SET status = 'paid' WHERE id = ?")->execute([$id]);
        } elseif ($action === 'cancel') {
            $pdo->prepare("UPDATE affiliate_commissions SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        }
    }
    header("Location: commissions.php");
    exit;
}

$stmt = $pdo->query("SELECT c.*, a.name as aff_name, o.order_number 
                    FROM affiliate_commissions c 
                    JOIN affiliates a ON c.affiliate_id = a.id 
                    JOIN orders o ON c.order_id = o.id 
                    ORDER BY c.created_at DESC");
$commissions = $stmt->fetchAll();

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Commissions - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 style="font-weight: 800; margin:0;">Affiliate Commissions</h1>
                <a href="export_commissions.php" class="btn-primary btn-sm"><i class="bi bi-download"></i> Export CSV</a>
            </div>

            <div class="data-table-card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Affiliate</th>
                                <th>Amount</th>
                                <th>Commission</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commissions as $c): ?>
                            <tr>
                                <td style="font-weight:600;"><span class="text-primary">#<?= $c['order_number'] ?></span></td>
                                <td><?= htmlspecialchars($c['aff_name']) ?></td>
                                <td style="font-weight:600;"><?= formatPrice($c['order_amount']) ?></td>
                                <td class="fw-bold text-success"><?= formatPrice($c['commission_amount']) ?></td>
                                <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $c['status'] ?>">
                                        <?= ucfirst($c['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.5rem;">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <?php if ($c['status'] === 'pending'): ?>
                                            <button type="submit" name="action" value="verify" class="btn-primary btn-sm" style="background:var(--success); border-color:var(--success);">Verify</button>
                                        <?php elseif ($c['status'] === 'verified'): ?>
                                            <button type="submit" name="action" value="pay" class="btn-primary btn-sm">Mark Paid</button>
                                        <?php endif; ?>
                                        <?php if ($c['status'] !== 'paid' && $c['status'] !== 'cancelled'): ?>
                                            <button type="submit" name="action" value="cancel" class="btn-primary btn-sm btn-outline" style="border-color:var(--danger); color:var(--danger);">Cancel</button>
                                        <?php endif; ?>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($commissions)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No commissions recorded yet.</td></tr>
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
