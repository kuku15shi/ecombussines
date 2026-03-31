<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

requireUserLogin();
$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
$uid = $currentUser['id'];

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$uid]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders – MIZ MAX</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
</head>
<!-- MOBILE HEADER -->
<?php include 'includes/mobile_header.php'; ?>

<?php include 'includes/navbar.php'; ?>
<div class="page-wrapper">
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= SITE_URL ?>/index">Home</a><span class="separator"><i class="bi bi-chevron-right"></i></span>
      <span class="current">My Orders</span>
    </div>

    <h1 style="font-size:2rem; font-weight:800; margin-bottom:2rem;">
      <i class="bi bi-bag-check" style="color:var(--primary);"></i> My Orders
    </h1>

    <?php if (empty($orders)): ?>
      <div class="glass-card" style="text-align:center; padding:4rem 2rem;">
        <div style="font-size:4rem; margin-bottom:1rem;">📦</div>
        <h2 style="color:var(--text-secondary); margin-bottom:0.5rem;">No orders yet</h2>
        <p style="color:var(--text-muted); margin-bottom:1.5rem;">Start shopping and your orders will appear here</p>
        <a href="<?= SITE_URL ?>/products" class="btn-primary-luxury"><i class="bi bi-bag"></i> Shop Now</a>
      </div>
    <?php else: ?>
      <div style="display:flex; flex-direction:column; gap:1.25rem;">
        <?php foreach ($orders as $order):
          $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
          $itemsStmt->execute([$order['id']]);
          $items = $itemsStmt->fetchAll();
          $firstItem = $items[0] ?? null;
          ?>
          <div class="glass-card" style="padding:1.5rem;">
            <div
              style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
              <div>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.25rem;">
                  <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
                <div style="font-weight:800; font-size:1.05rem;">Order #<?= $order['order_number'] ?></div>
                <?php if (($order['order_status'] === 'shipped' || $order['order_status'] === 'delivered') && !empty($order['tracking_id'])): ?>
                  <div style="margin-top:0.4rem; display:flex; align-items:center; gap:0.5rem;">
                    <span style="font-size:0.75rem; color:var(--text-muted);">Tracking:</span>
                    <span style="font-size:0.8rem; font-weight:700; color:var(--primary); font-family:monospace;"
                      class="track-id-list-<?= $order['id'] ?>"><?= htmlspecialchars($order['tracking_id']) ?></span>
                    <button onclick="copyToClipboard('<?= htmlspecialchars($order['tracking_id']) ?>', this)"
                      style="background:none; border:none; padding:2px; cursor:pointer; color:var(--text-muted);"
                      title="Copy Tracking ID">
                      <i class="bi bi-copy" style="font-size:0.75rem;"></i>
                    </button>
                  </div>
                <?php endif; ?>
              </div>
              <div style="display:flex; align-items:center; gap:0.75rem;">
                <span class="status-badge status-<?= $order['order_status'] ?>"><i class="bi bi-circle-fill"
                    style="font-size:0.45rem;"></i> <?= ucfirst($order['order_status']) ?></span>
              </div>
            </div>

            <!-- Items Preview -->
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.25rem;">
              <?php foreach (array_slice($items, 0, 4) as $item): ?>
                <div style="position:relative;">
                  <img src="<?= UPLOAD_URL . $item['product_image'] ?>"
                    onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'"
                    style="width:64px; height:64px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--glass-border);"
                    title="<?= htmlspecialchars($item['product_name']) ?>" alt="">
                  <?php if ($item['quantity'] > 1): ?>
                    <div
                      style="position:absolute; bottom:-4px; right:-4px; background:var(--primary); color:#fff; width:18px; height:18px; border-radius:50%; font-size:0.65rem; font-weight:700; display:flex; align-items:center; justify-content:center;">
                      ×<?= $item['quantity'] ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php if (count($items) > 4): ?>
                <div
                  style="width:64px; height:64px; border-radius:var(--radius-sm); background:var(--glass); border:1px solid var(--glass-border); display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:var(--text-muted);">
                  +<?= count($items) - 4 ?> more</div>
              <?php endif; ?>
            </div>

            <!-- Order Footer -->
            <div
              style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; padding-top:1.25rem; border-top:1px solid var(--border);">
              <div>
                <div style="font-size:0.8rem; color:var(--text-muted);"><?= count($items) ?>
                  item<?= count($items) > 1 ? 's' : '' ?></div>
                <div
                  style="font-weight:800; font-size:1.2rem; background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
                  <?= formatPrice($order['total']) ?></div>
              </div>
              <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <?php if ($order['payment_method'] === 'online' && $order['payment_status'] === 'pending'): ?>
                  <a href="<?= SITE_URL ?>/order/<?= urlencode($order['order_number']) ?>/payment" class="btn-primary-luxury"
                    style="padding:0.5rem 1.25rem; font-size:0.85rem;">
                    <i class="bi bi-credit-card"></i> Pay Now
                  </a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>/order/<?= urlencode($order['order_number']) ?>/track" class="btn-outline-luxury"
                  style="padding:0.5rem 1.25rem; font-size:0.85rem;">
                  <i class="bi bi-truck"></i> Track
                </a>
                <a href="<?= SITE_URL ?>/order/<?= urlencode($order['order_number']) ?>/invoice" class="btn-outline-luxury"
                  style="padding:0.5rem 1.25rem; font-size:0.85rem;" target="_blank">
                  <i class="bi bi-file-pdf"></i> Invoice
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
<?php include 'includes/bottom_nav.php'; ?>
<script>
  function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
      const icon = btn.querySelector('i');
      const originalClass = icon.className;
      icon.className = 'bi bi-check-lg';
      icon.style.color = 'var(--success)';
      setTimeout(() => {
        icon.className = originalClass;
        icon.style.color = '';
      }, 2000);
    });
  }
</script>
</body>

</html>