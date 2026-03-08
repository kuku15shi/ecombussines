<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Order Detail';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT o.*, u.name as user_name, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.id=?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if(!$order){ header('Location: orders.php'); exit; }

$stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])){
    validateCsrf();
    $status = $_POST['status'] ?? '';
    $tracking_id = $_POST['tracking_id'] ?? '';
    $courier_name = $_POST['courier_name'] ?? '';
    $tracking_url = $_POST['tracking_url'] ?? '';
    
    $allowed = ['pending','processing','shipped','delivered','cancelled'];
    if (in_array($status, $allowed)) {
        $pdo->prepare("UPDATE orders SET order_status=?, tracking_id=?, courier_name=?, tracking_url=? WHERE id=?")->execute([$status, $tracking_id, $courier_name, $tracking_url, $id]);
    }
    header('Location: order_detail.php?id='.$id.'&success=1'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order <?= $order['order_number'] ?> – Admin</title>
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
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Order updated!</div><?php endif; ?>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:0.75rem;">
        <div>
          <a href="orders.php" style="color:var(--text-muted); text-decoration:none; font-size:0.85rem;"><i class="bi bi-arrow-left"></i> Back to Orders</a>
          <h2 style="font-weight:800; margin-top:0.4rem;">Order #<?= $order['order_number'] ?></h2>
        </div>
        <div style="display:flex; gap:0.75rem; align-items:center;">
          <span class="badge badge-<?= $order['order_status'] ?>" style="font-size:0.875rem; padding:0.4rem 1rem;"><?= ucfirst($order['order_status']) ?></span>
          <a href="../invoice.php?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn-primary"><i class="bi bi-file-pdf"></i> Invoice</a>
        </div>
      </div>

      <div class="admin-grid-2-1">
        <div style="display:flex; flex-direction:column; gap:1.25rem;">
          <!-- Items -->
          <div class="data-table-card">
            <div class="data-table-header"><div class="data-table-title">Items Ordered</div></div>
            <div class="table-responsive">
            <table class="admin-table">
              <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Discount</th><th class="text-right">Total</th></tr></thead>
              <tbody>
                <?php foreach($items as $item): ?>
                <tr>
                  <td>
                    <div style="display:flex; gap:0.75rem; align-items:center;">
                      <img src="<?= UPLOAD_URL.$item['product_image'] ?>" onerror="this.src='../assets/img/default_product.jpg'" style="width:48px;height:48px;border-radius:var(--radius-sm);object-fit:cover;" alt="">
                      <div style="display:flex; flex-direction:column;">
                        <span style="font-weight:600;"><?= htmlspecialchars($item['product_name']) ?></span>
                        <?php if($item['size'] || $item['color']): ?>
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px; display:flex; gap:0.5rem;">
                          <?php if($item['size']): ?><span>Size: <?= strtoupper($item['size']) ?></span><?php endif; ?>
                          <?php if($item['color']): ?>
                          <span style="display:flex; align-items:center; gap:3px;">Color: <span style="width:10px; height:10px; border-radius:50%; background:<?= $item['color'] ?>; border:1px solid rgba(0,0,0,0.1);"></span></span>
                          <?php endif; ?>
                        </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td><?= formatPrice($item['price']) ?></td>
                  <td><?= $item['quantity'] ?></td>
                  <td><?= $item['discount_percent'] > 0 ? $item['discount_percent'].'%' : '–' ?></td>
                  <td class="text-right" style="font-weight:800;"><?= formatPrice($item['total']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          </div>
          <!-- Customer -->
          <div class="form-card">
            <div style="font-weight:700; margin-bottom:1rem;">Customer & Delivery Info</div>
            <div class="admin-grid-half">
              <div><div style="font-size:0.75rem; color:var(--text-muted);">Name</div><div style="font-weight:600;"><?= htmlspecialchars($order['name']) ?></div></div>
              <div><div style="font-size:0.75rem; color:var(--text-muted);">Email</div><div style="font-weight:600;"><?= htmlspecialchars($order['email']) ?></div></div>
              <div><div style="font-size:0.75rem; color:var(--text-muted);">Phone</div><div style="font-weight:600;"><?= htmlspecialchars($order['phone']) ?></div></div>
              <div><div style="font-size:0.75rem; color:var(--text-muted);">Payment</div><div style="font-weight:600; text-transform:uppercase;"><?= $order['payment_method'] ?></div></div>
              <div style="grid-column:1/-1;"><div style="font-size:0.75rem; color:var(--text-muted);">Delivery Address</div><div style="font-weight:600; line-height:1.7;"><?= htmlspecialchars($order['address']) ?><br><?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> – <?= htmlspecialchars($order['pincode']) ?></div></div>
              <?php if($order['notes']): ?><div style="grid-column:1/-1;"><div style="font-size:0.75rem; color:var(--text-muted);">Notes</div><div><?= htmlspecialchars($order['notes']) ?></div></div><?php endif; ?>
            </div>
          </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:1.25rem;">
          <!-- Summary -->
          <div class="form-card">
            <div style="font-weight:700; margin-bottom:1rem;">Order Summary</div>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
              <div style="display:flex; justify-content:space-between; color:var(--text-muted); font-size:0.875rem;"><span>Subtotal</span><span><?= formatPrice($order['subtotal']) ?></span></div>
              <?php if($order['discount'] > 0): ?><div style="display:flex; justify-content:space-between; color:var(--success); font-size:0.875rem;"><span>Discount</span><span>–<?= formatPrice($order['discount']) ?></span></div><?php endif; ?>
              <div style="display:flex; justify-content:space-between; color:var(--text-muted); font-size:0.875rem;"><span>Shipping</span><span><?= $order['shipping'] == 0 ? 'FREE' : formatPrice($order['shipping']) ?></span></div>
              <div style="display:flex; justify-content:space-between; color:var(--text-muted); font-size:0.875rem;"><span>GST</span><span><?= formatPrice($order['gst']) ?></span></div>
              <?php if(isset($order['cod_fee']) && $order['cod_fee'] > 0): ?>
              <div style="display:flex; justify-content:space-between; color:var(--text-muted); font-size:0.875rem;"><span>COD Fee</span><span><?= formatPrice($order['cod_fee']) ?></span></div>
              <?php endif; ?>
              <div style="display:flex; justify-content:space-between; font-weight:800; font-size:1.2rem; border-top:1px solid var(--border); padding-top:0.75rem;"><span>Total</span><span style="color:var(--primary);"><?= formatPrice($order['total']) ?></span></div>
            </div>
          </div>
          <!-- Update Status -->
          <div class="form-card">
            <div style="font-weight:700; margin-bottom:1rem;">Update Order Status</div>
            <form method="POST">
              <?= csrfField() ?>
              <div class="form-group">
                <label class="form-label">Order Status</label>
                <select name="status" class="form-control" style="margin-bottom:1rem;">
                  <?php foreach(['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $order['order_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Courier Partner</label>
                <input type="text" name="courier_name" class="form-control" value="<?= htmlspecialchars($order['courier_name'] ?? '') ?>" placeholder="e.g. Delhivery, BlueDart">
              </div>
              <div class="form-group">
                <label class="form-label">Tracking ID</label>
                <input type="text" name="tracking_id" class="form-control" value="<?= htmlspecialchars($order['tracking_id'] ?? '') ?>" placeholder="Enter tracking number">
              </div>
              <div class="form-group" style="margin-bottom:1.5rem;">
                <label class="form-label">Manual Tracking Link (Optional)</label>
                <input type="url" name="tracking_url" class="form-control" value="<?= htmlspecialchars($order['tracking_url'] ?? '') ?>" placeholder="https://tracking-site.com/...">
              </div>
              <button type="submit" name="update_status" class="btn-primary" style="width:100%; justify-content:center;">Update Status</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
