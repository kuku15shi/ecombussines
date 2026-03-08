<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$orderNum = $_GET['order'] ?? '';
if(!$orderNum) { header('Location: ' . SITE_URL . '/orders'); exit; }

$stmt = $pdo->prepare("SELECT o.*, u.name as user_name FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.order_number=?");
$stmt->execute([$orderNum]);
$order = $stmt->fetch();

if(!$order) { header('Location: ' . SITE_URL . '/orders'); exit; }

$stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
$stmtItems->execute([$order['id']]);
$items = $stmtItems->fetchAll();

$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);

$statusSteps = ['pending','confirmed','shipped','delivered'];
$currentStep = array_search($order['order_status'], $statusSteps);
if($order['order_status'] === 'cancelled') $currentStep = -1;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track Order #<?= $order['order_number'] ?> – LuxeStore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
  <style>
    .track-timeline { position:relative; padding:1.5rem 0; }
    .track-timeline::before { content:''; position:absolute; left:20px; top:0; bottom:0; width:2px; background:var(--glass-border); }
    .track-step { position:relative; padding:0 0 2rem 56px; }
    .track-step:last-child { padding-bottom:0; }
    .track-dot { position:absolute; left:0; width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; transition:var(--transition); border:2px solid var(--glass-border); background:var(--glass); }
    .track-dot.active { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); border-color:transparent; box-shadow:0 0 20px rgba(108,99,255,0.4); }
    .track-dot.done { background:linear-gradient(135deg,var(--success),#38BDF8); border-color:transparent; }
    .track-step-title { font-weight:700; margin-bottom:0.25rem; }
    .track-step-sub { font-size:0.8rem; color:var(--text-muted); }
  </style>
</head>
<!-- MOBILE HEADER -->
<?php include 'includes/mobile_header.php'; ?>

<?php include 'includes/navbar.php'; ?>
<div class="page-wrapper">
  <div class="container-sm" style="max-width:800px;">
    <div class="breadcrumb">
      <a href="<?= SITE_URL ?>/index">Home</a><span class="separator"><i class="bi bi-chevron-right"></i></span>
      <a href="<?= SITE_URL ?>/orders">My Orders</a><span class="separator"><i class="bi bi-chevron-right"></i></span>
      <span class="current">Track Order</span>
    </div>

    <!-- Order Header -->
    <div class="glass-card" style="padding:1.75rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
      <div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.25rem;">Order Number</div>
        <div style="font-size:1.5rem; font-weight:800; background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;"><?= $order['order_number'] ?></div>
        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.25rem;">Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
      </div>
      <div style="display:flex; gap:0.75rem;">
        <span class="status-badge status-<?= $order['order_status'] ?>"><i class="bi bi-circle-fill" style="font-size:0.45rem;"></i> <?= ucfirst($order['order_status']) ?></span>
        <a href="<?= SITE_URL ?>/order/<?= urlencode($order['order_number']) ?>/invoice" class="btn-outline-luxury" style="padding:0.5rem 1rem; font-size:0.82rem;" target="_blank">
          <i class="bi bi-file-pdf"></i> Invoice
        </a>
      </div>
    </div>

    <?php if($order['order_status'] === 'cancelled'): ?>
    <div class="alert alert-danger">
      <i class="bi bi-x-circle"></i> This order has been <strong>cancelled</strong>.
    </div>
    <?php else: ?>
    
    <!-- Tracking Info Card (Visible when Shipped/Delivered) -->
    <?php 
    $tracking_id = $order['tracking_id'] ?? '';
    $courier = strtolower($order['courier_name'] ?? '');
    $track_url = $order['tracking_url'] ?: '#';
    
    if(empty($order['tracking_url']) && !empty($tracking_id)) {
        if(strpos($courier, 'delhivery') !== false) $track_url = "https://www.delhivery.com/track/package/" . $tracking_id;
        elseif(strpos($courier, 'bluedart') !== false) $track_url = "https://www.bluedart.com/tracking?trackid=" . $tracking_id;
        elseif(strpos($courier, 'ekart') !== false) $track_url = "https://ekartlogistics.com/track/" . $tracking_id;
        elseif(strpos($courier, 'ecom') !== false) $track_url = "https://ecomexpress.in/tracking/?order_id=" . $tracking_id;
        elseif(strpos($courier, 'dtdc') !== false) $track_url = "https://www.dtdc.in/tracking/tracking_results.asp?SearchNo=" . $tracking_id;
        elseif(strpos($courier, 'shadowfax') !== false) $track_url = "https://tracker.shadowfax.in/track?orderId=" . $tracking_id;
    }

    if(($order['order_status'] === 'shipped' || $order['order_status'] === 'delivered') && !empty($tracking_id)): 
    ?>
    <div class="glass-card" style="padding:1.75rem; margin-bottom:1.5rem; border-left:5px solid var(--primary); background: linear-gradient(to right, rgba(108,99,255,0.05), transparent);">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem;">
        <div style="flex:1; min-width:250px;">
          <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1.5px; margin-bottom:0.75rem; font-weight:700;">Shipping Information</div>
          <div style="margin-bottom:1rem;">
            <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.25rem;">Courier Partner</div>
            <div style="font-weight:800; font-size:1.15rem; display:flex; align-items:center; gap:0.5rem;">
               <i class="bi bi-box-seam" style="color:var(--primary);"></i>
               <?= htmlspecialchars($order['courier_name'] ?: 'Standard Shipping') ?>
            </div>
          </div>
          <div>
            <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.4rem;">Tracking ID</div>
            <div style="display:flex; align-items:center; gap:0.6rem;">
              <div style="background:var(--bg-lighter); padding:0.6rem 1rem; border-radius:10px; border:2px dashed var(--glass-border); font-family:'Courier New', monospace; font-weight:800; color:var(--text-primary); font-size:1.1rem; letter-spacing:1px;" id="trackingId">
                <?= htmlspecialchars($tracking_id) ?>
              </div>
              <button onclick="copyTrackingId()" class="btn-icon" title="Copy Tracking ID" style="width:42px; height:42px; background:var(--primary); color:white; border:none; border-radius:10px; cursor:pointer; transition:all 0.2s ease;">
                <i class="bi bi-copy" id="copyIcon"></i>
              </button>
            </div>
          </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.75rem;">
          <a href="<?= $track_url ?>" target="_blank" class="btn-primary-luxury" style="padding:0.8rem 1.5rem; justify-content:center; text-decoration:none;">
            <i class="bi bi-box-arrow-up-right"></i> Track on <?= htmlspecialchars($order['courier_name'] ?: 'Carrier') ?>
          </a>
          <div style="font-size:0.75rem; color:var(--text-muted); text-align:center;">Updates may take up to 24h</div>
        </div>
      </div>
    </div>
    <script>
    function copyTrackingId() {
      const text = document.getElementById('trackingId').innerText.trim();
      navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('button[title="Copy Tracking ID"]');
        const icon = document.getElementById('copyIcon');
        
        btn.style.background = 'var(--success)';
        icon.classList.replace('bi-copy', 'bi-check2');
        
        showToast('Tracking ID copied to clipboard!', 'success');
        
        setTimeout(() => {
          btn.style.background = 'var(--primary)';
          icon.classList.replace('bi-check2', 'bi-copy');
        }, 2000);
      });
    }
    </script>
    <?php endif; ?>

    <!-- Tracking Timeline -->
    <div class="glass-card" style="padding:1.75rem; margin-bottom:1.5rem;">
      <h3 style="font-weight:800; margin-bottom:1.5rem;"><i class="bi bi-truck" style="color:var(--primary);"></i> Tracking Status</h3>
      <div class="track-timeline">
        <?php
        $trackSteps = [
          ['pending','bi-bag-plus','Order Placed','Your order has been placed and is being reviewed.'],
          ['confirmed','bi-check-circle','Confirmed','Your order has been confirmed and is being prepared.'],
          ['shipped','bi-truck','Shipped','Your package is on the way to your address.'],
          ['delivered','bi-house-check','Delivered','Your order has been delivered. Enjoy! 🎉'],
        ];
        foreach($trackSteps as $si => $step):
          $isDone = $si < $currentStep;
          $isActive = $si === $currentStep;
        ?>
        <div class="track-step">
          <div class="track-dot <?= $isDone?'done':($isActive?'active':'') ?>">
            <i class="bi <?= $step[1] ?>" style="color:#fff;"></i>
          </div>
          <div class="track-step-title" style="opacity:<?= ($isDone||$isActive)?1:0.4 ?>;"><?= $step[2] ?></div>
          <div class="track-step-sub"><?= $step[3] ?></div>
          <?php if($isActive): ?>
          <div style="margin-top:0.5rem; background:rgba(108,99,255,0.1); border:1px solid rgba(108,99,255,0.3); border-radius:var(--radius-sm); padding:0.5rem 1rem; font-size:0.8rem; color:var(--primary); display:inline-block;">
            <i class="bi bi-clock-history"></i> Current Status
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Items + Summary -->
    <div class="track-order-grid">
      <div class="glass-card" style="padding:1.5rem;">
        <h3 style="font-weight:800; margin-bottom:1.25rem;">Items (<?= count($items) ?>)</h3>
        <div style="display:flex; flex-direction:column; gap:0.75rem;">
          <?php foreach($items as $item): ?>
          <div style="display:flex; gap:1rem; align-items:flex-start; padding:0.5rem 0;">
            <img src="<?= UPLOAD_URL . $item['product_image'] ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'" style="width:56px; height:56px; border-radius:var(--radius-sm); object-fit:cover; flex-shrink:0; border:1px solid var(--glass-border);" alt="">
            <div style="flex:1; min-width:0;">
              <div style="font-size:0.9rem; font-weight:700; line-height:1.4; color:var(--text-primary); margin-bottom:0.125rem;"><?= htmlspecialchars($item['product_name']) ?></div>
              <div style="font-size:0.75rem; color:var(--text-muted); font-weight:500;">Qty: <?= $item['quantity'] ?> × <?= formatPrice($item['price']) ?></div>
            </div>
            <div style="font-weight:800; font-size:1rem; white-space:nowrap; margin-left:0.75rem; color:var(--text-primary); text-align:right;"><?= formatPrice($item['total']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="glass-card" style="padding:1.5rem;">
        <h3 style="font-weight:800; margin-bottom:1.25rem;">Order Summary</h3>
        <div style="display:flex; flex-direction:column; gap:0.6rem;">
          <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:var(--text-secondary);"><span>Subtotal</span><span><?= formatPrice($order['subtotal']) ?></span></div>
          <?php if($order['discount'] > 0): ?><div style="display:flex; justify-content:space-between; font-size:0.875rem; color:var(--success);"><span>Discount</span><span>–<?= formatPrice($order['discount']) ?></span></div><?php endif; ?>
          <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:var(--text-secondary);"><span>Shipping</span><span><?= $order['shipping'] == 0 ? '<span style="color:var(--success)">FREE</span>' : formatPrice($order['shipping']) ?></span></div>
          <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:var(--text-secondary);"><span>GST</span><span><?= formatPrice($order['gst']) ?></span></div>
          <?php if(isset($order['cod_fee']) && $order['cod_fee'] > 0): ?>
          <div style="display:flex; justify-content:space-between; font-size:0.875rem; color:var(--text-secondary);"><span>COD Fee</span><span><?= formatPrice($order['cod_fee']) ?></span></div>
          <?php endif; ?>
          <div style="display:flex; justify-content:space-between; font-weight:800; font-size:1.1rem; border-top:1px solid var(--border); padding-top:0.75rem;"><span>Total</span><span style="background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;"><?= formatPrice($order['total']) ?></span></div>
          <div style="margin-top:0.5rem; padding-top:0.75rem; border-top:1px solid var(--border);">
            <div style="font-size:0.8rem; color:var(--text-muted);">Payment Method</div>
            <div style="font-weight:600;"><?= $order['payment_method'] === 'cod' ? '💵 Cash on Delivery' : '💳 Online Payment' ?></div>
          </div>
          <div>
            <div style="font-size:0.8rem; color:var(--text-muted);">Delivery Address</div>
            <div style="font-size:0.875rem; color:var(--text-secondary); line-height:1.6;"><?= htmlspecialchars($order['name']) ?><br><?= htmlspecialchars($order['address']) ?><br><?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> <?= htmlspecialchars($order['pincode']) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
<?php include 'includes/bottom_nav.php'; ?>
</body>
</html>
