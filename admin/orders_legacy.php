<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Manage Orders';

// Update Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    validateCsrf();
    $id = (int)$_POST['order_id'];
    $status = $_POST['status'] ?? '';
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (in_array($status, $allowed)) {
        $pdo->prepare("UPDATE orders SET order_status=? WHERE id=?")->execute([$status, $id]);
    }
    header('Location: orders.php?success=1'); exit;
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (o.order_number LIKE ? OR o.name LIKE ? OR o.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter) {
    $where .= " AND o.order_status=?";
    $params[] = $statusFilter;
}

$total = $pdo->prepare("SELECT COUNT(*) as c FROM orders o $where");
$total->execute($params);
$total = $total->fetch()['c'];

$pages = ceil($total / $perPage);
$offset = ($page-1) * $perPage;

$sql = "SELECT o.*, u.name as user_name, (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) as item_count FROM orders o LEFT JOIN users u ON o.user_id=u.id $where ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statuses = ['pending','processing','shipped','delivered','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders – LuxeStore Admin</title>
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
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Order status updated!</div><?php endif; ?>

      <!-- Status Tabs -->
      <div style="display:flex; gap:0.6rem; overflow-x:auto; padding:0.4rem; white-space:nowrap; margin-bottom:1.5rem; -webkit-overflow-scrolling: touch; scrollbar-width: none;">
        <style>.status-tab { padding: 0.6rem 1.25rem; font-size: 0.85rem; font-weight: 600; border-radius: 12px; text-decoration: none; transition: var(--transition); border: 1px solid var(--border); background: var(--glass); color: var(--text-muted); } .status-tab.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; border-color: transparent; box-shadow: 0 4px 15px rgba(108,99,255,0.3); } .status-tab:hover:not(.active) { background: var(--card-bg); color: var(--text-primary); border-color: var(--primary); } .count-pill { font-size: 0.7rem; background: rgba(0,0,0,0.1); padding: 0.1rem 0.4rem; border-radius: 50px; margin-left: 0.4rem; }</style>
        <a href="orders.php" class="status-tab <?= !$statusFilter?'active':'' ?>">All <span class="count-pill"><?= $total ?></span></a>
        <?php foreach($statuses as $s):
          $cntStmt = $pdo->prepare("SELECT COUNT(*) as c FROM orders WHERE order_status=?");
          $cntStmt->execute([$s]);
          $cnt = $cntStmt->fetch()['c'];
          $badgeMap = ['pending'=>'var(--warning)', 'processing'=>'var(--info)', 'shipped'=>'var(--primary)', 'delivered'=>'var(--success)', 'cancelled'=>'var(--danger)'];
        ?>
        <a href="orders.php?status=<?= $s ?>" class="status-tab <?= $statusFilter===$s?'active':'' ?>">
          <?= ucfirst($s) ?> <small style="opacity:0.7; border-left: 1px solid rgba(255,255,255,0.2); padding-left: 0.5rem; margin-left: 0.5rem;"><?= $cnt ?></small>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Search -->
      <div class="data-table-card" style="margin-bottom:1.25rem;">
        <div style="padding:1rem 1.25rem;">
          <form method="GET" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
            <input type="hidden" name="status" value="<?= $statusFilter ?>">
            <input type="text" name="search" class="filter-input" placeholder="🔍 Search by order #, name, email..." value="<?= htmlspecialchars($search) ?>" style="flex:1;">
            <button type="submit" class="btn-primary btn-sm"><i class="bi bi-search"></i> Search</button>
          </form>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="data-table-card">
        <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr><th>Order #</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach($orders as $order):
              $itemCount = $order['item_count'];
            ?>
            <tr>
              <td><a href="order_detail.php?id=<?= $order['id'] ?>" style="color:var(--primary); text-decoration:none; font-weight:700; font-size:0.8rem;"><?= $order['order_number'] ?></a></td>
              <td>
                <div style="font-weight:600; font-size:0.875rem;"><?= htmlspecialchars($order['name']) ?></div>
                <div style="font-size:0.72rem; color:var(--text-muted);"><?= $order['phone'] ?></div>
              </td>
              <td style="font-size:0.8rem;"><?= date('d M Y', strtotime($order['created_at'])) ?><br><span style="color:var(--text-muted);"><?= date('h:i A', strtotime($order['created_at'])) ?></span></td>
              <td><?= $itemCount ?> item<?= $itemCount>1?'s':'' ?></td>
              <td style="font-weight:800;"><?= formatPrice($order['total']) ?></td>
              <td><span class="badge <?= $order['payment_method']==='cod'?'badge-pending':'badge-delivered' ?>"><?= strtoupper($order['payment_method']) ?></span></td>
              <td><span class="badge badge-<?= $order['order_status'] ?>"><?= ucfirst($order['order_status']) ?></span></td>
              <td>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="update_status" value="1">
                  <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                  <div style="display:flex; gap:0.4rem; align-items:center;">
                    <select name="status" class="filter-input" style="padding:0.3rem 0.5rem; font-size:0.75rem;">
                      <?php foreach($statuses as $s): ?>
                      <option value="<?= $s ?>" <?= $order['order_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" name="update_status" class="btn-icon btn-edit" title="Update"><i class="bi bi-check-lg"></i></button>
                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn-icon" title="View"><i class="bi bi-eye"></i></a>
                  </div>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($orders)): ?>
            <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--text-muted);">No orders found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        </div>
        <?php if($pages > 1): ?>
        <div class="pagination">
          <?php for($i=1;$i<=$pages;$i++): ?>
          <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>" class="page-link <?= $i==$page?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
