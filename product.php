<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$slug = $_GET['slug'] ?? '';
$slug = str_replace(' ', '-', $slug); // Handle spaces gracefully
$p = $slug ? getProductBySlug($pdo, $slug) : null;

if (!$p) {
    http_response_code(404);
    include '404.php';
    exit;
}

$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
$inWishlist = $currentUser ? isInWishlist($pdo, $currentUser['id'], $p['id']) : false;

$imgs = getProductImages($p['images']);
$discountedPrice = $p['discount_percent'] > 0 ? getDiscountedPrice($p['price'], $p['discount_percent']) : $p['price'];
$reviews = getReviews($pdo, $p['id']);
$rating = round($p['rating'] ?? 0, 1);
$fullStars = floor($rating);
$halfStar = ($rating - $fullStars) >= 0.5;

// Related products
$related = getRelatedProducts($pdo, $p['category_id'], $p['id'], 4);

// Helper: convert YouTube watch URL -> embed URL
function getVideoEmbed($url) {
    if (empty($url)) return null;
    // YouTube standard & shortened
    if (preg_match('%(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})%', $url, $m)) {
        return ['type' => 'youtube', 'embed' => 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&modestbranding=1'];
    }
    
    // Check if it's a local path (e.g. videos/...)
    $fullUrl = $url;
    if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
        $fullUrl = UPLOAD_URL . $url;
    }

    // Direct video file
    $ext = strtolower(pathinfo(parse_url($fullUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'webm', 'ogg'])) {
        return ['type' => 'file', 'url' => $fullUrl, 'ext' => $ext];
    }
    // Fallback: treat as generic embed (Vimeo, etc.)
    return ['type' => 'iframe', 'embed' => $url];
}
$videoEmbed = getVideoEmbed($p['video_url'] ?? '');

// Fetch Color Variants
$stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
$stmt->execute([$p['id']]);
$variants = $stmt->fetchAll();
$variantMap = [];
foreach($variants as $v) {
    if($v['color']) $variantMap[strtolower(trim($v['color']))] = $v['image'];
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    validateCsrf();
    if (!$currentUser) { header('Location: ' . SITE_URL . '/login?redirect=product/' . urlencode($slug)); exit; }
    $ratingVal = (int)($_POST['rating'] ?? 0);
    $comment = $_POST['comment'] ?? '';
    $uid = $currentUser['id'];
    $pid = $p['id'];
    
    if ($ratingVal >= 1 && $ratingVal <= 5) {
        // Check existing review
        $existStmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id=? AND product_id=?");
        $existStmt->execute([$uid, $pid]);
        
        if (!$existStmt->fetch()) {
            try {
                $pdo->beginTransaction();
                
                $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)")->execute([$pid, $uid, $ratingVal, $comment]);
                
                // Update product rating
                $avgRes = $pdo->prepare("SELECT AVG(rating) as avg, COUNT(*) as cnt FROM reviews WHERE product_id=?");
                $avgRes->execute([$pid]);
                $avgRow = $avgRes->fetch();
                
                $avg = round($avgRow['avg'], 2); 
                $cnt = $avgRow['cnt'];
                
                $pdo->prepare("UPDATE products SET rating=?, review_count=? WHERE id=?")->execute([$avg, $cnt, $pid]);
                
                $pdo->commit();
                $_SESSION['toast'] = ['msg'=>'Review submitted!','type'=>'success'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['toast'] = ['msg'=>'Error: ' . $e->getMessage(),'type'=>'error'];
            }
        } else {
            $_SESSION['toast'] = ['msg'=>'You already reviewed this product','type'=>'warning'];
        }
        header('Location: ' . SITE_URL . '/product/' . urlencode($slug));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($p['name']) ?> – LuxeStore</title>
  <meta name="description" content="<?= htmlspecialchars($p['short_description'] ?? substr(strip_tags($p['description']), 0, 160)) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
  <style>
    .img-gallery { display:flex; gap:0.75rem; }
    .gallery-thumbnails { display:flex; flex-direction:column; gap:0.5rem; width:80px; }
    .gallery-thumb { width:72px; height:72px; border-radius:var(--radius-sm); overflow:hidden; cursor:pointer; border:2px solid transparent; transition:var(--transition); opacity:0.6; flex-shrink: 0; position:relative; }
    .gallery-thumb.active { border-color:var(--primary); opacity:1; }
    .gallery-thumb img { width:100%; height:100%; object-fit:cover; }
    @media (max-width: 768px) {
      .img-gallery { flex-direction: column-reverse; }
      .gallery-thumbnails { flex-direction: row; width: 100%; overflow-x: auto; padding: 2px 0; }
      .gallery-thumb { width: 64px; height: 64px; }
    }
    .gallery-main { flex:1; aspect-ratio:1; border-radius:var(--radius); overflow:hidden; background:var(--glass); position:relative; display:flex; align-items:center; justify-content:center; }
    .gallery-main img, .gallery-main .video-container { width:100%; height:100%; object-fit:contain; transition:transform 0.4s ease, opacity 0.3s ease; display:none; }
    .gallery-main img.active, .gallery-main .video-container.active { display:block; }
    .gallery-main:hover img.active { transform:scale(1.05); }
    .video-thumb-play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.3); color:#fff; font-size:1.5rem; pointer-events:none; }
    .qty-control { display:flex; align-items:center; gap:0.5rem; }
    .qty-btn { width:36px; height:36px; border-radius:50%; background:var(--glass); border:1px solid var(--glass-border); color:var(--text-primary); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:var(--transition); }
    .qty-btn:hover { background:var(--primary); border-color:var(--primary); }
    .qty-display { width:48px; text-align:center; font-weight:700; font-size:1rem; background:none; border:none; color:var(--text-primary); }
    .review-card { background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); padding:1.25rem; }
    .stars-input { display:flex; gap:0.25rem; }
    .stars-input i { font-size:1.5rem; cursor:pointer; color:var(--text-muted); transition:var(--transition); }
    .stars-input i.active, .stars-input i:hover { color:var(--gold); }
    .tab-btn { background:none; border:none; font-family:var(--font); font-size:0.9rem; font-weight:600; color:var(--text-muted); padding:0.75rem 1.25rem; cursor:pointer; border-bottom:2px solid transparent; transition:var(--transition); }
    .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
    .tab-content { display:none; }
    .tab-content.active { display:block; }
  </style>
</head>
<!-- MOBILE HEADER -->
<?php include 'includes/mobile_header.php'; ?>

<?php include 'includes/navbar.php'; ?>

<div class="page-wrapper">
  <div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="<?= SITE_URL ?>/index">Home</a>
      <span class="separator"><i class="bi bi-chevron-right"></i></span>
      <a href="<?= SITE_URL ?>/products">Products</a>
      <span class="separator"><i class="bi bi-chevron-right"></i></span>
      <a href="<?= SITE_URL ?>/category/<?= $p['cat_slug'] ?>"><?= htmlspecialchars($p['cat_name']) ?></a>
      <span class="separator"><i class="bi bi-chevron-right"></i></span>
      <span class="current"><?= htmlspecialchars($p['name']) ?></span>
    </div>

    <!-- Product Detail -->
    <div class="glass-card" style="padding:2rem; margin-bottom:2rem;">
      <div class="product-detail-grid">
        <!-- Image Gallery -->
        <div class="img-gallery">
          <?php if(count($imgs) > 1 || $videoEmbed): ?>
          <div class="gallery-thumbnails">
            <?php foreach($imgs as $i => $img): ?>
            <div class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>" onclick="setMainContent('image', '<?= UPLOAD_URL . $img ?>', this)">
              <img src="<?= UPLOAD_URL . $img ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'" alt="">
            </div>
            <?php endforeach; ?>
            
            <?php if($videoEmbed): ?>
            <div class="gallery-thumb position-relative" onclick="setMainContent('video', '', this)">
              <div class="video-thumb-play"><i class="bi bi-play-fill"></i></div>
              <img src="<?= UPLOAD_URL . ($imgs[0] ?? 'default_product.jpg') ?>" style="filter: brightness(0.7);">
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="gallery-main">
            <img src="<?= UPLOAD_URL . $imgs[0] ?>" id="mainImg" class="active" onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'" alt="<?= htmlspecialchars($p['name']) ?>">
            
            <?php if($videoEmbed): ?>
            <div id="mainVideo" class="video-container">
               <?php if($videoEmbed['type'] === 'youtube' || $videoEmbed['type'] === 'iframe'): ?>
               <iframe src="<?= htmlspecialchars($videoEmbed['embed']) ?>" style="width:100%; height:100%; border:0;" allowfullscreen></iframe>
               <?php else: ?>
               <video controls style="width:100%; height:100%;" poster="<?= UPLOAD_URL . ($imgs[0] ?? '') ?>">
                 <source src="<?= htmlspecialchars($videoEmbed['url']) ?>" type="video/<?= $videoEmbed['ext'] ?>">
               </video>
               <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Product Info -->
        <div>
          <div style="font-size:0.78rem; color:var(--primary); font-weight:700; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:0.5rem;"><?= htmlspecialchars($p['cat_name']) ?></div>
          <h1 style="font-size:1.8rem; font-weight:800; line-height:1.2; margin-bottom:0.75rem;"><?= htmlspecialchars($p['name']) ?></h1>

          <!-- Rating -->
          <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1.25rem;">
            <div class="stars">
              <?php for($i=1;$i<=5;$i++): ?>
              <i class="bi <?= $i<=$fullStars?'bi-star-fill':($halfStar&&$i==$fullStars+1?'bi-star-half':'bi-star') ?>" style="color:var(--gold);"></i>
              <?php endfor; ?>
            </div>
            <span style="font-size:0.85rem; color:var(--text-muted);"><?= $rating ?> (<?= count($reviews) ?> reviews)</span>
            <?php if($p['stock'] > 0): ?>
            <span style="font-size:0.8rem; color:var(--success); background:rgba(67,233,123,0.1); padding:0.2rem 0.7rem; border-radius:50px; border:1px solid rgba(67,233,123,0.3);">✓ In Stock (<?= $p['stock'] ?>)</span>
            <?php else: ?>
            <span style="font-size:0.8rem; color:var(--danger); background:rgba(255,101,132,0.1); padding:0.2rem 0.7rem; border-radius:50px; border:1px solid rgba(255,101,132,0.3);">✗ Out of Stock</span>
            <?php endif; ?>
          </div>

          <!-- Price -->
          <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.75rem;">
            <span style="font-size:2.2rem; font-weight:900; background:linear-gradient(135deg,var(--primary),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;"><?= formatPrice($discountedPrice) ?></span>
            <?php if($p['discount_percent'] > 0): ?>
            <span style="font-size:1.1rem; color:var(--text-muted); text-decoration:line-through;"><?= formatPrice($p['price']) ?></span>
            <span style="background:linear-gradient(135deg,var(--secondary),var(--accent2)); color:#fff; font-size:0.8rem; font-weight:700; padding:0.3rem 0.8rem; border-radius:50px;"><?= intval($p['discount_percent']) ?>% OFF</span>
            <?php endif; ?>
          </div>

          <?php if($p['short_description']): ?>
          <p style="color:var(--text-secondary); line-height:1.7; margin-bottom:1.5rem;"><?= htmlspecialchars($p['short_description']) ?></p>
          <?php endif; ?>

          <!-- Affiliate Tools (Only for logged-in affiliates) -->
          <?php 
          require_once __DIR__ . '/config/affiliate_auth.php';
          if (isAffiliateLoggedIn()): 
              $affId = $_SESSION['affiliate_id'];
              $stmtAff = $pdo->prepare("SELECT referral_code, commission_rate FROM affiliates WHERE id = ?");
              $stmtAff->execute([$affId]);
              $affData = $stmtAff->fetch();
              
              if ($affData):
                $affCode = $affData['referral_code'];
                $affLink = SITE_URL . "/product.php?slug=" . $p['slug'] . "&ref=" . $affCode;
                
                // Determine commission for this product
                $itemComm = ($p['affiliate_commission'] !== null) ? (float)$p['affiliate_commission'] : (float)($affData['commission_rate'] ?: (defined('AFFILIATE_COMMISSION_PERCENT') ? AFFILIATE_COMMISSION_PERCENT : 10));
          ?>
          <div class="glass-card mb-4" style="border: 2px dashed var(--primary); background: rgba(108, 99, 255, 0.05); padding: 1.25rem;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--primary); letter-spacing: 1px;"><i class="bi bi-gem me-1"></i> AFFILIATE TOOLS</span>
                <span class="badge bg-success" style="font-size: 0.65rem;">Earn <?= formatPrice($discountedPrice * ($itemComm / 100)) ?></span>
            </div>
            <div class="input-group mb-2">
                <input type="text" class="form-control form-control-sm border-0 bg-white" id="affLinkInput" value="<?= $affLink ?>" readonly style="font-size: 0.8rem; border-radius: 8px 0 0 8px;">
                <button class="btn btn-primary btn-sm" onclick="copyAffLink()" style="border-radius: 0 8px 8px 0;">Copy</button>
            </div>
            <div class="d-flex gap-2">
                <a href="https://wa.me/?text=Check this out: <?= urlencode($affLink) ?>" target="_blank" class="btn btn-outline-success btn-sm flex-grow-1" style="font-size: 0.75rem; border-radius: 6px;"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode($affLink) ?>" target="_blank" class="btn btn-outline-info btn-sm flex-grow-1" style="font-size: 0.75rem; border-radius: 6px;"><i class="bi bi-twitter-x"></i> Tweet</a>
            </div>
          </div>
          <script>
            function copyAffLink() {
                const copyText = document.getElementById("affLinkInput");
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(copyText.value).then(() => {
                    showToast('Affiliate link copied!', 'success');
                });
            }
          </script>
          <?php endif; endif; ?>

          <!-- Color Variant Images (Thumbnails) -->
          <?php if(!empty($variants)): ?>
          <div class="variant-selector" style="margin-bottom:1.5rem;">
            <div class="variant-label">MORE COLORS</div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
              <?php foreach($variants as $v): ?>
              <div class="color-thumb-wrapper" onclick="selectVariant('color', '<?= htmlspecialchars($v['color']) ?>', document.querySelector('.color-option[title=\'<?= htmlspecialchars($v['color']) ?>\']'))" style="cursor:pointer; width:60px; height:80px; border-radius:8px; overflow:hidden; border:2px solid transparent; transition:var(--transition); background:var(--glass);">
                <img src="<?= UPLOAD_URL.$v['image'] ?>" style="width:100%; height:100%; object-fit:cover;" alt="<?= htmlspecialchars($v['color']) ?>">
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <style>
            .color-thumb-wrapper:hover { border-color:var(--primary); transform:translateY(-2px); }
            .color-thumb-wrapper.active { border-color:var(--primary); box-shadow:0 4px 12px rgba(108,99,255,0.2); }
          </style>
          <?php endif; ?>

          <!-- Fashion Variants -->
          <?php if(!empty($p['sizes'])): 
            $sizeList = explode(',', $p['sizes']); ?>
          <div class="variant-selector">
            <div class="variant-label">
              <span>Select Size</span>
              <?php if(!empty($p['size_chart'])): ?>
              <span class="size-chart-link" onclick="toggleSizeChart()"><i class="bi bi-rulers"></i> Size Chart</span>
              <?php endif; ?>
            </div>
            <div class="variant-options" id="sizeOptions">
              <?php foreach($sizeList as $s): $s = trim($s); if(!$s) continue; ?>
              <div class="size-chip" onclick="selectVariant('size', '<?= $s ?>', this)"><?= $s ?></div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" id="selectedSize" value="">
          </div>
          <?php endif; ?>

          <?php if(!empty($p['colors'])): 
            $colorList = explode(',', $p['colors']); ?>
          <div class="variant-selector">
            <div class="variant-label">Select Color</div>
            <div class="variant-options" id="colorOptions">
              <?php foreach($colorList as $c): $c = trim($c); if(!$c) continue; ?>
              <div class="color-option" onclick="selectVariant('color', '<?= $c ?>', this)" title="<?= $c ?>">
                <span style="background-color: <?= $c ?>; border: 1px solid rgba(0,0,0,0.1);"></span>
              </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" id="selectedColor" value="">
          </div>
          <?php endif; ?>

          <!-- Quantity + Cart -->
          <?php if($p['stock'] > 0): ?>
          <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
            <div class="qty-control">
              <button class="qty-btn" onclick="changeQty(-1)"><i class="bi bi-dash"></i></button>
              <input type="number" class="qty-display" id="qtyInput" value="1" min="1" max="<?= $p['stock'] ?>">
              <button class="qty-btn" onclick="changeQty(1)"><i class="bi bi-plus"></i></button>
            </div>
            <button class="btn-primary-luxury" onclick="addToCartWithQty(<?= $p['id'] ?>)" style="flex:1; justify-content:center;">
              <i class="bi bi-bag-plus"></i> Add to Cart
            </button>
          </div>
          <div style="display:flex; gap:0.75rem; margin-bottom:1.5rem;">
            <button class="btn-outline-luxury <?= $inWishlist?'active':'' ?>" id="wishlistBtn" onclick="toggleWishlistDetail(<?= $p['id'] ?>)" style="flex:1; justify-content:center;">
              <i class="bi bi-heart<?= $inWishlist?'-fill':'' ?>"></i> <?= $inWishlist?'Wishlisted':'Wishlist' ?>
            </button>
            <a href="<?= SITE_URL ?>/cart" class="btn-outline-luxury" style="flex:1; justify-content:center;">
              <i class="bi bi-bag"></i> View Cart
            </a>
          </div>
          <?php endif; ?>

          <!-- Safety & Security Badges -->
          <div style="margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border);">
            <div style="font-size:0.75rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
              <i class="bi bi-shield-lock" style="color:var(--primary);"></i> SAFETY & SECURITY
            </div>
            <div class="grid-2" style="gap:1rem;">
              <div style="display:flex; align-items:center; gap:0.6rem; background:rgba(108,99,255,0.05); border:1px solid rgba(108,99,255,0.1); border-radius:12px; padding:0.75rem 1rem;">
                <i class="bi bi-shield-check" style="color:var(--primary); font-size:1.2rem;"></i>
                <div style="font-size:0.8rem; font-weight:600; color:var(--text-primary);">Secure Checkout</div>
              </div>
              <div style="display:flex; align-items:center; gap:0.6rem; background:rgba(67,233,123,0.05); border:1px solid rgba(67,233,123,0.1); border-radius:12px; padding:0.75rem 1rem;">
                <i class="bi bi-person-check" style="color:var(--success); font-size:1.2rem;"></i>
                <div style="font-size:0.8rem; font-weight:600; color:var(--text-primary);">Buyer Protection</div>
              </div>
              <div style="display:flex; align-items:center; gap:0.6rem; background:rgba(255,193,7,0.05); border:1px solid rgba(255,193,7,0.1); border-radius:12px; padding:0.75rem 1rem;">
                <i class="bi bi-database-lock" style="color:var(--gold); font-size:1.2rem;"></i>
                <div style="font-size:0.8rem; font-weight:600; color:var(--text-primary);">Data Encrypted</div>
              </div>
              <div style="display:flex; align-items:center; gap:0.6rem; background:rgba(108,99,255,0.05); border:1px solid rgba(108,99,255,0.1); border-radius:12px; padding:0.75rem 1rem;">
                <i class="bi bi-award" style="color:var(--primary); font-size:1.2rem;"></i>
                <div style="font-size:0.8rem; font-weight:600; color:var(--text-primary);">100% Authentic</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs: Description / Specs / Reviews -->
    <div class="glass-card" style="padding:0; margin-bottom:2rem; overflow:hidden;">
      <div style="display:flex; border-bottom:1px solid var(--border); padding:0 1.5rem; flex-wrap:wrap;">
        <button class="tab-btn active" onclick="switchTab('desc',this)">Description</button>
        <?php if($videoEmbed): ?>
        <button class="tab-btn" id="videoTabBtn" onclick="switchTab('video',this)"><i class="bi bi-play-circle-fill" style="color:var(--primary); margin-right:0.3rem;"></i>Video</button>
        <?php endif; ?>
        <?php if(!empty($p['model_3d'])): ?>
        <button class="tab-btn" id="modelTabBtn" onclick="switchTab('3d',this); init3DViewer();"><i class="bi bi-box" style="color:var(--primary); margin-right:0.3rem;"></i>3D View</button>
        <?php endif; ?>
        <button class="tab-btn" onclick="switchTab('reviews',this)">Reviews (<?= count($reviews) ?>)</button>
      </div>
      <div style="padding:2rem;">
        <div id="tab-desc" class="tab-content active">
          <div style="color:var(--text-secondary); line-height:1.8; font-size:0.95rem;">
            <?= nl2br(htmlspecialchars($p['description'] ?? 'No description available.')) ?>
          </div>
          <?php if($p['sku']): ?>
          <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--border);">
            <div style="font-size:0.8rem; color:var(--text-muted);">SKU: <?= htmlspecialchars($p['sku']) ?></div>
            <?php if($p['weight']): ?>
            <div style="font-size:0.8rem; color:var(--text-muted);">Weight: <?= $p['weight'] ?> kg</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <?php if($videoEmbed): ?>
        <div id="tab-video" class="tab-content">
          <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
            <i class="bi bi-camera-video" style="color:var(--primary);"></i>
            Product Video
          </div>
          <?php if($videoEmbed['type'] === 'youtube' || $videoEmbed['type'] === 'iframe'): ?>
          <div style="position:relative; width:100%; padding-bottom:56.25%; border-radius:var(--radius); overflow:hidden; background:#000;">
            <iframe
              src="<?= htmlspecialchars($videoEmbed['embed']) ?>"
              style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
              loading="lazy"
              title="<?= htmlspecialchars($p['name']) ?> video">
            </iframe>
          </div>
          <?php elseif($videoEmbed['type'] === 'file'): ?>
          <div style="border-radius:var(--radius); overflow:hidden; background:#000;">
            <video
              controls
              preload="metadata"
              style="width:100%; display:block; max-height:500px;"
              poster="<?= UPLOAD_URL . ($imgs[0] ?? '') ?>">
              <source src="<?= htmlspecialchars($videoEmbed['url']) ?>" type="video/<?= $videoEmbed['ext'] === 'mp4' ? 'mp4' : ($videoEmbed['ext'] === 'webm' ? 'webm' : 'ogg') ?>">
              Your browser does not support the video tag.
            </video>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if(!empty($p['model_3d'])): ?>
        <div id="tab-3d" class="tab-content">
          <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
            <i class="bi bi-box" style="color:var(--primary);"></i>
            3D Interactive Experience
          </div>
          <div id="canvas-container" style="width:100%; height:500px; background:#f0f0f0; border-radius:var(--radius); overflow:hidden; position:relative; cursor:grab;">
            <div id="loader-3d" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.8); z-index:10;">
                <div class="spinner-border text-primary" role="status"></div>
                <span class="ms-2">Loading 3D model...</span>
            </div>
          </div>
          <div style="margin-top:1rem; display:flex; gap:1rem; justify-content:center;">
             <span style="font-size:0.75rem; color:var(--text-muted);"><i class="bi bi-mouse me-1"></i> Left click: Rotate</span>
             <span style="font-size:0.75rem; color:var(--text-muted);"><i class="bi bi-mouse2 me-1"></i> Right click: Pan</span>
             <span style="font-size:0.75rem; color:var(--text-muted);"><i class="bi bi-arrows-expand me-1"></i> Scroll: Zoom</span>
          </div>
        </div>
        <script type="importmap">
          {
            "imports": {
              "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
              "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
            }
          }
        </script>
        <script type="module">
          import * as THREE from 'three';
          import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
          import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

          let scene, camera, renderer, controls, loader;
          let model3d_url = '<?= UPLOAD_URL . $p['model_3d'] ?>';
          let initialized = false;

          window.init3DViewer = function() {
            if (initialized) return;
            const container = document.getElementById('canvas-container');
            const loaderEl = document.getElementById('loader-3d');
            
            // Scene
            scene = new THREE.Scene();
            scene.background = new THREE.Color(0xf8f9fa);
            
            // Camera
            camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 1000);
            camera.position.set(0, 1, 5);
            
            // Renderer
            renderer = new THREE.WebGLRenderer({ antialias: true });
            renderer.setSize(container.clientWidth, container.clientHeight);
            renderer.setPixelRatio(window.devicePixelRatio);
            renderer.toneMapping = THREE.ACESFilmicToneMapping;
            renderer.toneMappingExposure = 1;
            container.appendChild(renderer.domElement);
            
            // Lights
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
            scene.add(ambientLight);
            
            const dirLight = new THREE.DirectionalLight(0xffffff, 1);
            dirLight.position.set(10, 10, 5);
            scene.add(dirLight);
            
            const spotLight = new THREE.SpotLight(0xffffff, 1);
            spotLight.position.set(0, 10, 0);
            scene.add(spotLight);

            // Controls
            controls = new OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            
            // Loader
            loader = new GLTFLoader();
            loader.load(model3d_url, (gltf) => {
              const model = gltf.scene;
              
              // Center model
              const box = new THREE.Box3().setFromObject(model);
              const center = box.getCenter(new THREE.Vector3());
              const size = box.getSize(new THREE.Vector3());
              
              model.position.x += (model.position.x - center.x);
              model.position.y += (model.position.y - center.y);
              model.position.z += (model.position.z - center.z);
              
              // Scale to fit
              const maxDim = Math.max(size.x, size.y, size.z);
              const scale = 2 / maxDim;
              model.scale.set(scale, scale, scale);
              
              scene.add(model);
              loaderEl.style.display = 'none';
              initialized = true;
              animate();
            }, undefined, (error) => {
              console.error(error);
              loaderEl.innerHTML = '<span class="text-danger">Failed to load 3D model</span>';
            });
            
            window.addEventListener('resize', () => {
              camera.aspect = container.clientWidth / container.clientHeight;
              camera.updateProjectionMatrix();
              renderer.setSize(container.clientWidth, container.clientHeight);
            });
          }

          function animate() {
            requestAnimationFrame(animate);
            controls.update();
            renderer.render(scene, camera);
          }
        </script>
        <?php endif; ?>

        <div id="tab-reviews" class="tab-content">
          <!-- Review Stats -->
          <div style="display:flex; gap:2rem; align-items:center; margin-bottom:2rem; flex-wrap:wrap;">
            <div style="text-align:center;">
              <div style="font-size:4rem; font-weight:900; background:linear-gradient(135deg,var(--gold),#F97316); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; line-height:1;"><?= number_format($rating, 1) ?></div>
              <div class="stars" style="justify-content:center;">
                <?php for($i=1;$i<=5;$i++): ?>
                <i class="bi <?= $i<=$fullStars?'bi-star-fill':($halfStar&&$i==$fullStars+1?'bi-star-half':'bi-star') ?>" style="color:var(--gold);"></i>
                <?php endfor; ?>
              </div>
              <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.25rem;"><?= count($reviews) ?> reviews</div>
            </div>
            <!-- Rating Bars -->
            <div style="flex:1;">
              <?php for($i=5;$i>=1;$i--):
                $cnt = count(array_filter($reviews, fn($r)=>$r['rating']==$i));
                $pct = count($reviews) > 0 ? ($cnt/count($reviews)*100) : 0;
              ?>
              <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.4rem;">
                <span style="font-size:0.8rem; color:var(--text-muted); width:12px;"><?= $i ?></span>
                <i class="bi bi-star-fill" style="color:var(--gold); font-size:0.75rem;"></i>
                <div style="flex:1; height:6px; background:var(--glass); border-radius:3px; overflow:hidden;">
                  <div style="width:<?= $pct ?>%; height:100%; background:linear-gradient(135deg,var(--gold),#F97316); border-radius:3px;"></div>
                </div>
                <span style="font-size:0.78rem; color:var(--text-muted); width:20px;"><?= $cnt ?></span>
              </div>
              <?php endfor; ?>
            </div>
          </div>

          <!-- Write Review -->
          <?php if($currentUser): ?>
          <details style="margin-bottom:1.5rem;">
            <summary style="cursor:pointer; font-weight:700; color:var(--primary); list-style:none; display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
              <i class="bi bi-pencil-square"></i> Write a Review
            </summary>
            <form method="POST" style="background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius); padding:1.5rem; margin-top:0.75rem;">
              <?= csrfField() ?>
              <div class="form-group">
                <label class="form-label">Your Rating *</label>
                <div class="stars-input" id="starsInput">
                  <?php for($i=1;$i<=5;$i++): ?>
                  <i class="bi bi-star" onclick="setReviewRating(<?= $i ?>)" data-val="<?= $i ?>"></i>
                  <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingHidden" required>
              </div>
              <div class="form-group">
                <label class="form-label">Your Review</label>
                <textarea name="comment" class="form-control" rows="4" placeholder="Share your experience with this product..."></textarea>
              </div>
              <button type="submit" name="submit_review" class="btn-primary-luxury">
                <i class="bi bi-send"></i> Submit Review
              </button>
            </form>
          </details>
          <?php endif; ?>

          <!-- Reviews List -->
          <?php if(empty($reviews)): ?>
          <div style="text-align:center; padding:2rem; color:var(--text-muted);">
            <i class="bi bi-chat-square-text" style="font-size:2.5rem; display:block; margin-bottom:0.75rem;"></i>
            No reviews yet. Be the first to review!
          </div>
          <?php else: ?>
          <div style="display:flex; flex-direction:column; gap:1rem;">
            <?php foreach($reviews as $rev): ?>
            <div class="review-card">
              <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                  <div style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--accent2)); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1rem;">
                    <?= strtoupper(substr($rev['user_name'] ?? 'U', 0, 1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:600; font-size:0.875rem;"><?= htmlspecialchars($rev['user_name'] ?? 'Anonymous') ?></div>
                    <div class="stars" style="margin-top:2px;">
                      <?php for($i=1;$i<=5;$i++): ?>
                      <i class="bi bi-star<?= $i<=$rev['rating']?'-fill':'' ?>" style="color:var(--gold); font-size:0.72rem;"></i>
                      <?php endfor; ?>
                    </div>
                  </div>
                </div>
                <span style="font-size:0.75rem; color:var(--text-muted);"><?= timeAgo($rev['created_at']) ?></span>
              </div>
              <?php if($rev['comment']): ?>
              <p style="color:var(--text-secondary); font-size:0.875rem; line-height:1.6;"><?= htmlspecialchars($rev['comment']) ?></p>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Related Products -->
    <?php if(!empty($related)): ?>
    <section>
      <div class="section-header" style="text-align:left; margin-bottom:1.5rem;">
        <h2 class="section-title" style="font-size:1.5rem;">You May Also Like</h2>
      </div>
      <div class="products-grid">
        <?php foreach($related as $p): ?>
        <?php include 'includes/product_card.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>

<?php
if(isset($_SESSION['toast'])) {
    echo '<script>window.addEventListener("load",()=>showToast("'.addslashes($_SESSION['toast']['msg']).'","'.addslashes($_SESSION['toast']['type']).'"));</script>';
    unset($_SESSION['toast']);
}
?>

<?php include 'includes/footer.php'; ?>

<script>
function setMainContent(type, src, thumbEl) {
  const mainImg = document.getElementById('mainImg');
  const mainVideo = document.getElementById('mainVideo');
  
  if (type === 'image') {
    mainImg.src = src;
    mainImg.classList.add('active');
    if (mainVideo) {
      mainVideo.classList.remove('active');
      const videoTag = mainVideo.querySelector('video');
      if (videoTag) videoTag.pause();
      const iframe = mainVideo.querySelector('iframe');
      if (iframe) {
        const temp = iframe.src;
        iframe.src = '';
        iframe.src = temp;
      }
    }
    // Also sync the bottom tabs? Optional but helpful
    // switchTab('desc', document.querySelector('.tab-btn[onclick*="desc"]'));
  } else {
    mainImg.classList.remove('active');
    if (mainVideo) mainVideo.classList.add('active');
    // switchTab('video', document.getElementById('videoTabBtn'));
  }
  
  if (thumbEl) {
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    thumbEl.classList.add('active');
  }
}

function changeQty(delta) {
  const input = document.getElementById('qtyInput');
  const max = parseInt(input.max); const min = parseInt(input.min);
  input.value = Math.max(min, Math.min(max, parseInt(input.value) + delta));
}

function addToCartWithQty(productId) {
  const qty = document.getElementById('qtyInput').value;
  const size = document.getElementById('selectedSize')?.value || '';
  const color = document.getElementById('selectedColor')?.value || '';
  
  // Check if variants are required
  const sizeOpts = document.getElementById('sizeOptions');
  const colorOpts = document.getElementById('colorOptions');
  
  if(sizeOpts && !size) { showToast('Please select a size', 'warning'); return; }
  if(colorOpts && !color) { showToast('Please select a color', 'warning'); return; }

  fetch(_siteUrl + '/ajax/cart.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=add&product_id='+productId+'&qty='+qty+'&size='+encodeURIComponent(size)+'&color='+encodeURIComponent(color)
  }).then(r=>r.json()).then(data=>{
    showToast(data.message, data.success?'success':'error');
    if(data.success && document.getElementById('cartBadge')) document.getElementById('cartBadge').textContent=data.cartCount;
  });
}

const variantMap = <?= json_encode($variantMap) ?>;
const uploadUrl = '<?= UPLOAD_URL ?>';

function selectVariant(type, value, el) {
  if(type === 'size') {
    document.getElementById('selectedSize').value = value;
    document.querySelectorAll('#sizeOptions .size-chip').forEach(c => c.classList.remove('active'));
  } else {
    document.getElementById('selectedColor').value = value;
    document.querySelectorAll('#colorOptions .color-option').forEach(c => c.classList.remove('active'));
    
    const colorLower = value.toLowerCase().trim();
    
    // Update thumbnail active states
    document.querySelectorAll('.color-thumb-wrapper').forEach(tw => tw.classList.remove('active'));
    document.querySelectorAll('.color-thumb-wrapper img').forEach(img => {
      if(img.alt.toLowerCase().trim() === colorLower) {
        img.parentElement.classList.add('active');
      }
    });

    // Switch image if color variant image exists
    if(variantMap[colorLower]) {
      const mainImg = document.getElementById('mainImg');
      if(mainImg) {
        setMainContent('image', uploadUrl + variantMap[colorLower], null);
        mainImg.style.opacity = '0.5';
        setTimeout(() => {
          mainImg.style.opacity = '1';
        }, 150);
      }
    }
  }
  if(el) el.classList.add('active');
}

function toggleSizeChart() {
  const modal = document.getElementById('sizeChartModal');
  if(modal) modal.classList.toggle('active');
  document.body.style.overflow = modal?.classList.contains('active') ? 'hidden' : '';
}

function toggleWishlistDetail(productId) {
  fetch(_siteUrl + '/ajax/wishlist.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'product_id='+productId})
    .then(r=>r.json()).then(data=>{
      if(data.success){
        const btn=document.getElementById('wishlistBtn');
        btn.innerHTML=data.in_wishlist?'<i class="bi bi-heart-fill"></i> Wishlisted':'<i class="bi bi-heart"></i> Wishlist';
        showToast(data.message,'success');
      } else showToast(data.message||'Please login','error');
    });
}

function switchTab(id, el) {
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  el.classList.add('active');
}

function setReviewRating(val) {
  document.getElementById('ratingHidden').value = val;
  document.querySelectorAll('.stars-input i').forEach((star, i) => {
    star.className = 'bi ' + (i < val ? 'bi-star-fill active' : 'bi-star');
    star.style.color = i < val ? 'var(--gold)' : 'var(--text-muted)';
  });
}
</script>
<?php include 'includes/bottom_nav.php'; ?>
</body>
</html>
