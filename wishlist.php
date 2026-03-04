<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

requireUserLogin();
$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
$uid = $currentUser['id'];

$stmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM wishlist w LEFT JOIN products p ON w.product_id=p.id LEFT JOIN categories c ON p.category_id=c.id WHERE w.user_id=? AND p.is_active=1 ORDER BY w.created_at DESC");
$stmt->execute([$uid]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Wishlist – LuxeStore</title>
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
  <div class="container">
    <div class="breadcrumb">
      <a href="<?= SITE_URL ?>/index">Home</a><span class="separator"><i class="bi bi-chevron-right"></i></span>
      <span class="current">My Wishlist</span>
    </div>
    <h1 style="font-size:2rem; font-weight:800; margin-bottom:2rem;">
      <i class="bi bi-heart" style="color:var(--secondary);"></i> My Wishlist
      <span style="font-size:1rem; font-weight:500; color:var(--text-muted);">(<?= count($items) ?> items)</span>
    </h1>
    <?php if(empty($items)): ?>
    <div class="glass-card" style="text-align:center; padding:5rem 2rem;">
      <div style="font-size:5rem; margin-bottom:1.5rem;">💔</div>
      <h2 style="margin-bottom:0.75rem;">Your wishlist is empty</h2>
      <p style="color:var(--text-muted); margin-bottom:2rem;">Save items you love and come back later</p>
      <a href="<?= SITE_URL ?>/products" class="btn-primary-luxury"><i class="bi bi-bag"></i> Explore Products</a>
    </div>
    <?php else: ?>
    <div class="products-grid">
      <?php foreach($items as $p):
        $firstImg = getProductFirstImage($p['images']);
        $price = $p['discount_percent'] > 0 ? getDiscountedPrice($p['price'], $p['discount_percent']) : $p['price'];
      ?>
      <div class="glass-card" style="padding:0; overflow:hidden; position:relative;" id="wl-<?= $p['id'] ?>">
        <a href="<?= SITE_URL ?>/product/<?= $p['slug'] ?>">
          <img src="<?= UPLOAD_URL . $firstImg ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'" style="width:100%; height:200px; object-fit:cover;" alt="">
        </a>
        <button onclick="removeFromWishlist(<?= $p['id'] ?>)" style="position:absolute; top:0.75rem; right:0.75rem; background:rgba(255,101,132,0.2); border:1px solid rgba(255,101,132,0.4); border-radius:50%; width:34px; height:34px; color:var(--secondary); cursor:pointer; display:flex; align-items:center; justify-content:center;">
          <i class="bi bi-heart-fill"></i>
        </button>
        <div style="padding:1rem;">
          <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.25rem;"><?= htmlspecialchars($p['cat_name']) ?></div>
          <a href="<?= SITE_URL ?>/product/<?= $p['slug'] ?>" style="text-decoration:none; color:var(--text-primary);">
            <div style="font-weight:700; margin-bottom:0.5rem; font-size:0.9rem; line-height:1.4;"><?= htmlspecialchars($p['name']) ?></div>
          </a>
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
              <div style="font-weight:800; font-size:1.1rem; background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;"><?= formatPrice($price) ?></div>
              <?php if($p['discount_percent'] > 0): ?><div style="font-size:0.75rem; text-decoration:line-through; color:var(--text-muted);"><?= formatPrice($p['price']) ?></div><?php endif; ?>
            </div>
            <button onclick="addToCart(<?= $p['id'] ?>)" class="btn-primary-luxury" style="padding:0.5rem 1rem; font-size:0.8rem;" <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>
              <i class="bi bi-bag-plus"></i>
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
function removeFromWishlist(productId) {
  fetch('<?= SITE_URL ?>/ajax/wishlist.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'product_id='+productId })
    .then(r=>r.json()).then(d=>{ if(d.success){ document.getElementById('wl-'+productId)?.remove(); showToast('Removed from wishlist','info'); } });
}
</script>

<?php include 'includes/bottom_nav.php'; ?>
</body>
</html>
