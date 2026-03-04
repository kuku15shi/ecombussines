<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Manage Users';

if(isset($_GET['toggle'])){ 
    // Ideally, this should be a POST request with a CSRF token.
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE users SET is_blocked = NOT is_blocked WHERE id = ?")->execute([$id]); 
    header('Location: users.php'); exit; 
}

if(isset($_GET['delete'])){ 
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]); 
    header('Location: users.php?success=1'); exit; 
}

$search = $_GET['search'] ?? '';
$where = "WHERE 1=1";
$params = [];
if($search) {
    $where .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id=u.id) as order_count, (SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=u.id AND status!='cancelled') as total_spent FROM users u $where ORDER BY u.created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Users – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
</head>
<body>
<div class="admin-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="content-area">
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Done!</div><?php endif; ?>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:0.75rem;">
        <h2 style="font-weight:800; font-size:1.3rem;">Users <span style="font-size:1rem; color:var(--text-muted); font-weight:500;">(<?= count($users) ?>)</span></h2>
      </div>
      <!-- Search -->
      <div class="data-table-card" style="margin-bottom:1.25rem;">
        <div style="padding:1rem 1.25rem;">
          <form method="GET" style="display:flex; gap:0.75rem;">
            <input type="text" name="search" class="filter-input" placeholder="🔍 Search users..." value="<?= htmlspecialchars($search) ?>" style="flex:1;">
            <button type="submit" class="btn-primary btn-sm"><i class="bi bi-search"></i></button>
          </form>
        </div>
      </div>
      <div class="data-table-card">
        <div class="table-responsive">
        <table class="admin-table">
          <thead><tr><th>User</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($users as $u): ?>
            <tr>
              <td>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                  <div style="width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--secondary)); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.85rem; flex-shrink:0;"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                  <div>
                    <div style="font-weight:700; font-size:0.875rem;"><?= htmlspecialchars($u['name']) ?></div>
                    <div style="font-size:0.72rem; color:var(--text-muted);"><?= $u['email'] ?></div>
                  </div>
                </div>
              </td>
              <td><?= htmlspecialchars($u['phone'] ?? '–') ?></td>
              <td><span class="badge badge-processing"><?= $u['order_count'] ?></span></td>
              <td style="font-weight:700;"><?= formatPrice($u['total_spent']) ?></td>
              <td style="font-size:0.8rem;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td><span class="badge <?= $u['is_blocked']?'badge-cancelled':'badge-active' ?>"><?= $u['is_blocked']?'Blocked':'Active' ?></span></td>
              <td>
                <div style="display:flex; gap:0.4rem;">
                  <a href="users.php?toggle=<?= $u['id'] ?>" class="btn-icon <?= $u['is_blocked']?'btn-edit':'btn-delete' ?>" title="<?= $u['is_blocked']?'Unblock':'Block' ?>"><i class="bi bi-<?= $u['is_blocked']?'unlock':'lock' ?>"></i></a>
                  <a href="users.php?delete=<?= $u['id'] ?>" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Delete user?')"><i class="bi bi-trash"></i></a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($users)): ?><tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No users found</td></tr><?php endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
