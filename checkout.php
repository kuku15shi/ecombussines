<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

requireUserLogin();

$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
  header('Location: ' . SITE_URL . '/cart');
  exit;
}

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
    'color' => $item['color'] ?? ''
  ];
}

$discount = $_SESSION['coupon_discount'] ?? 0;
$couponCode = $_SESSION['coupon_code'] ?? '';
$shipping = ($subtotal - $discount) >= (defined('FREE_SHIPPING_ABOVE') ? FREE_SHIPPING_ABOVE : 999) ? 0 : (defined('SHIPPING_CHARGE') ? SHIPPING_CHARGE : 50);
$gst = ($subtotal - $discount) * (defined('GST_PERCENT') ? GST_PERCENT : 18) / 100;
$cod_fee = 0; // Initialized to 0, will be updated below if needed
$total = $subtotal - $discount + $shipping + $gst;

// Address Loading
$stmtAddrs = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmtAddrs->execute([$currentUser['id']]);
$userAddresses = $stmtAddrs->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validateCsrf();
  $payment = $_POST['payment'] ?? 'cod';
  $notes = $_POST['notes'] ?? '';
  $addressId = $_POST['selected_address_id'] ?? 0;

  $selectedAddress = null;
  if ($addressId) {
      $stmtCheck = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
      $stmtCheck->execute([$addressId, $currentUser['id']]);
      $selectedAddress = $stmtCheck->fetch();
  }

  if ($selectedAddress) {
    $name = $selectedAddress['full_name'];
    $email = $selectedAddress['email'] ?: $currentUser['email'];
    $phone = $selectedAddress['phone'];
    $addressInfo = $selectedAddress['house'] . ', ' . $selectedAddress['street'];
    if ($selectedAddress['landmark']) $addressInfo .= ', Near ' . $selectedAddress['landmark'];
    $city = $selectedAddress['city'];
    $state = $selectedAddress['state'];
    $pincode = $selectedAddress['pincode'];
    
    $orderNum = generateOrderNumber();
    $uid = $currentUser['id'];
    $payment_status = 'pending';

    try {
      $pdo->beginTransaction();

      $cod_fee = ($payment === 'cod') ? (defined('COD_CHARGE') ? COD_CHARGE : 40) : 0;
      $final_total = $total + $cod_fee;

      $stmt = $pdo->prepare("INSERT INTO orders (order_number,user_id,name,email,phone,address,city,state,pincode,subtotal,discount,shipping,gst,cod_fee,total,coupon_code,payment_method,payment_status,order_status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $initial_status = 'pending'; // Change: Always start as pending for COD verification
      $stmt->execute([$orderNum, $uid, $name, $email, $phone, $addressInfo, $city, $state, $pincode, $subtotal, $discount, $shipping, $gst, $cod_fee, $final_total, $couponCode, $payment, $payment_status, $initial_status, $notes]);
      $orderId = $pdo->lastInsertId();

      foreach ($cartItems as $item) {
        $p = $item['product'];
        $pImg = getProductFirstImage($p['images']);

        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id,product_id,size,color,product_name,product_image,quantity,price,discount_percent,total) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmtItem->execute([$orderId, $p['id'], $item['size'], $item['color'], $p['name'], $pImg, $item['qty'], $item['price'], $p['discount_percent'], $item['total']]);

        // Reduce stock
        $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?")->execute([$item['qty'], $p['id']]);
      }

      // Update coupon usage
      if ($couponCode) {
        $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?")->execute([$couponCode]);
      }

      $pdo->commit();

      // Handle Payment Flow
      if ($payment === 'cod') {
        require_once __DIR__ . '/includes/whatsapp_functions.php';

        // Get Product Names for the message
        $pNames = [];
        foreach ($cartItems as $item) {
          $pNames[] = $item['product']['name'];
        }
        $pSummary = implode(', ', $pNames);
        if (strlen($pSummary) > 40)
          $pSummary = substr($pSummary, 0, 37) . '...';

        // Use template for first message (Meta rule)
        $templateData = [
          ["type" => "text", "text" => $name],
          ["type" => "text", "text" => $orderNum . " (" . $pSummary . ")"],
          ["type" => "text", "text" => formatPrice($final_total)],
          ["type" => "text", "text" => "Cash on Delivery"],
          ["type" => "text", "text" => "Reply: 1 Confirm, 2 Cancel, 3 Support"]
        ];

        sendWhatsAppMessage($phone, '', 'template', 'order_confirmation', $templateData, $orderId);
        sendAdminOrderNotification($orderId);

        recordAffiliateCommission($orderId, $final_total);
        unset($_SESSION['cart'], $_SESSION['coupon_discount'], $_SESSION['coupon_code']);
        header('Location: ' . SITE_URL . '/order/' . urlencode($orderNum) . '/success');
      } else {
        header('Location: ' . SITE_URL . '/order/' . urlencode($orderNum) . '/payment');
      }
      exit;
    } catch (Exception $e) {
      $pdo->rollBack();
      $error = 'Database error: ' . $e->getMessage();
    }
  } else {
    $error = 'Please select a delivery address.';
  }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout – MIZ MAX</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
  <style>
    /* Aggressive Mobile Responsiveness Override */
    @media (max-width: 768px) {
      html, body {
        width: 100% !important;
        max-width: 100vw !important;
        overflow-x: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
        position: relative !important;
        -webkit-text-size-adjust: 100%;
      }
      .page-wrapper {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100vw !important;
        overflow-x: hidden !important;
      }
      .container-sm, .container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 1rem !important;
        margin: 0 !important;
        box-sizing: border-box !important;
      }
      .mobile-header, .bottom-nav {
        width: 100% !important;
        max-width: 100vw !important;
        left: 0 !important;
        right: 0 !important;
        box-sizing: border-box !important;
      }
      .checkout-layout-grid {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        max-width: 100vw !important;
        gap: 1.5rem !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      .checkout-layout-grid > div {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
      }
      .cart-summary {
        position: static !important;
        width: 100% !important;
        box-sizing: border-box !important;
      }
      /* Fix steps for very small screens */
      .checkout-steps-container {
        gap: 0.5rem !important;
        justify-content: space-around !important;
      }
      .checkout-steps-text {
        font-size: 0.75rem !important;
      }
      .fab-whatsapp {
          right: 15px !important;
          bottom: 75px !important;
      }
    }
  </style>
</head>
<!-- MOBILE HEADER -->
<?php include 'includes/mobile_header.php'; ?>

<?php include 'includes/navbar.php'; ?>
<div class="page-wrapper">
  <div class="container-sm">
    <div class="breadcrumb">
      <a href="index">Home</a><span class="separator"><i class="bi bi-chevron-right"></i></span>
      <a href="cart">Cart</a><span class="separator"><i class="bi bi-chevron-right"></i></span>
      <span class="current">Checkout</span>
    </div>

    <!-- Checkout Steps -->
    <div class="checkout-steps-container" style="display:flex; justify-content:center; gap:2rem; margin-bottom:2.5rem; flex-wrap:wrap;">
      <?php $steps = [['bi-bag-check', 'Cart', 'done'], ['bi-credit-card', 'Checkout', 'active'], ['bi-check-circle', 'Confirm', '']]; ?>
      <?php foreach ($steps as $i => $s): ?>
        <div style="display:flex; align-items:center; gap:0.5rem; opacity:<?= $s[2] ? 1 : 0.4 ?>;">
          <div
            style="width:36px;height:36px;border-radius:50%;background:<?= $s[2] === 'active' ? 'linear-gradient(135deg,var(--primary),var(--primary-dark))' : ($s[2] === 'done' ? 'rgba(67,233,123,0.2)' : 'var(--glass)') ?>;border:1px solid <?= $s[2] === 'active' ? 'transparent' : ($s[2] === 'done' ? 'rgba(67,233,123,0.5)' : 'var(--glass-border)') ?>;display:flex;align-items:center;justify-content:center;color:<?= $s[2] === 'done' ? 'var(--success)' : '#fff' ?>;">
            <i class="bi <?= $s[0] ?>"></i>
          </div>
          <span class="checkout-steps-text" style="font-weight:600;font-size:0.875rem;"><?= $s[1] ?></span>
          <?php if ($i < count($steps) - 1): ?><i class="bi bi-chevron-right"
              style="opacity:0.3;margin-left:1rem;"></i><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <form method="POST" style="overflow-x:hidden; width:100%;">
      <?= csrfField() ?>
      <div class="checkout-layout-grid" style="width:100%; max-width:100%;">
        <!-- Form Fields -->
        <div style="display:flex; flex-direction:column; gap:1.5rem; min-width:0; width:100%; max-width:100%;">
          <!-- Delivery Address Selection -->
          <div class="glass-card" style="padding:clamp(1rem, 4vw, 1.75rem);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.5rem;">
              <h3 style="font-weight:800; display:flex; align-items:center; gap:0.6rem; font-size:1.25rem;">
                <i class="bi bi-geo-alt" style="color:var(--primary);"></i> Delivery Address
              </h3>
              <?php if(!empty($userAddresses)): ?>
              <button type="button" onclick="openAddressModal()" style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.4rem 0.8rem; border-radius:50px; border:1px solid var(--border); color:var(--text-primary); background:var(--glass); font-size:0.75rem; font-weight:600; cursor:pointer; transition:var(--transition);" onmouseover="this.style.background='var(--primary)'; this.style.color='#fff'; this.style.borderColor='var(--primary)';" onmouseout="this.style.background='var(--glass)'; this.style.color='var(--text-primary)'; this.style.borderColor='var(--border)';">
                <i class="bi bi-plus-lg"></i> Add New
              </button>
              <?php endif; ?>
            </div>
            
            <input type="hidden" name="selected_address_id" id="selected_address_id" value="">

            <div id="address-list-container" style="display:flex; flex-direction:column; gap:1rem; max-height:400px; overflow-y:auto; overflow-x:hidden; padding-right:5px; width:100%;">
              <?php if(empty($userAddresses)): ?>
                <div style="text-align:center; padding:3rem 1.5rem; background:var(--glass); border-radius:var(--radius); border:1px dashed var(--glass-border); display:flex; flex-direction:column; align-items:center; justify-content:center;">
                  <div style="width:70px; height:70px; border-radius:50%; background:linear-gradient(135deg, rgba(108,99,255,0.1), rgba(108,99,255,0.2)); display:flex; align-items:center; justify-content:center; margin-bottom:1.25rem;">
                      <i class="bi bi-house-door" style="font-size:2.2rem; background:linear-gradient(135deg, var(--primary), var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;"></i>
                  </div>
                  <div style="font-weight:800; font-size:1.2rem; margin-bottom:0.5rem; color:var(--text-primary);">No Address Found</div>
                  <div style="font-size:0.9rem; color:var(--text-muted); max-width:300px; margin:0 auto 1.5rem; line-height:1.5;">You haven't saved any delivery addresses yet. Please add a new address to continue.</div>
                  <button type="button" class="btn-primary-luxury" style="padding:0.6rem 1.5rem; border-radius:50px; box-shadow:0 8px 20px rgba(108,99,255,0.25);" onclick="openAddressModal()">
                    <i class="bi bi-plus-lg"></i> Add New Address
                  </button>
                </div>
              <?php else: ?>
                <?php foreach($userAddresses as $idx => $addr): ?>
                  <label class="address-card" style="display:flex; gap:0.5rem; padding:clamp(0.75rem, 3vw, 1.25rem); background:<?= $addr['is_default'] ? 'rgba(var(--primary-rgb),0.05)' : 'var(--glass)' ?>; border:1px solid <?= $addr['is_default'] ? 'var(--primary)' : 'var(--glass-border)' ?>; border-radius:var(--radius); cursor:pointer; width:100%; box-sizing:border-box; overflow:hidden; transition:var(--transition);" onclick="selectAddress(this, <?= $addr['id'] ?>)">
                    <input type="radio" name="address_radio" value="<?= $addr['id'] ?>" style="display:none;" <?= $addr['is_default'] ? 'checked' : '' ?>>
                    <div style="flex:1; min-width:0;">
                      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem; gap:0.5rem;">
                        <div style="font-weight:700; font-size:0.95rem; color:var(--text-primary); line-height:1.2; word-break:break-word; overflow-wrap:break-word; padding-right:5px;">
                          <?= htmlspecialchars($addr['full_name']) ?>
                          <?php if($addr['address_type']): ?>
                            <span style="display:inline-block; font-size:0.65rem; padding:2px 6px; background:var(--glass-border); border-radius:4px; margin-left:4px; text-transform:capitalize; vertical-align:middle; white-space:nowrap;"><?= htmlspecialchars($addr['address_type']) ?></span>
                          <?php endif; ?>
                        </div>
                        <div class="radio-indicator" style="flex-shrink:0; width:18px; height:18px; border-radius:50%; border:2px solid <?= $addr['is_default'] ? 'var(--primary)' : 'var(--glass-border)' ?>; display:flex; align-items:center; justify-content:center;">
                          <div style="width:10px; height:10px; border-radius:50%; background:<?= $addr['is_default'] ? 'var(--primary)' : 'transparent' ?>;"></div>
                        </div>
                      </div>
                      <div style="font-size:0.8rem; color:var(--text-secondary); line-height:1.4; word-break:break-word; overflow-wrap:break-word; margin-bottom:0.75rem;">
                        <span style="display:block; margin-bottom:2px;"><?= htmlspecialchars($addr['house'] . ', ' . $addr['street']) ?></span>
                        <?php if($addr['landmark']) echo '<span style="display:block; margin-bottom:2px;">Near ' . htmlspecialchars($addr['landmark']) . '</span>'; ?>
                        <span style="display:block; margin-bottom:4px;"><?= htmlspecialchars($addr['city'] . ', ' . $addr['district'] . ', ' . $addr['state'] . ' - ' . $addr['pincode']) ?></span>
                        <strong style="color:var(--text-primary); display:block;">Phone: <?= htmlspecialchars($addr['phone']) ?></strong>
                      </div>
                      <div style="display:flex; gap:0.5rem;">
                          <button type="button" class="btn btn-sm btn-outline-danger" style="padding:0.2rem 0.6rem; font-size:0.7rem; display:inline-flex; align-items:center; gap:4px;" onclick="deleteAddress(event, <?= $addr['id'] ?>)"><i class="bi bi-trash"></i> Delete</button>
                      </div>
                    </div>
                  </label>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div class="form-group" style="margin-top:1.5rem; margin-bottom:0;">
              <label class="form-label">Order Notes (Optional)</label>
              <input type="text" name="notes" class="form-control" placeholder="Any special instructions">
            </div>
          </div>

          <!-- Payment -->
          <div class="glass-card" style="padding:1.75rem;">
            <h3 style="font-weight:800; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.6rem;">
              <i class="bi bi-credit-card" style="color:var(--primary);"></i> Payment Method
            </h3>
            <div style="display:flex; flex-direction:column; gap:0.75rem;">
              <label
                style="display:flex; align-items:center; gap:1rem; padding:1.25rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); cursor:pointer; transition:var(--transition);"
                onclick="selectPayment(this, 'cod')" id="pay-cod">
                <input type="radio" name="payment" value="cod" style="display:none;" required>
                <div
                  style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,rgba(247,183,49,0.2),rgba(249,115,22,0.2)); display:flex; align-items:center; justify-content:center;">
                  <i class="bi bi-cash" style="font-size:1.5rem; color:var(--gold);"></i>
                </div>
                <div>
                  <div style="font-weight:700;">Cash on Delivery</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Pay when you receive your order</div>
                  <div style="font-size:0.75rem; color:var(--secondary); font-weight:600; margin-top:2px;">
                    <i class="bi bi-info-circle"></i> Extra <?= formatPrice(defined('COD_CHARGE') ? COD_CHARGE : 40) ?>
                    fee applies
                  </div>
                </div>
                <div
                  style="margin-left:auto; width:20px; height:20px; border-radius:50%; border:2px solid var(--glass-border);"
                  id="radio-cod">
                </div>
              </label>
              <label
                style="display:flex; align-items:center; gap:1rem; padding:1.25rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); cursor:pointer; transition:var(--transition);"
                onclick="selectPayment(this, 'upi')" id="pay-upi">
                <input type="radio" name="payment" value="online" style="display:none;">
                <div
                  style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,rgba(0,184,148,0.2),rgba(85,239,196,0.2)); display:flex; align-items:center; justify-content:center;">
                  <i class="bi bi-qr-code" style="font-size:1.5rem; color:#00b894;"></i>
                </div>
                <div>
                  <div style="font-weight:700;">UPI Payment</div>
                  <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem;">GPay, PhonePe, Paytm &
                    more</div>
                  <div style="display:flex; gap:0.5rem; align-items:center;">
                    <img src="https://img.icons8.com/color/48/google-pay.png"
                      style="height:18px; filter:grayscale(0.2);" alt="GPay">
                    <img src="https://img.icons8.com/color/48/phone-pe.png" style="height:18px; filter:grayscale(0.2);"
                      alt="PhonePe">
                    <img src="https://img.icons8.com/color/48/paytm.png" style="height:14px; filter:grayscale(0.2);"
                      alt="Paytm">
                  </div>
                </div>
                <div
                  style="margin-left:auto; width:20px; height:20px; border-radius:50%; border:2px solid var(--glass-border);"
                  id="radio-upi">
                </div>
              </label>
              <label
                style="display:flex; align-items:center; gap:1rem; padding:1.25rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); cursor:pointer; transition:var(--transition);"
                onclick="selectPayment(this, 'online')" id="pay-online">
                <input type="radio" name="payment" value="online" style="display:none;">
                <div
                  style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,rgba(108,99,255,0.2),rgba(255,101,132,0.2)); display:flex; align-items:center; justify-content:center;">
                  <i class="bi bi-credit-card-2-front" style="font-size:1.5rem; color:var(--primary);"></i>
                </div>
                <div>
                  <div style="font-weight:700;">Cards / Net Banking</div>
                  <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem;">Credit/Debit Cards, Net
                    Banking</div>
                  <div style="display:flex; gap:0.5rem; align-items:center;">
                    <img src="https://img.icons8.com/color/48/visa.png" style="height:14px; opacity:0.8;" alt="Visa">
                    <img src="https://img.icons8.com/color/48/mastercard.png" style="height:18px; opacity:0.8;"
                      alt="Mastercard">
                    <img src="https://img.icons8.com/color/48/rupay.png" style="height:12px; opacity:0.8;" alt="Rupay">
                  </div>
                </div>
                <div
                  style="margin-left:auto; width:20px; height:20px; border-radius:50%; border:2px solid var(--glass-border);"
                  id="radio-online">
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="glass-card cart-summary">
          <h3 style="font-weight:800; margin-bottom:1.25rem;">Order Summary</h3>
          <div
            style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.5rem; max-height:280px; overflow-y:auto;">
            <?php foreach ($cartItems as $item):
              $p = $item['product'];
              ?>
              <div style="display:flex; gap:0.75rem; align-items:center;">
                <img src="<?= UPLOAD_URL . getProductFirstImage($p['images']) ?>"
                  onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'"
                  style="width:52px; height:52px; border-radius:var(--radius-sm); object-fit:cover;" alt="">
                <div style="flex:1; min-width:0;">
                  <div
                    style="font-size:0.85rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= htmlspecialchars($p['name']) ?>
                  </div>
                  <div style="display:flex; gap:0.4rem; font-size:0.7rem; color:var(--text-muted); margin-top:2px;">
                    <span>Qty: <?= $item['qty'] ?></span>
                    <?php if ($item['size']): ?><span>| Size: <?= strtoupper($item['size']) ?></span><?php endif; ?>
                    <?php if ($item['color']): ?><span>| Color: <?= strtoupper($item['color']) ?></span><?php endif; ?>
                  </div>
                </div>
                <div style="font-weight:700; font-size:0.9rem; flex-shrink:0;"><?= formatPrice($item['total']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div
            style="border-top:1px solid var(--border); padding-top:0.875rem; display:flex; flex-direction:column; gap:0.6rem; margin-bottom:1.5rem;">
            <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.875rem;">
              <span>Subtotal</span><span><?= formatPrice($subtotal) ?></span>
            </div>
            <?php if ($discount > 0): ?>
              <div style="display:flex; justify-content:space-between; color:var(--success); font-size:0.875rem;">
                <span>Discount</span><span>–<?= formatPrice($discount) ?></span>
              </div><?php endif; ?>
            <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.875rem;">
              <span>Shipping</span><span><?= $shipping === 0 ? '<span style="color:var(--success)">FREE</span>' : formatPrice($shipping) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.875rem;">
              <span>GST (<?= GST_PERCENT ?>%)</span><span><?= formatPrice($gst) ?></span>
            </div>
            <div id="cod-fee-row" style="display:none; flex-direction:column; gap:2px; margin-bottom:4px;">
              <div
                style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.875rem;">
                <span>COD Handling Fee</span>
                <span><?= formatPrice(defined('COD_CHARGE') ? COD_CHARGE : 40) ?></span>
              </div>
              <div style="font-size:0.7rem; color:var(--secondary); text-align:right; font-weight:500;">* Applied only
                for Cash on Delivery</div>
            </div>
            <div
              style="display:flex; justify-content:space-between; font-weight:800; font-size:1.2rem; padding-top:0.6rem; border-top:1px solid var(--border);">
              <span>Total</span>
              <span id="final-total"
                style="background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;"><?= formatPrice($total) ?></span>
            </div>
          </div>
          <button type="submit" class="btn-primary-luxury"
            style="width:100%; justify-content:center; padding:1rem; font-size:1rem;"
            onclick="return validateCheckout()">
            <i class="bi bi-lock-fill"></i> Place Order
          </button>
          <p style="text-align:center; color:var(--text-muted); font-size:0.75rem; margin-top:0.875rem;">
            <i class="bi bi-shield-check"></i> Secured by SSL encryption
          </p>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
  // Address selection logic
  function selectAddress(el, id) {
    document.getElementById('selected_address_id').value = id;
    document.querySelectorAll('.address-card').forEach(card => {
      card.style.borderColor = 'var(--glass-border)';
      card.style.background = 'var(--glass)';
      const radioInd = card.querySelector('.radio-indicator');
      if (radioInd) {
           radioInd.style.borderColor = 'var(--glass-border)';
           radioInd.firstElementChild.style.background = 'transparent';
      }
    });
    
    el.style.borderColor = 'var(--primary)';
    el.style.background = 'rgba(var(--primary-rgb),0.05)';
    const ind = el.querySelector('.radio-indicator');
    if (ind) {
        ind.style.borderColor = 'var(--primary)';
        ind.firstElementChild.style.background = 'var(--primary)';
    }
  }

  // Pre-select Default exactly on load
  document.addEventListener('DOMContentLoaded', () => {
    const checkedRadio = document.querySelector('input[name="address_radio"]:checked');
    if (checkedRadio) {
      document.getElementById('selected_address_id').value = checkedRadio.value;
    }
  });

  function selectPayment(el, type) {
    ['cod', 'online', 'upi'].forEach(t => {
      const box = document.getElementById('pay-' + t);
      if (box) box.style.borderColor = t === type ? 'var(--primary)' : 'var(--glass-border)';
      const radio = document.getElementById('radio-' + t);
      if (radio) {
        radio.innerHTML = t === type ? '<div style="width:10px;height:10px;border-radius:50%;background:var(--primary);"></div>' : '';
        radio.style.borderColor = t === type ? 'var(--primary)' : 'var(--glass-border)';
      }
    });
    el.querySelector('input[type=radio]').checked = true;

    // Update COD fee display and Total
    const codFee = <?= defined('COD_CHARGE') ? COD_CHARGE : 40 ?>;
    const baseTotal = <?= $total ?>;
    const codRow = document.getElementById('cod-fee-row');
    const finalTotalEl = document.getElementById('final-total');

    if (type === 'cod') {
      codRow.style.display = 'flex';
      finalTotalEl.innerText = '<?= CURRENCY ?>' + (baseTotal + codFee).toFixed(2);
    } else {
      codRow.style.display = 'none';
      finalTotalEl.innerText = '<?= CURRENCY ?>' + baseTotal.toFixed(2);
    }
  }

  function validateCheckout() {
    const addressId = document.getElementById('selected_address_id').value;
    if (!addressId) {
       alert('Please select or add a delivery address to continue.');
       const target = document.querySelector('.glass-card');
       if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
       return false;
    }

    const selected = document.querySelector('input[name="payment"]:checked');
    if (!selected) {
      alert('Please select a payment method to continue.');
      const target = document.getElementById('pay-upi') || document.getElementById('pay-cod');
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return false;
    }
    return true;
  }
</script>

<!-- Add Address Modal & Logic -->
<div id="addressModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; background:rgba(0,0,0,0.6); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); justify-content:center; align-items:center; padding:1rem;">
    <div class="glass-card" style="background:var(--bg-dark); width:100%; max-width:650px; max-height:90vh; overflow-y:auto; border-radius:20px; padding:2rem; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
        <button type="button" onclick="closeAddressModal()" style="position:absolute; top:15px; right:15px; background:var(--glass); border:1px solid var(--glass-border); width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--text-primary); transition:var(--transition);" onmouseover="this.style.background='var(--primary)'; this.style.color='#fff';" onmouseout="this.style.background='var(--glass)'; this.style.color='var(--text-primary)';"><i class="bi bi-x-lg" style="font-size:0.9rem;"></i></button>
        <h3 style="font-weight:800; margin-bottom:1.5rem; font-size:1.3rem;"><i class="bi bi-geo-alt" style="color:var(--primary);"></i> Add New Address</h3>
        
        <form id="addAddressForm" onsubmit="submitNewAddress(event)">
            <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem;">
                <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">Full Name *</label><input type="text" name="full_name" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary);" required></div>
                <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">Phone Number *</label><input type="tel" name="phone" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary);" pattern="[0-9]{10,15}" required></div>
                
                <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">Pincode *</label>
                    <div style="display:flex; gap:0.5rem;">
                        <input type="text" id="pincodeInput" name="pincode" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary);" pattern="[0-9]{6}" required>
                        <button type="button" style="padding:0 0.8rem; border-radius:var(--radius-sm); border:1px solid var(--primary); background:rgba(108,99,255,0.1); color:var(--primary); cursor:pointer;" onclick="fetchPincodeData()" title="Auto-fill City/State"><i class="bi bi-magic"></i></button>
                        <button type="button" style="padding:0 0.8rem; border-radius:var(--radius-sm); border:none; background:linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#fff; cursor:pointer;" onclick="detectLocation()" title="Use GPS Location"><i class="bi bi-crosshair"></i></button>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">Locality / Area *</label><input type="text" id="localityInput" name="street" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary);" required></div>
                
                <div class="form-group" style="grid-column:1/-1; margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">Flat / House No. / Building *</label><input type="text" name="house" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary);" required></div>
                <div class="form-group" style="grid-column:1/-1; margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">Landmark (Optional)</label><input type="text" name="landmark" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary);"></div>
                
                <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">City / Town *</label><input type="text" id="cityInput" name="city" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary);" required></div>
                <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">District *</label><input type="text" id="districtInput" name="district" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary);" required></div>
                
                <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">State *</label><input type="text" id="stateInput" name="state" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary);" required></div>
                <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem; display:block;">Address Type</label>
                    <select name="address_type" class="form-control" style="width:100%; padding:0.6rem 1rem; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--glass); color:var(--text-primary); cursor:pointer;">
                        <option value="home" style="background:var(--bg-card); color:var(--text-primary);">Home</option>
                        <option value="work" style="background:var(--bg-card); color:var(--text-primary);">Work</option>
                        <option value="other" style="background:var(--bg-card); color:var(--text-primary);">Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group" style="margin-top:1.5rem; margin-bottom:0;">
                <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-size:0.9rem; color:var(--text-primary);">
                    <input type="checkbox" name="is_default" value="1" checked style="width:18px; height:18px; accent-color:var(--primary);"> 
                    Set as default delivery address
                </label>
            </div>
            
            <button type="submit" class="btn-primary-luxury" style="width:100%; margin-top:2rem; padding:1rem; border-radius:var(--radius-sm); box-shadow:0 8px 25px rgba(108,99,255,0.3); font-size:1rem;" id="saveAddrBtn">
                <i class="bi bi-save"></i> Save Address & Deliver Here
            </button>
        </form>
    </div>
</div>

<script>
function deleteAddress(e, id) {
    e.stopPropagation(); // prevent label click
    if (!confirm('Are you sure you want to delete this address?')) return;
    
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('address_id', id);
    
    fetch('<?= SITE_URL ?>/ajax/manage_address.php', {
        method: 'POST',
        body: fd
    }).then(r=>r.json()).then(data => {
        if(data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error occurred');
        }
    }).catch(e => {
        alert('Exception: ' + e);
    });
}
function openAddressModal() {
    document.getElementById('addressModal').style.display = 'flex';
}
function closeAddressModal() {
    document.getElementById('addressModal').style.display = 'none';
}

function fetchPincodeData() {
    const pin = document.getElementById('pincodeInput').value;
    if(pin.length === 6) {
        fetch(`https://api.postalpincode.in/pincode/${pin}`)
        .then(res => res.json())
        .then(data => {
            if(data[0].Status === "Success" && data[0].PostOffice.length > 0) {
                const po = data[0].PostOffice[0];
                document.getElementById('cityInput').value = po.Block;
                document.getElementById('districtInput').value = po.District;
                document.getElementById('stateInput').value = po.State;
            } else {
                alert("Invalid Pincode.");
            }
        }).catch(err => alert("Failed to fetch data"));
    } else {
        alert("Please enter a valid 6-digit pincode.");
    }
}

function detectLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(position => {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&accept-language=en`)
            .then(res => res.json())
            .then(data => {
                const addr = data.address;
                if(addr.postcode) document.getElementById('pincodeInput').value = addr.postcode;
                if(addr.city || addr.town || addr.village) document.getElementById('cityInput').value = addr.city || addr.town || addr.village;
                if(addr.state_district || addr.county) document.getElementById('districtInput').value = addr.state_district || addr.county || '';
                if(addr.state) document.getElementById('stateInput').value = addr.state;
                if(addr.suburb || addr.neighbourhood) document.getElementById('localityInput').value = addr.suburb || addr.neighbourhood;
            }).catch(e => console.error(e));
        }, () => alert("Location access denied or failed."));
    } else {
        alert("Geolocation is not supported by this browser.");
    }
}

function submitNewAddress(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveAddrBtn');
    saveBtn.innerText = 'Saving...';
    saveBtn.disabled = true;

    const fd = new FormData(e.target);
    fd.append('action', 'add');
    
    fetch('<?= SITE_URL ?>/ajax/manage_address.php', {
        method: 'POST',
        body: fd
    }).then(r=>r.json()).then(data => {
        if(data.success) {
            closeAddressModal();
            window.location.reload(); // Reload to refresh address list safely and fast
        } else {
            alert(data.message || 'Error occurred');
            saveBtn.innerText = 'Save Address';
            saveBtn.disabled = false;
        }
    }).catch(e => {
        alert('Exception: ' + e);
        saveBtn.innerText = 'Save Address';
        saveBtn.disabled = false;
    });
}
</script>
<?php include 'includes/bottom_nav.php'; ?>
</body>

</html>