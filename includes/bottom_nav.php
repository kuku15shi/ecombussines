<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
?>
<div class="bottom-nav">
  <a href="<?= SITE_URL ?>/index" class="bottom-nav-item <?= $currentPage == 'index.php' ? 'active' : '' ?>">
    <i class="bi <?= $currentPage == 'index.php' ? 'bi-house-heart-fill' : 'bi-house-heart' ?>"></i>
    <span>Home</span>
  </a>
  <a href="<?= SITE_URL ?>/products" class="bottom-nav-item <?= $currentPage == 'products.php' ? 'active' : '' ?>">
    <i class="bi <?= $currentPage == 'products.php' ? 'bi-grid-fill' : 'bi-grid' ?>"></i>
    <span>Shop</span>
  </a>
  <a href="<?= SITE_URL ?>/wishlist" class="bottom-nav-item <?= $currentPage == 'wishlist.php' ? 'active' : '' ?>">
    <i class="bi <?= $currentPage == 'wishlist.php' ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
    <?php if($wishlistCount > 0): ?>
      <span class="badge-count"><?= $wishlistCount ?></span>
    <?php endif; ?>
    <span>Wishlist</span>
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
