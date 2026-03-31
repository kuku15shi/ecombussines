<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
?>
<div class="bottom-nav">
  <a href="<?= SITE_URL ?>/index" class="bottom-nav-item <?= ($currentPage == 'index.php' || strpos($_SERVER['REQUEST_URI'], '/index') !== false) ? 'active' : '' ?>">
    <i class="bi <?= ($currentPage == 'index.php' || strpos($_SERVER['REQUEST_URI'], '/index') !== false) ? 'bi-house-heart-fill' : 'bi-house-heart' ?>"></i>
    <span>Home</span>
  </a>
  <a href="<?= SITE_URL ?>/products" class="bottom-nav-item <?= $currentPage == 'products.php' ? 'active' : '' ?>">
    <i class="bi <?= $currentPage == 'products.php' ? 'bi-grid-fill' : 'bi-grid' ?>"></i>
    <span>Shop</span>
  </a>
  <a href="<?= SITE_URL ?>/videos.php" class="bottom-nav-item <?= $currentPage == 'videos.php' ? 'active' : '' ?>">
    <i class="bi <?= $currentPage == 'videos.php' ? 'bi-play-btn-fill' : 'bi-play-btn' ?>"></i>
    <span>Videos</span>
  </a>
  <a href="<?= SITE_URL ?>/cart" class="bottom-nav-item <?= $currentPage == 'cart.php' ? 'active' : '' ?>">
    <i class="bi <?= $currentPage == 'cart.php' ? 'bi-bag-heart-fill' : 'bi-bag-heart' ?>"></i>
    <?php if($cartCount > 0): ?>
      <span class="badge-count"><?= $cartCount ?></span>
    <?php endif; ?>
    <span>Cart</span>
  </a>
  <a href="<?= SITE_URL ?>/profile" class="bottom-nav-item <?= $currentPage == 'profile.php' ? 'active' : '' ?>">
    <i class="bi <?= $currentPage == 'profile.php' ? 'bi-person-fill' : 'bi-person' ?>"></i>
    <span>Profile</span>
  </a>
</div>
