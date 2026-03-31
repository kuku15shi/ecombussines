<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$categories = getCategories($pdo);
$banners = getActiveBanners($pdo);
$featuredProducts = getFeaturedProducts($pdo, 8);
$topProducts = getTopProducts($pdo, 8);
$trendingProducts = getTrendingProducts($pdo, 8);

// Fetch Shoppable Reels
$suggestedReels = [];
try {
  $stmt = $pdo->query("SELECT v.*, p.name as product_name, p.slug as product_slug 
                         FROM videos v 
                         LEFT JOIN products p ON v.product_id = p.id 
                         WHERE v.is_active = 1 
                         ORDER BY v.created_at DESC LIMIT 8");
  $suggestedReels = $stmt->fetchAll();
} catch (Exception $e) {
}

$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);

// Category product counts
$catCounts = [];
$stmt = $pdo->query("SELECT category_id, COUNT(*) as cnt FROM products WHERE is_active=1 GROUP BY category_id");
$countsRow = $stmt->fetchAll();
foreach ($countsRow as $row)
  $catCounts[$row['category_id']] = $row['cnt'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MIZ MAX – Premium Online Shopping</title>
  <meta name="description"
    content="Discover premium products at MIZ MAX. Shop electronics, fashion, beauty and more with exclusive deals and fast delivery.">
  <meta name="keywords" content="online shopping, premium store, electronics, fashion, beauty">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
</head>

<body>

  <?php include 'includes/navbar.php'; ?>

  <!-- MOBILE HEADER (App Type) -->
  <?php include 'includes/mobile_header.php'; ?>

  <!-- MAIN CONTENT -->
  <div class="page-wrapper">
    <div class="container">

      <!-- HERO SLIDER -->
      <div class="hero-slider" id="heroSlider">
        <?php if (empty($banners)): ?>
          <!-- Default banners if none in DB -->
          <div class="slide active">
            <div class="slide-bg" style="background: linear-gradient(135deg, #1a1a3e, #6C63FF, #FF6584);"></div>
            <div class="slide-overlay"></div>
            <div class="slide-content">
              <span class="slide-badge">New Season 2026</span>
              <h1 class="slide-title">Discover Premium Collections</h1>
              <p class="slide-subtitle">Shop the latest trends with unbeatable prices and luxury experience</p>
              <a href="<?= SITE_URL ?>/products" class="btn-primary-luxury">Shop Now <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
          <div class="slide">
            <div class="slide-bg" style="background: linear-gradient(135deg, #0a1628, #FA709A, #6C63FF);"></div>
            <div class="slide-overlay"></div>
            <div class="slide-content">
              <span class="slide-badge">Electronics Sale</span>
              <h1 class="slide-title">Up to 40% Off on Gadgets</h1>
              <p class="slide-subtitle">Premium tech products at the best prices — limited time offer</p>
              <a href="<?= SITE_URL ?>/category/electronics" class="btn-primary-luxury">Explore Deals <i
                  class="bi bi-lightning"></i></a>
            </div>
          </div>
          <div class="slide">
            <div class="slide-bg" style="background: linear-gradient(135deg, #0d1117, #43E97B, #38BDF8);"></div>
            <div class="slide-overlay"></div>
            <div class="slide-content">
              <span class="slide-badge">Luxury Collection</span>
              <h1 class="slide-title">Style Meets Sophistication</h1>
              <p class="slide-subtitle">Handpicked luxury items curated for the discerning shopper</p>
              <a href="<?= SITE_URL ?>/products" class="btn-primary-luxury">View Collection <i class="bi bi-gem"></i></a>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($banners as $i => $banner): ?>
            <div class="slide <?= $i === 0 ? 'active' : '' ?>">
              <div class="slide-bg" style="background-image:url('<?= UPLOAD_URL . $banner['image'] ?>')"></div>
              <div class="slide-overlay"></div>
              <div class="slide-content">
                <span class="slide-badge">Featured</span>
                <h1 class="slide-title"><?= htmlspecialchars($banner['title']) ?></h1>
                <p class="slide-subtitle"><?= htmlspecialchars($banner['subtitle']) ?></p>
                <a href="<?= $banner['btn_url'] ?>" class="btn-primary-luxury"><?= $banner['btn_text'] ?> <i
                    class="bi bi-arrow-right"></i></a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Slider Controls -->
        <div class="slider-arrows">
          <button class="slider-arrow" onclick="prevSlide()"><i class="bi bi-chevron-left"></i></button>
          <button class="slider-arrow" onclick="nextSlide()"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="slider-dots" id="sliderDots"></div>
      </div>

      <!-- Categories Scroller (Premium Circular Layout) -->
      <div class="d-md-none" style="margin: 1rem 0 2rem;">
        <div
          style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem; padding:0 0.25rem;">
          <h3 style="font-size:1.25rem; font-weight:900; margin:0; letter-spacing:-0.5px;">Collections</h3>
          <a href="products"
            style="font-size:0.85rem; color:var(--primary); text-decoration:none; font-weight:800;">View All</a>
        </div>
        <div class="premium-cat-scroller">
          <a href="products" class="premium-cat-item">
            <div class="cat-icon-circle"
              style="background: linear-gradient(135deg, var(--primary), var(--accent)); color:#fff; border:none;">
              <i class="bi bi-grid-fill"></i>
            </div>
            <div class="premium-cat-label">All</div>
          </a>
          <?php foreach ($categories as $cat): ?>
            <a href="category/<?= $cat['slug'] ?>" class="premium-cat-item">
              <div class="cat-icon-circle">
                <?php if (strlen($cat['icon']) > 5 || strpos($cat['icon'], 'bi-') !== false): ?>
                  <i class="<?= $cat['icon'] ?>"></i>
                <?php else: ?>
                  <span style="font-style: normal;"><?= $cat['icon'] ?></span>
                <?php endif; ?>
              </div>
              <div class="premium-cat-label"><?= htmlspecialchars($cat['name']) ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- STATS BAR -->
      <div class="grid-4 mobile-stats-row" style="margin:2rem 0;">
        <?php
        $stats = [
          ['icon' => 'bi-truck', 'label' => 'Free Shipping', 'sub' => 'Over ₹999'],
          ['icon' => 'bi-shield-lock', 'label' => 'Secure Pay', 'sub' => 'Verified'],
          ['icon' => 'bi-arrow-repeat', 'label' => 'Return Policy', 'sub' => '30 Days'],
          ['icon' => 'bi-lightning', 'label' => 'Fast Delivery', 'sub' => 'Express'],
        ];
        foreach ($stats as $s):
          ?>
          <div class="glass-card premium-stats-item">
            <div class="stats-icon-box">
              <i class="<?= $s['icon'] ?>"></i>
            </div>
            <div>
              <div class="stats-label"><?= $s['label'] ?></div>
              <div class="stats-sub"><?= $s['sub'] ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- CATEGORIES (Desktop Only) -->
      <section class="d-none d-md-block" style="margin:3rem 0;">
        <div class="section-header">
          <div class="section-badge">Browse</div>
          <h2 class="section-title">Shop by Category</h2>
          <p class="section-sub">Find exactly what you're looking for</p>
        </div>
        <div class="category-grid">
          <?php foreach ($categories as $cat): ?>
            <a href="category/<?= $cat['slug'] ?>" class="category-card">
              <div class="category-icon">
                <?php
                // Check if icon is an emoji (more than 3 bytes usually in UTF-8) or a class
                if (strlen($cat['icon']) > 5 || strpos($cat['icon'], 'bi-') !== false): ?>
                  <i class="<?= $cat['icon'] ?>"></i>
                <?php else: ?>
                  <span class="emoji-icon"><?= $cat['icon'] ?></span>
                <?php endif; ?>
              </div>
              <div class="category-name"><?= htmlspecialchars($cat['name']) ?></div>
              <div class="category-count"><?= $catCounts[$cat['id']] ?? 0 ?> products</div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- FEATURED PRODUCTS -->
      <section style="margin:3rem 0;">
        <div class="section-header-flex">
          <div>
            <div class="section-badge">Handpicked</div>
            <h2 class="section-title">Featured Products</h2>
          </div>
          <a href="products?filter=featured" class="btn-outline-luxury">View All <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if (empty($featuredProducts)): ?>
          <div style="text-align:center; padding:3rem; color:var(--text-muted);">
            <i class="bi bi-box" style="font-size:3rem; display:block; margin-bottom:1rem;"></i>
            <p>No featured products yet. Add products from the admin panel.</p>
            <a href="products.php" class="btn-primary-luxury" style="margin-top:1rem;">Browse All Products</a>
          </div>
        <?php else: ?>
          <div class="products-grid">
            <?php foreach ($featuredProducts as $p): ?>
              <?php include 'includes/product_card.php'; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- TOP PRODUCTS -->
      <?php if (!empty($topProducts)): ?>
        <section style="margin:3rem 0;">
          <div class="section-header-flex">
            <div>
              <div class="section-badge">Best Sellers</div>
              <h2 class="section-title">Top Products</h2>
            </div>
            <a href="products?filter=top" class="btn-outline-luxury">View All <i class="bi bi-arrow-right"></i></a>
          </div>
          <div class="products-grid">
            <?php foreach ($topProducts as $p): ?>
              <?php include 'includes/product_card.php'; ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- SPECIAL OFFER BANNER -->
      <section style="margin:3rem 0;">
        <div class="offer-banner">
          <div class="section-badge" style="border-color:rgba(255,101,132,0.4); color:var(--secondary);">Limited Time
          </div>
          <h2 style="font-size:clamp(1.5rem,3vw,2.5rem); font-weight:800; margin:0.75rem 0;">🔥 Flash Sale — Upto 60%
            Off!</h2>
          <p style="color:var(--text-secondary); margin-bottom:0.5rem;">Use code <strong
              style="color:var(--gold);">LUXE20</strong> for extra 20% off on your first order</p>
          <div class="offer-countdown">
            <div class="countdown-item">
              <div class="countdown-num" id="cdH">00</div>
              <div class="countdown-label">Hours</div>
            </div>
            <div class="countdown-item">
              <div class="countdown-num" id="cdM">00</div>
              <div class="countdown-label">Mins</div>
            </div>
            <div class="countdown-item">
              <div class="countdown-num" id="cdS">00</div>
              <div class="countdown-label">Secs</div>
            </div>
          </div>
          <a href="products" class="btn-primary-luxury"
            style="background:linear-gradient(135deg,var(--secondary),var(--accent2));">
            <i class="bi bi-lightning-fill"></i> Shop the Sale
          </a>
        </div>
      </section>

      <!-- SHOPPABLE REELS FEED -->
      <?php if (!empty($suggestedReels)): ?>
        <section style="margin:3rem 0;">
          <div class="section-header-flex">
            <div>
              <div class="section-badge" style="border-color:rgba(254,44,85,0.4); color:#fe2c55;">Shorts</div>
              <h2 class="section-title">Shoppable Reels</h2>
            </div>
            <a href="videos.php" class="btn-outline-luxury">Watch All <i class="bi bi-play-circle"></i></a>
          </div>
          <style>
            .reels-scroller {
              display: flex;
              overflow-x: auto;
              gap: 15px;
              padding-bottom: 10px;
              scroll-snap-type: x mandatory;
            }

            .reels-scroller::-webkit-scrollbar {
              display: none;
            }

            .reel-card {
              min-width: 150px;
              height: 260px;
              border-radius: 12px;
              background: #111;
              position: relative;
              overflow: hidden;
              scroll-snap-align: start;
              flex-shrink: 0;
              box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
              text-decoration: none;
              display: block;
              border: 1px solid var(--border);
            }

            .reel-video-preview {
              width: 100%;
              height: 100%;
              object-fit: cover;
              opacity: 0.85;
            }

            .reel-card::after {
              content: '';
              position: absolute;
              inset: 0;
              background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0) 60%);
              z-index: 1;
            }

            .reel-content {
              position: absolute;
              bottom: 12px;
              left: 12px;
              right: 12px;
              z-index: 2;
              color: #fff;
            }

            .reel-play-icon {
              position: absolute;
              top: 10px;
              right: 10px;
              z-index: 2;
              color: #fff;
              text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
              font-size: 1.2rem;
            }

            .reel-views {
              font-size: 0.75rem;
              font-weight: 700;
              margin-bottom: 4px;
              display: flex;
              align-items: center;
              gap: 4px;
              color: #ddd;
            }

            .reel-title {
              font-size: 0.85rem;
              font-weight: 700;
              line-height: 1.3;
              overflow: hidden;
              display: -webkit-box;
              -webkit-line-clamp: 2;
              -webkit-box-orient: vertical;
              text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
              color: #fff;
            }
          </style>
          <div class="reels-scroller">
            <?php foreach ($suggestedReels as $reel): ?>
              <a href="videos.php" class="reel-card">
                <video src="<?= htmlspecialchars($reel['video_url']) ?>#t=0.1" class="reel-video-preview" muted playsinline
                  preload="metadata"></video>
                <i class="bi bi-play-fill reel-play-icon"></i>
                <div class="reel-content">
                  <div class="reel-views"><i class="bi bi-play"></i> <?= number_format($reel['likes'] * 3) ?></div>
                  <div class="reel-title"><?= htmlspecialchars($reel['title']) ?></div>
                  <?php if ($reel['product_name']): ?>
                    <div style="font-size:0.75rem; color:#4ade80; font-weight:800; margin-top:6px;"><i
                        class="bi bi-cart-check"></i> 1 Product</div>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- TRENDING PRODUCTS -->
      <?php if (!empty($trendingProducts)): ?>
        <section style="margin:3rem 0;">
          <div class="section-header-flex">
            <div>
              <div class="section-badge">🔥 Hot</div>
              <h2 class="section-title">Trending Now</h2>
            </div>
            <a href="products?filter=trending" class="btn-outline-luxury">View All <i class="bi bi-arrow-right"></i></a>
          </div>
          <div class="products-grid">
            <?php foreach ($trendingProducts as $p): ?>
              <?php include 'includes/product_card.php'; ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- NEWSLETTER -->
      <section style="margin:3rem 0;">
        <div class="newsletter-section">
          <div class="section-badge">Join Us</div>
          <h2 style="font-size:1.8rem; font-weight:800; margin:0.75rem 0;">Stay in the Loop</h2>
          <p style="color:var(--text-secondary); max-width:480px; margin:0 auto;">Get exclusive deals, new arrivals, and
            luxury lifestyle tips delivered to your inbox.</p>
          <form class="newsletter-form" id="newsletterForm" onsubmit="subscribeNewsletter(event)">
            <input type="email" class="newsletter-input" placeholder="Enter your email address" required id="nlEmail">
            <button type="submit" class="btn-primary-luxury" style="white-space:nowrap;">
              <i class="bi bi-envelope-heart"></i> Subscribe
            </button>
          </form>
        </div>
      </section>

    </div><!-- /container -->
  </div><!-- /page-wrapper -->

  <!-- FOOTER -->
  <?php include 'includes/footer.php'; ?>

  <script>
    // Page Loader
    // Page interactions handled by navbar.php


    // Slider
    let currentSlide = 0;
    const slides = document.querySelectorAll('.slide');
    const dotsEl = document.getElementById('sliderDots');
    let autoSlide;

    slides.forEach((_, i) => {
      const d = document.createElement('button');
      d.className = 'slider-dot' + (i === 0 ? ' active' : '');
      d.onclick = () => goToSlide(i);
      dotsEl.appendChild(d);
    });

    function goToSlide(n) {
      slides[currentSlide].classList.remove('active');
      document.querySelectorAll('.slider-dot')[currentSlide].classList.remove('active');
      currentSlide = (n + slides.length) % slides.length;
      slides[currentSlide].classList.add('active');
      document.querySelectorAll('.slider-dot')[currentSlide].classList.add('active');
    }

    function nextSlide() { goToSlide(currentSlide + 1); resetAutoSlide(); }
    function prevSlide() { goToSlide(currentSlide - 1); resetAutoSlide(); }
    function resetAutoSlide() { clearInterval(autoSlide); autoSlide = setInterval(nextSlide, 5000); }
    autoSlide = setInterval(nextSlide, 5000);

    // Common utilities and Core interactions (Cart, Wishlist, Search, Themes) 
    // are all centrally handled by includes/navbar.php scripts.



    // Newsletter
    function subscribeNewsletter(e) {
      e.preventDefault();
      const email = document.getElementById('nlEmail').value;
      fetch('ajax/newsletter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'email=' + encodeURIComponent(email)
      }).then(r => r.json()).then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) document.getElementById('newsletterForm').reset();
      });
    }

    // Countdown Timer (counts to midnight)
    function updateCountdown() {
      const now = new Date();
      const midnight = new Date();
      midnight.setHours(24, 0, 0, 0);
      const diff = Math.max(0, Math.floor((midnight - now) / 1000));
      const h = Math.floor(diff / 3600);
      const m = Math.floor((diff % 3600) / 60);
      const s = diff % 60;
      document.getElementById('cdH').textContent = String(h).padStart(2, '0');
      document.getElementById('cdM').textContent = String(m).padStart(2, '0');
      document.getElementById('cdS').textContent = String(s).padStart(2, '0');
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);
  </script>

  <?php include 'includes/mobile_sheets.php'; ?>
  <?php include 'includes/bottom_nav.php'; ?>
</body>

</html>