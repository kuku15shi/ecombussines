<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$orderNum = $_GET['order'] ?? '';
$order = null;
if ($orderNum) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=?");
    $stmt->execute([$orderNum]);
    $order = $stmt->fetch();
}
if (!$order) { header('Location: ' . SITE_URL . '/index'); exit; }

$stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
$stmtItems->execute([$order['id']]);
$items = $stmtItems->fetchAll();

$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Confirmed – LuxeStore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    .success-icon { width:100px; height:100px; border-radius:50%; background:linear-gradient(135deg,var(--success),#38BDF8); display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; font-size:3rem; animation:pulse 2s infinite; }
    @keyframes confetti-fall { from{transform:translateY(-20px) rotate(0deg);opacity:1;} to{transform:translateY(100vh) rotate(720deg);opacity:0;}}
    .confetti-piece { position:fixed; width:10px; height:10px; pointer-events:none; animation:confetti-fall linear infinite; }
  </style>
</head>
<!-- MOBILE HEADER -->
<?php include 'includes/mobile_header.php'; ?>

<?php include 'includes/navbar.php'; ?>

<!-- Confetti -->
<script>
for(let i=0;i<30;i++){
  const c=document.createElement('div');
  c.className='confetti-piece';
  c.style.cssText=`left:${Math.random()*100}vw;top:-20px;background:hsl(${Math.random()*360},70%,60%);border-radius:${Math.random()>0.5?'50%':'2px'};width:${6+Math.random()*10}px;height:${6+Math.random()*10}px;animation-duration:${2+Math.random()*3}s;animation-delay:${Math.random()*2}s;`;
  document.body.appendChild(c);
  setTimeout(()=>c.remove(),5000);
}
</script>

<div class="page-wrapper">
  <div class="container-sm" style="max-width:700px;">
    <div class="glass-card" style="padding:3rem 2.5rem; text-align:center; margin-bottom:2rem;">
      <div class="success-icon">✓</div>
      <h1 style="font-size:2rem; font-weight:800; margin-bottom:0.5rem;">Order Confirmed! 🎉</h1>
      <p style="color:var(--text-secondary); margin-bottom:1.5rem;">Thank you for shopping with LuxeStore. Your order has been placed successfully.</p>
      <div style="background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); padding:1.25rem; margin-bottom:1.5rem; display:inline-block;">
        <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px;">Order Number</div>
        <div style="font-size:1.6rem; font-weight:900; background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;"><?= $order['order_number'] ?></div>
      </div>

      <!-- Order Meta -->
      <div class="order-meta-grid">
        <div class="glass-card">
          <div style="font-size:0.75rem; color:var(--text-muted);">Payment</div>
          <div style="font-weight:700; margin-top:0.25rem; text-transform:capitalize;"><?= $order['payment_method'] === 'cod' ? '💵 Cash on Delivery' : '💳 Online' ?></div>
        </div>
        <div class="glass-card">
          <div style="font-size:0.75rem; color:var(--text-muted);">Status</div>
          <div style="margin-top:0.25rem;"><span class="status-badge status-<?= $order['order_status'] ?>"><i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> <?= ucfirst($order['order_status']) ?></span></div>
        </div>
        <div class="glass-card">
          <div style="font-size:0.75rem; color:var(--text-muted);">Total Paid</div>
          <div style="font-weight:800; font-size:1.1rem; background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; margin-top:0.25rem;"><?= formatPrice($order['total']) ?></div>
          <?php if($order['cod_fee'] > 0): ?>
          <div style="font-size:0.65rem; color:var(--text-muted); margin-top:2px;">(Inc. <?= formatPrice($order['cod_fee']) ?> COD Fee)</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Items -->
      <div style="text-align:left; margin-bottom:2rem;">
        <div style="font-weight:700; margin-bottom:1rem;">Items Ordered (<?= count($items) ?>)</div>
        <div style="display:flex; flex-direction:column; gap:0.75rem;">
          <?php foreach($items as $item): ?>
          <div style="display:flex; gap:1rem; align-items:center; background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.875rem;">
            <img src="<?= UPLOAD_URL . $item['product_image'] ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'" style="width:56px; height:56px; border-radius:var(--radius-sm); object-fit:cover;" alt="">
            <div style="flex:1;">
              <div style="font-weight:600;"><?= htmlspecialchars($item['product_name']) ?></div>
              <div style="font-size:0.8rem; color:var(--text-muted);">Qty: <?= $item['quantity'] ?> × <?= formatPrice($item['price']) ?></div>
            </div>
            <div style="font-weight:800;"><?= formatPrice($item['total']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Delivery Info -->
      <div style="text-align:left; background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); padding:1.25rem; margin-bottom:2rem;">
        <div style="font-weight:700; margin-bottom:0.75rem;"><i class="bi bi-geo-alt" style="color:var(--primary);"></i> Delivery Address</div>
        <div style="color:var(--text-secondary); line-height:1.7;">
          <strong><?= htmlspecialchars($order['name']) ?></strong><br>
          <?= htmlspecialchars($order['address']) ?><br>
          <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> – <?= htmlspecialchars($order['pincode']) ?><br>
          📞 <?= htmlspecialchars($order['phone']) ?>
        </div>
      </div>

      <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
        <a href="<?= SITE_URL ?>/order/<?= urlencode($order['order_number']) ?>/track" class="btn-primary-luxury"><i class="bi bi-bag-check"></i> Track Order</a>
        <a href="<?= SITE_URL ?>/order/<?= urlencode($order['order_number']) ?>/invoice" class="btn-outline-luxury" target="_blank"><i class="bi bi-file-pdf"></i> Download Invoice</a>
        <a href="<?= SITE_URL ?>/index" class="btn-outline-luxury"><i class="bi bi-house"></i> Continue Shopping</a>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/bottom_nav.php'; ?>
</body>
</html>
