<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../includes/whatsapp_functions.php';

requireAdminLogin();
$pageTitle = 'Manage Orders & WhatsApp';

// Update Status Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    validateCsrf();
    $id = (int)$_POST['order_id'];
    $status = $_POST['status'] ?? '';
    $trackingId = $_POST['tracking_id'] ?? null;
    $courierName = $_POST['courier_name'] ?? null;
    
    $allowed = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
    if (in_array($status, $allowed)) {
        // Get old status to check for change
        $stmtOld = $pdo->prepare("SELECT order_status, phone, order_number FROM orders WHERE id = ?");
        $stmtOld->execute([$id]);
        $orderInfo = $stmtOld->fetch();
        
        if ($orderInfo) {
            $oldStatus = $orderInfo['order_status'];
            $phone = $orderInfo['phone'];
            $orderNum = $orderInfo['order_number'];

            $pdo->prepare("UPDATE orders SET order_status = ?, tracking_id = ?, courier_name = ? WHERE id = ?")
                ->execute([$status, $trackingId, $courierName, $id]);

            // Only send WhatsApp if status changed
            if ($oldStatus !== $status) {
                $waMsg = "";
                if ($status === 'shipped') {
                    $waMsg = "Your order #$orderNum has been shipped 🚚\nTracking ID: " . ($trackingId ?: 'N/A') . "\nCourier: " . ($courierName ?: 'Standard');
                } elseif ($status === 'delivered') {
                    $waMsg = "Order #$orderNum Delivered 🎉\nThank you for shopping with us!";
                } elseif ($status === 'cancelled') {
                    $waMsg = "Your order #$orderNum has been cancelled. If you have any questions, please contact our support.";
                } elseif ($status === 'confirmed') {
                    $waMsg = "Great news! Your order #$orderNum has been confirmed and is now being processed. 📦";
                }

                if ($waMsg) {
                    sendWhatsAppMessage($phone, $waMsg, 'text', '', [], $id);
                }
            }
            header("Location: admin_orders.php?success=1&id=$id"); exit;
        }
    }
}

// Resend Failed Logic
if (isset($_GET['resend']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    
    if ($order && $order['whatsapp_status'] === 'failed') {
        $templateData = [
            ["type" => "text", "text" => $order['name']],
            ["type" => "text", "text" => $order['order_number']],
            ["type" => "text", "text" => formatPrice($order['total'])],
            ["type" => "text", "text" => "Cash on Delivery"],
            ["type" => "text", "text" => "3-5 Business Days"]
        ];
        sendWhatsAppMessage($order['phone'], '', 'template', 'order_confirmation', $templateData, $id);
        header('Location: admin_orders.php?success=resend'); exit;
    }
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$where = "WHERE 1=1";
$params = [];

if ($search) {
    if (preg_match('/^[0-9+]+$/', $search)) {
        $where .= " AND o.phone LIKE ?";
        $params[] = "%$search%";
    } else {
        $where .= " AND (o.order_number LIKE ? OR o.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

if ($statusFilter) {
    $where .= " AND o.order_status = ?";
    $params[] = $statusFilter;
}

$total = $pdo->prepare("SELECT COUNT(*) as c FROM orders o $where");
$total->execute($params);
$totalCount = $total->fetch()['c'];

$pages = ceil($totalCount / $perPage);
$offset = ($page-1) * $perPage;

$sql = "SELECT o.*, u.name as user_name FROM orders o LEFT JOIN users u ON o.user_id=u.id $where ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders Management – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
  <style>
    .tracking-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.5rem; margin-top: 0.5rem; }
    .tracking-form input { padding: 0.35rem 0.5rem; font-size: 0.75rem; border-radius: 4px; border: 1px solid var(--glass-border); background: var(--glass); color: var(--text); }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="content-area">
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Order updated & WhatsApp notified!</div><?php endif; ?>

      <!-- Status Filter Chips -->
      <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.5rem;">
        <a href="admin_orders.php" class="btn-primary btn-sm <?= !$statusFilter?'':'default' ?>">All (<?= $totalCount ?>)</a>
        <?php foreach($statuses as $s): ?>
        <a href="admin_orders.php?status=<?= $s ?>" class="btn-primary btn-sm <?= $statusFilter===$s?'':'default' ?>" style="<?= $statusFilter===$s?'':'background:var(--glass);color:var(--text-muted);' ?>">
          <?= ucfirst($s) ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Search Bar -->
      <div class="data-table-card" style="margin-bottom:1.5rem; padding:1.25rem;">
        <form method="GET" style="display:flex; gap:0.75rem;">
          <input type="text" name="search" class="filter-input" placeholder="🔍 Search by Order #, Name or Phone..." value="<?= htmlspecialchars($search) ?>" style="flex:1;">
          <button type="submit" class="btn-primary btn-sm">Search</button>
        </form>
      </div>

      <!-- Orders List -->
      <div class="data-table-card">
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order Details</th>
                <th>Total</th>
                <th>WA Status</th>
                <th>Status / Shipping</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($orders as $order): ?>
              <tr>
                <td>
                  <div style="font-weight:800; color:var(--primary);"><?= $order['order_number'] ?></div>
                  <div style="font-size:0.85rem; font-weight:600; margin-top:0.25rem;"><?= htmlspecialchars($order['name']) ?></div>
                  <div style="font-size:0.75rem; color:var(--text-muted);"><?= $order['phone'] ?></div>
                  <div style="font-size:0.65rem; color:var(--text-muted);"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
                </td>
                <td style="font-weight:800;"><?= formatPrice($order['total']) ?><br><small style="font-weight:400;"><?= strtoupper($order['payment_method']) ?></small></td>
                <td>
                  <?php if($order['whatsapp_status'] === 'sent'): ?>
                    <span class="badge badge-delivered"><i class="bi bi-check2-all"></i> Sent</span>
                  <?php elseif($order['whatsapp_status'] === 'failed'): ?>
                    <span class="badge badge-cancelled" title="Error!"><i class="bi bi-exclamation-triangle"></i> Failed</span>
                    <a href="?resend=1&id=<?= $order['id'] ?>" style="font-size:0.6rem; display:block; margin-top:0.2rem; color:var(--primary);">Resend Confirmation</a>
                  <?php else: ?>
                    <span class="badge badge-pending">Pending</span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <div style="display:flex; gap:0.4rem; align-items:center;">
                      <select name="status" class="filter-input" style="padding:0.3rem; font-size:0.75rem;">
                        <?php foreach($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $order['order_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <?php if($order['order_status'] === 'shipped'): ?>
                        <span class="badge badge-processing">Shipped</span>
                      <?php endif; ?>
                    </div>
                    <div class="tracking-form">
                      <input type="text" name="tracking_id" placeholder="Tracking ID" value="<?= htmlspecialchars($order['tracking_id'] ?? '') ?>">
                      <input type="text" name="courier_name" placeholder="Courier Name" value="<?= htmlspecialchars($order['courier_name'] ?? '') ?>">
                      <button type="submit" name="update_status" class="btn-icon btn-edit" title="Update"><i class="bi bi-check-lg"></i></button>
                    </div>
                  </form>
                </td>
                <td>
                  <div style="display:flex; gap:0.5rem;">
                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn-icon" title="View Items"><i class="bi bi-eye"></i></a>
                    <a href="admin_chatbot.php?phone=<?= $order['phone'] ?>" class="btn-icon" title="WhatsApp Chat"><i class="bi bi-chat-dots-fill"></i></a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($orders)): ?>
              <tr><td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">No orders found</td></tr>
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
