<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$currentUser = getCurrentUser($pdo);
$cart = $_SESSION['cart'] ?? [];
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);

// Calculate totals
$subtotal = 0;
$cartItems = [];
foreach ($cart as $key => $item) {
  if (!isset($item['id']))
    continue;
  $p = getProductById($pdo, $item['id']);
  if (!$p)
    continue;
  $qty = $item['qty'] ?? 1;
  $price = $p['discount_percent'] > 0 ? ($p['price'] * (1 - $p['discount_percent'] / 100)) : $p['price'];
  $subtotal += $price * $qty;
  $cartItems[] = [
    'product' => $p,
    'qty' => $qty,
    'price' => $price,
    'total' => $price * $qty,
    'size' => $item['size'] ?? '',
    'color' => $item['color'] ?? '',
    'cartKey' => $key
  ];
}

$discount = $_SESSION['coupon_discount'] ?? 0;
$couponCode = $_SESSION['coupon_code'] ?? '';
$shipping = ($subtotal - $discount) >= (defined('FREE_SHIPPING_ABOVE') ? FREE_SHIPPING_ABOVE : 999) || $subtotal === 0 ? 0 : (defined('SHIPPING_CHARGE') ? SHIPPING_CHARGE : 50);
$gst = ($subtotal - $discount) * (defined('GST_PERCENT') ? GST_PERCENT : 18) / 100;
$total = $subtotal - $discount + $shipping + $gst;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shopping Cart – MIZ MAX</title>
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
      <span class="current">Shopping Cart</span>
    </div>

    <h1 style="font-size:1.8rem; font-weight:800; margin-bottom:1.75rem;">
      <i class="bi bi-bag"
        style="background:linear-gradient(135deg,var(--primary),var(--accent2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
      Shopping Cart <span style="font-size:1rem; color:var(--text-muted); font-weight:500;">(<?= count($cartItems) ?>
        items)</span>
    </h1>

    <?php if (empty($cartItems)): ?>
      <div class="glass-card" style="text-align:center; padding:5rem 2rem;">
        <div style="font-size:5rem; margin-bottom:1.5rem;">🛒</div>
        <h2 style="font-size:1.5rem; font-weight:700; margin-bottom:0.75rem;">Your cart is empty</h2>
        <p style="color:var(--text-muted); margin-bottom:2rem;">Discover our premium collection and add items to your cart
        </p>
        <a href="<?= SITE_URL ?>/products" class="btn-primary-luxury"><i class="bi bi-bag"></i> Start Shopping</a>
      </div>
    <?php else: ?>
      <div class="cart-layout-grid">
        <!-- Cart Items -->
        <div style="display:flex; flex-direction:column; gap:1rem;">
          <?php foreach ($cartItems as $item):
            $p = $item['product'];
            $firstImg = getProductFirstImage($p['images']);
            ?>
            <div class="glass-card cart-item" id="cart-item-<?= $item['cartKey'] ?>">
              <a href="<?= SITE_URL ?>/product/<?= $p['slug'] ?>">
                <img src="<?= UPLOAD_URL . $firstImg ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'"
                  class="cart-item-img" alt="">
              </a>
              <div class="cart-item-info">
                <a href="<?= SITE_URL ?>/product/<?= $p['slug'] ?>" style="text-decoration:none;">
                  <div class="cart-item-name"><?= htmlspecialchars($p['name']) ?></div>
                </a>
                <div class="cart-item-cat"><?= htmlspecialchars($p['cat_name']) ?></div>
                <?php if ($item['size'] || $item['color']): ?>
                  <div style="display:flex; gap:0.6rem; margin:0.4rem 0;">
                    <?php if ($item['size']): ?>
                      <span
                        style="font-size:0.75rem; background:var(--glass); border:1px solid var(--glass-border); padding:2px 8px; border-radius:4px; font-weight:600;">Size:
                        <?= strtoupper($item['size']) ?></span>
                    <?php endif; ?>
                    <?php if ($item['color']): ?>
                      <span
                        style="font-size:0.75rem; background:var(--glass); border:1px solid var(--glass-border); padding:2px 8px; border-radius:4px; font-weight:600; display:flex; align-items:center; gap:4px;">
                        Color: <span
                          style="width:10px; height:10px; border-radius:50%; background:<?= $item['color'] ?>;"></span>
                      </span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <div class="cart-item-price"><?= formatPrice($item['price']) ?></div>
              </div>
              <div class="cart-item-qty">
                <button class="qty-btn" onclick="updateCartQty('<?= $item['cartKey'] ?>', -1, <?= $p['id'] ?>)">
                  <i class="bi bi-dash"></i>
                </button>
                <span id="qty-<?= $item['cartKey'] ?>" class="qty-num"><?= $item['qty'] ?></span>
                <button class="qty-btn" onclick="updateCartQty('<?= $item['cartKey'] ?>', 1, <?= $p['id'] ?>)">
                  <i class="bi bi-plus"></i>
                </button>
              </div>
              <div class="cart-item-actions">
                <div class="cart-item-total" id="total-<?= $item['cartKey'] ?>"><?= formatPrice($item['total']) ?></div>
                <button onclick="removeCartItem('<?= $item['cartKey'] ?>')" class="cart-remove-btn">
                  <i class="bi bi-trash"></i> <span class="d-md-inline">Remove</span>
                </button>
              </div>
            </div>
          <?php endforeach; ?>

          <!-- Coupon -->
          <div class="glass-card" style="padding:1.5rem;">
            <div style="font-weight:700; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;"><i
                class="bi bi-ticket-perforated" style="color:var(--gold); font-size:1.2rem;"></i> Apply Coupon</div>
            <div class="coupon-container" style="display:flex; gap:0.75rem;">
              <input type="text" id="couponInput" class="form-control" placeholder="Enter coupon code"
                value="<?= htmlspecialchars($couponCode) ?>" style="flex:1; min-width:0; background:var(--glass);">
              <button class="btn-primary-luxury" onclick="applyCoupon()"
                style="white-space:nowrap; padding:0.65rem 1.5rem; justify-content:center; border-radius:10px;">
                Apply
              </button>
            </div>
            <div id="couponMsg" style="margin-top:0.75rem; font-size:0.85rem; font-weight:500;"></div>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="glass-card cart-summary">
          <h3 style="font-weight:800; margin-bottom:1.5rem;">Order Summary</h3>
          <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.5rem;">
            <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.9rem;">
              <span>Subtotal</span>
              <span id="summarySubtotal"><?= formatPrice($subtotal) ?></span>
            </div>
            <?php if ($discount > 0): ?>
              <div style="display:flex; justify-content:space-between; color:var(--success); font-size:0.9rem;">
                <span>Coupon (<?= $couponCode ?>)</span>
                <span>–<?= formatPrice($discount) ?></span>
              </div>
            <?php endif; ?>
            <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.9rem;">
              <span>Shipping</span>
              <span
                id="summaryShipping"><?= $shipping === 0 ? '<span style="color:var(--success)">FREE</span>' : formatPrice($shipping) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.9rem;">
              <span>GST (<?= GST_PERCENT ?>%)</span>
              <span id="summaryGst"><?= formatPrice($gst) ?></span>
            </div>
            <div
              style="border-top:1px solid var(--border); padding-top:0.875rem; display:flex; justify-content:space-between; font-weight:800; font-size:1.2rem;">
              <span>Total</span>
              <span
                style="background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;"
                id="summaryTotal"><?= formatPrice($total) ?></span>
            </div>
          </div>
          <?php if ($subtotal >= FREE_SHIPPING_ABOVE || $subtotal === 0): ?>
            <div
              style="background:rgba(67,233,123,0.1); border:1px solid rgba(67,233,123,0.3); border-radius:var(--radius-sm); padding:0.6rem 1rem; margin-bottom:1rem; font-size:0.8rem; color:var(--success);">
              <i class="bi bi-truck"></i> You qualify for free shipping!
            </div>
          <?php else: ?>
            <div
              style="background:rgba(247,183,49,0.1); border:1px solid rgba(247,183,49,0.2); border-radius:var(--radius-sm); padding:0.6rem 1rem; margin-bottom:1rem; font-size:0.8rem; color:var(--warning);">
              <i class="bi bi-truck"></i> Add <?= formatPrice(FREE_SHIPPING_ABOVE - $subtotal) ?> more for free shipping
            </div>
          <?php endif; ?>
          <a href="<?= SITE_URL ?>/checkout" class="btn-primary-luxury"
            style="width:100%; justify-content:center; font-size:1rem; padding:0.875rem;">
            <i class="bi bi-lock-fill"></i> Proceed to Checkout
          </a>
          <a href="<?= SITE_URL ?>/products" class="btn-outline-luxury"
            style="width:100%; justify-content:center; margin-top:0.75rem;">
            <i class="bi bi-arrow-left"></i> Continue Shopping
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
  const CURRENCY = '<?= CURRENCY ?>';

  function updateCartQty(cartKey, delta, productId) {
    fetch('<?= SITE_URL ?>/ajax/cart.php', {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=update&product_id=' + productId + '&delta=' + delta + '&size=' + (cartKey.split('-')[1] || '') + '&color=' + (cartKey.split('-')[2] || '')
    }).then(r => r.json()).then(data => {
      if (data.success) {
        if (data.qty <= 0) {
          location.reload();
        } else {
          document.getElementById('qty-' + cartKey).textContent = data.qty;
          document.getElementById('total-' + cartKey).textContent = data.itemTotal;
          updateSummary(data);
          if (document.getElementById('cartBadge')) document.getElementById('cartBadge').textContent = data.cartCount;
        }
      } else {
        showToast(data.message, 'error');
      }
    });
  }

  function removeCartItem(cartKey) {
    fetch('<?= SITE_URL ?>/ajax/cart.php', {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=remove&cart_key=' + cartKey
    }).then(r => r.json()).then(data => {
      if (data.success) { location.reload(); }
    });
  }

  function updateSummary(data) {
    if (data.subtotal !== undefined) document.getElementById('summarySubtotal').textContent = CURRENCY + data.subtotal;
    if (data.shipping !== undefined) document.getElementById('summaryShipping').textContent = data.shipping === '0.00' ? 'FREE' : CURRENCY + data.shipping;
    if (data.gst !== undefined) document.getElementById('summaryGst').textContent = CURRENCY + data.gst;
    if (data.total !== undefined) document.getElementById('summaryTotal').textContent = CURRENCY + data.total;
  }

  function applyCoupon() {
    const code = document.getElementById('couponInput').value.trim();
    fetch('<?= SITE_URL ?>/ajax/coupon.php', {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'code=' + encodeURIComponent(code)
    }).then(r => r.json()).then(data => {
      const msg = document.getElementById('couponMsg');
      if (data.success) {
        msg.innerHTML = '<span style="color:var(--success)">✓ ' + data.message + '</span>';
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        msg.innerHTML = '<span style="color:var(--danger)">✗ ' + data.message + '</span>';
        showToast(data.message, 'error');
      }
    });
  }
</script>
<?php include 'includes/bottom_nav.php'; ?>
</body>

</html>