<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

requireUserLogin();

$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) { header('Location: ' . SITE_URL . '/cart'); exit; }

// Calculate totals
$subtotal = 0;
$cartItems = [];
foreach ($cart as $key => $item) {
    if (!isset($item['id'])) continue;
    $p = getProductById($pdo, $item['id']);
    if (!$p) continue;
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $pincode = $_POST['pincode'] ?? '';
    $payment = $_POST['payment'] ?? 'cod';
    $notes = $_POST['notes'] ?? '';

    if ($name && $email && $phone && $address && $city && $state && $pincode) {
        $orderNum = generateOrderNumber();
        $uid = $currentUser['id'];
        $payment_status = 'pending';

        try {
            $pdo->beginTransaction();
            
            $cod_fee = ($payment === 'cod') ? (defined('COD_CHARGE') ? COD_CHARGE : 40) : 0;
            $final_total = $total + $cod_fee;

            $stmt = $pdo->prepare("INSERT INTO orders (order_number,user_id,name,email,phone,address,city,state,pincode,subtotal,discount,shipping,gst,cod_fee,total,coupon_code,payment_method,payment_status,order_status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $initial_status = 'pending'; // Change: Always start as pending for COD verification
            $stmt->execute([$orderNum, $uid, $name, $email, $phone, $address, $city, $state, $pincode, $subtotal, $discount, $shipping, $gst, $cod_fee, $final_total, $couponCode, $payment, $payment_status, $initial_status, $notes]);
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
                foreach($cartItems as $item) {
                    $pNames[] = $item['product']['name'];
                }
                $pSummary = implode(', ', $pNames);
                if(strlen($pSummary) > 40) $pSummary = substr($pSummary,0,37) . '...';

                // Use template for first message (Meta rule)
                $templateData = [
                    ["type" => "text", "text" => $name],
                    ["type" => "text", "text" => $orderNum . " (" . $pSummary . ")"],
                    ["type" => "text", "text" => formatPrice($final_total)],
                    ["type" => "text", "text" => "Cash on Delivery"],
                    ["type" => "text", "text" => "Reply: 1 Confirm, 2 Cancel, 3 Support"]
                ];
                
                sendWhatsAppMessage($phone, '', 'template', 'order_confirmation', $templateData, $orderId);

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
        $error = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout – LuxeStore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
  <div class="container-sm">
    <div class="breadcrumb">
      <a href="index">Home</a><span class="separator"><i class="bi bi-chevron-right"></i></span>
      <a href="cart">Cart</a><span class="separator"><i class="bi bi-chevron-right"></i></span>
      <span class="current">Checkout</span>
    </div>

    <!-- Checkout Steps -->
    <div style="display:flex; justify-content:center; gap:2rem; margin-bottom:2.5rem; flex-wrap:wrap;">
      <?php $steps = [['bi-bag-check','Cart','done'],['bi-credit-card','Checkout','active'],['bi-check-circle','Confirm','']]; ?>
      <?php foreach($steps as $i=>$s): ?>
      <div style="display:flex; align-items:center; gap:0.5rem; opacity:<?= $s[2]?1:0.4 ?>;">
        <div style="width:36px;height:36px;border-radius:50%;background:<?= $s[2]==='active'?'linear-gradient(135deg,var(--primary),var(--primary-dark))':($s[2]==='done'?'rgba(67,233,123,0.2)':'var(--glass)') ?>;border:1px solid <?= $s[2]==='active'?'transparent':($s[2]==='done'?'rgba(67,233,123,0.5)':'var(--glass-border)') ?>;display:flex;align-items:center;justify-content:center;color:<?= $s[2]==='done'?'var(--success)':'#fff' ?>;">
          <i class="bi <?= $s[0] ?>"></i>
        </div>
        <span style="font-weight:600;font-size:0.875rem;"><?= $s[1] ?></span>
        <?php if($i < count($steps)-1): ?><i class="bi bi-chevron-right" style="opacity:0.3;margin-left:1rem;"></i><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if(isset($error)): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <div class="checkout-layout-grid">
        <!-- Form Fields -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
          <!-- Personal Info -->
          <div class="glass-card" style="padding:1.75rem;">
            <h3 style="font-weight:800; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.6rem;">
              <i class="bi bi-person-circle" style="color:var(--primary);"></i> Personal Information
            </h3>
            <div class="grid-2">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($currentUser['name']) ?>" required>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
              </div>
              <div class="form-group" style="margin-bottom:0; grid-column:1/-1;">
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="+91 98765 43210" required>
              </div>
            </div>
          </div>

          <!-- Shipping Address -->
          <div class="glass-card" style="padding:1.75rem;">
            <h3 style="font-weight:800; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.6rem;">
              <i class="bi bi-geo-alt" style="color:var(--primary);"></i> Shipping Address
            </h3>
            <div class="grid-2">
              <div class="form-group" style="margin-bottom:0; grid-column:1/-1;">
                <label class="form-label">Street Address *</label>
                <textarea name="address" class="form-control" rows="2" placeholder="House/Flat No., Street, Area" required><?= htmlspecialchars($currentUser['address'] ?? '') ?></textarea>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">City *</label>
                <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($currentUser['city'] ?? '') ?>" required>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">State *</label>
                <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($currentUser['state'] ?? '') ?>" required>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Pincode *</label>
                <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($currentUser['pincode'] ?? '') ?>" pattern="[0-9]{6}" placeholder="6-digit pincode" required>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Order Notes (Optional)</label>
                <input type="text" name="notes" class="form-control" placeholder="Any special instructions">
              </div>
            </div>
          </div>

          <!-- Payment -->
          <div class="glass-card" style="padding:1.75rem;">
            <h3 style="font-weight:800; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.6rem;">
              <i class="bi bi-credit-card" style="color:var(--primary);"></i> Payment Method
            </h3>
            <div style="display:flex; flex-direction:column; gap:0.75rem;">
              <label style="display:flex; align-items:center; gap:1rem; padding:1.25rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); cursor:pointer; transition:var(--transition);" onclick="selectPayment(this, 'cod')" id="pay-cod">
                <input type="radio" name="payment" value="cod" style="display:none;" required>
                <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,rgba(247,183,49,0.2),rgba(249,115,22,0.2)); display:flex; align-items:center; justify-content:center;">
                  <i class="bi bi-cash" style="font-size:1.5rem; color:var(--gold);"></i>
                </div>
                <div>
                  <div style="font-weight:700;">Cash on Delivery</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Pay when you receive your order</div>
                  <div style="font-size:0.75rem; color:var(--secondary); font-weight:600; margin-top:2px;">
                    <i class="bi bi-info-circle"></i> Extra <?= formatPrice(defined('COD_CHARGE') ? COD_CHARGE : 40) ?> fee applies
                  </div>
                </div>
                <div style="margin-left:auto; width:20px; height:20px; border-radius:50%; border:2px solid var(--glass-border);" id="radio-cod">
                </div>
              </label>
              <label style="display:flex; align-items:center; gap:1rem; padding:1.25rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); cursor:pointer; transition:var(--transition);" onclick="selectPayment(this, 'upi')" id="pay-upi">
                <input type="radio" name="payment" value="online" style="display:none;">
                <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,rgba(0,184,148,0.2),rgba(85,239,196,0.2)); display:flex; align-items:center; justify-content:center;">
                  <i class="bi bi-qr-code" style="font-size:1.5rem; color:#00b894;"></i>
                </div>
                <div>
                  <div style="font-weight:700;">UPI Payment</div>
                  <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem;">GPay, PhonePe, Paytm & more</div>
                  <div style="display:flex; gap:0.5rem; align-items:center;">
                    <img src="https://img.icons8.com/color/48/google-pay.png" style="height:18px; filter:grayscale(0.2);" alt="GPay">
                    <img src="https://img.icons8.com/color/48/phone-pe.png" style="height:18px; filter:grayscale(0.2);" alt="PhonePe">
                    <img src="https://img.icons8.com/color/48/paytm.png" style="height:14px; filter:grayscale(0.2);" alt="Paytm">
                  </div>
                </div>
                <div style="margin-left:auto; width:20px; height:20px; border-radius:50%; border:2px solid var(--glass-border);" id="radio-upi">
                </div>
              </label>
              <label style="display:flex; align-items:center; gap:1rem; padding:1.25rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); cursor:pointer; transition:var(--transition);" onclick="selectPayment(this, 'online')" id="pay-online">
                <input type="radio" name="payment" value="online" style="display:none;">
                <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,rgba(108,99,255,0.2),rgba(255,101,132,0.2)); display:flex; align-items:center; justify-content:center;">
                  <i class="bi bi-credit-card-2-front" style="font-size:1.5rem; color:var(--primary);"></i>
                </div>
                <div>
                  <div style="font-weight:700;">Cards / Net Banking</div>
                  <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem;">Credit/Debit Cards, Net Banking</div>
                  <div style="display:flex; gap:0.5rem; align-items:center;">
                    <img src="https://img.icons8.com/color/48/visa.png" style="height:14px; opacity:0.8;" alt="Visa">
                    <img src="https://img.icons8.com/color/48/mastercard.png" style="height:18px; opacity:0.8;" alt="Mastercard">
                    <img src="https://img.icons8.com/color/48/rupay.png" style="height:12px; opacity:0.8;" alt="Rupay">
                  </div>
                </div>
                <div style="margin-left:auto; width:20px; height:20px; border-radius:50%; border:2px solid var(--glass-border);" id="radio-online">
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="glass-card cart-summary">
          <h3 style="font-weight:800; margin-bottom:1.25rem;">Order Summary</h3>
          <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.5rem; max-height:280px; overflow-y:auto;">
            <?php foreach($cartItems as $item):
              $p = $item['product'];
            ?>
            <div style="display:flex; gap:0.75rem; align-items:center;">
              <img src="<?= UPLOAD_URL . getProductFirstImage($p['images']) ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'" style="width:52px; height:52px; border-radius:var(--radius-sm); object-fit:cover;" alt="">
              <div style="flex:1; min-width:0;">
                <div style="font-size:0.85rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($p['name']) ?></div>
                <div style="display:flex; gap:0.4rem; font-size:0.7rem; color:var(--text-muted); margin-top:2px;">
                  <span>Qty: <?= $item['qty'] ?></span>
                  <?php if($item['size']): ?><span>| Size: <?= strtoupper($item['size']) ?></span><?php endif; ?>
                  <?php if($item['color']): ?><span>| Color: <?= strtoupper($item['color']) ?></span><?php endif; ?>
                </div>
              </div>
              <div style="font-weight:700; font-size:0.9rem; flex-shrink:0;"><?= formatPrice($item['total']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="border-top:1px solid var(--border); padding-top:0.875rem; display:flex; flex-direction:column; gap:0.6rem; margin-bottom:1.5rem;">
            <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.875rem;"><span>Subtotal</span><span><?= formatPrice($subtotal) ?></span></div>
            <?php if($discount > 0): ?><div style="display:flex; justify-content:space-between; color:var(--success); font-size:0.875rem;"><span>Discount</span><span>–<?= formatPrice($discount) ?></span></div><?php endif; ?>
            <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.875rem;"><span>Shipping</span><span><?= $shipping === 0 ? '<span style="color:var(--success)">FREE</span>' : formatPrice($shipping) ?></span></div>
            <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.875rem;"><span>GST (<?= GST_PERCENT ?>%)</span><span><?= formatPrice($gst) ?></span></div>
            <div id="cod-fee-row" style="display:none; flex-direction:column; gap:2px; margin-bottom:4px;">
              <div style="display:flex; justify-content:space-between; color:var(--text-secondary); font-size:0.875rem;">
                <span>COD Handling Fee</span>
                <span><?= formatPrice(defined('COD_CHARGE') ? COD_CHARGE : 40) ?></span>
              </div>
              <div style="font-size:0.7rem; color:var(--secondary); text-align:right; font-weight:500;">* Applied only for Cash on Delivery</div>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:800; font-size:1.2rem; padding-top:0.6rem; border-top:1px solid var(--border);">
              <span>Total</span>
              <span id="final-total" style="background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;"><?= formatPrice($total) ?></span>
            </div>
          </div>
          <button type="submit" class="btn-primary-luxury" style="width:100%; justify-content:center; padding:1rem; font-size:1rem;" onclick="return validateCheckout()">
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
function selectPayment(el, type) {
  ['cod','online','upi'].forEach(t => {
    const box = document.getElementById('pay-'+t);
    if(box) box.style.borderColor = t === type ? 'var(--primary)' : 'var(--glass-border)';
    const radio = document.getElementById('radio-'+t);
    if(radio) {
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
  const selected = document.querySelector('input[name="payment"]:checked');
  if(!selected) {
    alert('Please select a payment method to continue.');
    const target = document.getElementById('pay-upi') || document.getElementById('pay-cod');
    if(target) target.scrollIntoView({behavior: 'smooth', block: 'center'});
    return false;
  }
  return true;
}
</script>
<?php include 'includes/bottom_nav.php'; ?>
</body>
</html>
