<?php
// Product Card Partial - included from product listing loops
// $p must be set before including this file
$imgs = getProductImages($p['images'] ?? '');
$firstImg = $imgs[0];
$discountedPrice = $p['discount_percent'] > 0 ? getDiscountedPrice($p['price'], $p['discount_percent']) : $p['price'];
$inWishlist = isUserLoggedIn() ? isInWishlist($pdo, $_SESSION['user_id'], $p['id']) : false;
$rating = round($p['rating'] ?? 0, 1);
$fullStars = floor($rating);
$halfStar = ($rating - $fullStars) >= 0.5;
?>
<div class="product-card">
  <div class="product-image-wrap">
    <a href="<?= SITE_URL ?>/product/<?= htmlspecialchars($p['slug']) ?>">
      <img src="<?= UPLOAD_URL . htmlspecialchars($firstImg) ?>"
           onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'"
           alt="<?= htmlspecialchars($p['name']) ?>"
           loading="lazy">
    </a>

    <!-- Badges -->
    <div class="product-badges">
      <?php if($p['discount_percent'] > 0): ?>
      <span class="badge-discount">-<?= intval($p['discount_percent']) ?>%</span>
      <?php endif; ?>
      <?php if($p['is_top']): ?>
      <span class="badge-top">⭐ Top</span>
      <?php endif; ?>
      <?php if(strtotime($p['created_at']) > strtotime('-7 days')): ?>
      <span class="badge-new">New</span>
      <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="product-actions">
      <button class="action-btn <?= $inWishlist ? 'active' : '' ?>"
              onclick="toggleWishlist(<?= $p['id'] ?>, this)"
              title="Add to Wishlist">
        <i class="bi bi-heart<?= $inWishlist ? '-fill' : '' ?>"></i>
      </button>
      <a href="<?= SITE_URL ?>/product/<?= htmlspecialchars($p['slug']) ?>" class="action-btn" title="Quick View">
        <i class="bi bi-eye"></i>
      </a>
    </div>
  </div>

  <div class="product-info">
    <div class="product-category"><?= htmlspecialchars($p['cat_name'] ?? 'General') ?></div>
    <a href="<?= SITE_URL ?>/product/<?= htmlspecialchars($p['slug']) ?>" style="text-decoration:none;">
      <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
    </a>

    <!-- Rating -->
    <div class="product-rating">
      <div class="stars">
        <?php for($i = 1; $i <= 5; $i++): ?>
        <i class="bi <?= $i <= $fullStars ? 'bi-star-fill' : ($halfStar && $i == $fullStars+1 ? 'bi-star-half' : 'bi-star') ?>"></i>
        <?php endfor; ?>
      </div>
      <span class="rating-text">(<?= $p['review_count'] ?? 0 ?>)</span>
    </div>

    <!-- Price -->
    <div class="product-price">
      <span class="price-current"><?= formatPrice($discountedPrice) ?></span>
      <?php if($p['discount_percent'] > 0): ?>
      <span class="price-original"><?= formatPrice($p['price']) ?></span>
      <?php endif; ?>
    </div>

    <!-- Add to Cart -->
    <?php if(($p['stock'] ?? 0) > 0): ?>
    <button class="btn-add-cart" onclick="addToCart(<?= $p['id'] ?>, this)">
      <i class="bi bi-bag-plus"></i> Add to Cart
    </button>
    <?php else: ?>
    <button class="btn-add-cart" disabled style="opacity:0.5; cursor:not-allowed;">
      <i class="bi bi-x-circle"></i> Out of Stock
    </button>
    <?php endif; ?>
  </div>
</div>
