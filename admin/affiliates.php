<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireAdminLogin();

$status = $_GET['status'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $pdo->prepare("UPDATE affiliates SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    
    header("Location: affiliates.php");
    exit;
}

$sql = "SELECT * FROM affiliates " . ($status ? "WHERE status = :status " : "") . "ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
if ($status) $stmt->execute(['status' => $status]);
else $stmt->execute();
$affiliates = $stmt->fetchAll();

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Affiliates - Admin Table</title>
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
                <h1 style="font-weight: 800;">Affiliates</h1>
                <div style="display:flex; gap:0.5rem;">
                    <a href="affiliates.php" class="btn-primary btn-sm <?= !$status?'':'btn-outline' ?>" style="text-decoration:none;">All</a>
                    <a href="affiliates.php?status=pending" class="btn-primary btn-sm <?= $status==='pending'?'':'btn-outline' ?>" style="text-decoration:none;">Pending</a>
                    <a href="affiliates.php?status=approved" class="btn-primary btn-sm <?= $status==='approved'?'':'btn-outline' ?>" style="text-decoration:none;">Approved</a>
                </div>
            </div>

            <div class="data-table-card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Ref Code</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($affiliates as $aff): ?>
                            <tr>
                                <td style="font-weight:600;"><?= htmlspecialchars($aff['name']) ?></td>
                                <td><?= htmlspecialchars($aff['email']) ?></td>
                                <td><span class="badge badge-processing"><?= $aff['referral_code'] ?></span></td>
                                <td style="font-weight:700;"><?= formatPrice($aff['balance']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $aff['status'] ?>">
                                        <?= ucfirst($aff['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.5rem;">
                                    <?php if ($aff['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="id" value="<?= $aff['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn-primary btn-sm" style="background:var(--success); border-color:var(--success);" title="Approve"><i class="bi bi-check-lg"></i></button>
                                            <button type="submit" name="action" value="reject" class="btn-primary btn-sm" style="background:var(--danger); border-color:var(--danger);" title="Reject"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($affiliates)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);">No affiliates found.</td></tr>
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
