<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireAdminLogin();

$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    
    $pdo->beginTransaction();
    try {
        // Fetch withdrawal details
        $stmt = $pdo->prepare("SELECT * FROM affiliate_withdrawals WHERE id = ?");
        $stmt->execute([$id]);
        $withdrawal = $stmt->fetch();
        
        if (!$withdrawal) {
            throw new Exception("Withdrawal request not found.");
        }
        
        if ($withdrawal['status'] !== 'pending') {
            throw new Exception("This withdrawal has already been processed.");
        }

        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE affiliate_withdrawals SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $success_msg = "Withdrawal marked as completed successfully.";
        } elseif ($action === 'reject') {
            // Set status to rejected
            $stmt = $pdo->prepare("UPDATE affiliate_withdrawals SET status = 'rejected', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            // Refund the amount to affiliate balance
            $stmt = $pdo->prepare("UPDATE affiliates SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$withdrawal['amount'], $withdrawal['affiliate_id']]);
            $success_msg = "Withdrawal rejected and amount refunded to affiliate.";
        } else {
            throw new Exception("Invalid action.");
        }
        
        $pdo->commit();
        header("Location: withdrawals.php?msg=" . urlencode($success_msg) . ($status ? "&status=$status" : ""));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: withdrawals.php?error=" . urlencode($e->getMessage()) . ($status ? "&status=$status" : ""));
        exit;
    }
}

$sql = "SELECT w.*, a.name as affiliate_name, a.email as affiliate_email 
        FROM affiliate_withdrawals w 
        JOIN affiliates a ON w.affiliate_id = a.id " . 
        ($status ? "WHERE w.status = :status " : "") . 
        "ORDER BY w.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($status) $stmt->execute(['status' => $status]);
else $stmt->execute();
$withdrawals = $stmt->fetchAll();

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Withdrawals - Admin Panel</title>
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
                <div>
                    <h1 style="font-weight: 800; margin-bottom: 0.25rem;">Withdrawal Requests</h1>
                    <p class="text-muted small mb-0">Manage and process affiliate payout requests.</p>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <a href="withdrawals.php" class="btn-primary btn-sm <?= !$status?'':'btn-outline' ?>" style="text-decoration:none;">All</a>
                    <a href="withdrawals.php?status=pending" class="btn-primary btn-sm <?= $status==='pending'?'':'btn-outline' ?>" style="text-decoration:none;">Pending</a>
                    <a href="withdrawals.php?status=completed" class="btn-primary btn-sm <?= $status==='completed'?'':'btn-outline' ?>" style="text-decoration:none;">Completed</a>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="data-table-card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Affiliate</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Details</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawals as $w): ?>
                            <tr>
                                <td>
                                    <div class="fw-700"><?= htmlspecialchars($w['affiliate_name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($w['affiliate_email']) ?></div>
                                </td>
                                <td class="fw-800" style="color:var(--primary);"><?= formatPrice($w['amount']) ?></td>
                                <td>
                                    <span class="badge" style="background:rgba(99, 102, 241, 0.1); color:var(--primary); font-weight:600; border:none;">
                                        <?= htmlspecialchars($w['payment_method']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-muted small" style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($w['notes']) ?>">
                                        <?= htmlspecialchars($w['notes']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small"><?= date('M d, Y', strtotime($w['created_at'])) ?></div>
                                    <div class="text-muted" style="font-size:0.7rem;"><?= date('h:i A', strtotime($w['created_at'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $w['status']==='completed'?'delivered':($w['status']==='pending'?'pending':'cancelled') ?>">
                                        <?= ucfirst($w['status'] === 'completed' ? 'paid' : $w['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($w['status'] === 'pending'): ?>
                                    <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this withdrawal as completed?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn-icon" style="background:#22c55e; color:white; border:none; border-radius:8px; width:32px; height:32px;" title="Approve & Complete">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this withdrawal? The amount will be refunded to the affiliate.');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                            <button type="submit" name="action" value="reject" class="btn-icon" style="background:#ef4444; color:white; border:none; border-radius:8px; width:32px; height:32px;" title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                        <div class="text-muted small">Processed on <?= date('M d', strtotime($w['completed_at'])) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($withdrawals)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:4rem; color:var(--text-muted);">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No withdrawal requests found.
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }
</script>
</body>
</html>
