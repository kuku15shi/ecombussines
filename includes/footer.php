<?php if(!isset($categories)) { $categories = getCategories($pdo); } ?>
<footer class="footer">
  <div class="container">

    <div class="footer-grid">
      <div>
        <div class="footer-brand">✦ LuxeStore</div>
        <p class="footer-text">Your premium destination for luxury shopping. Curated collections, unbeatable prices, and an experience beyond ordinary.</p>
        <div class="social-links">
          <a href="#" class="social-btn"><i class="bi bi-instagram"></i></a>
          <a href="#" class="social-btn"><i class="bi bi-twitter-x"></i></a>
          <a href="#" class="social-btn"><i class="bi bi-facebook"></i></a>
          <a href="#" class="social-btn"><i class="bi bi-youtube"></i></a>
        </div>
      </div>
      <div>
        <div class="footer-title">Quick Links</div>
        <ul class="footer-links">
          <li><a href="<?= SITE_URL ?>/index"><i class="bi bi-chevron-right"></i> Home</a></li>
          <li><a href="<?= SITE_URL ?>/products"><i class="bi bi-chevron-right"></i> Products</a></li>
          <li><a href="<?= SITE_URL ?>/cart"><i class="bi bi-chevron-right"></i> Cart</a></li>
          <li><a href="<?= SITE_URL ?>/orders"><i class="bi bi-chevron-right"></i> Orders</a></li>
          <li><a href="<?= SITE_URL ?>/wishlist"><i class="bi bi-chevron-right"></i> Wishlist</a></li>
        </ul>
      </div>
      <div>
        <div class="footer-title">Categories</div>
        <ul class="footer-links">
          <?php foreach(array_slice($categories,0,5) as $cat): ?>
          <li><a href="<?= SITE_URL ?>/category/<?= $cat['slug'] ?>"><i class="bi bi-chevron-right"></i> <?= htmlspecialchars($cat['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <div class="footer-title">Contact Us</div>
        <ul class="footer-links">
          <li><a href="mailto:support@luxestore.com"><i class="bi bi-envelope"></i> support@luxestore.com</a></li>
          <li><a href="tel:+919876543210"><i class="bi bi-telephone"></i> +91 98765 43210</a></li>
          <li><a href="#"><i class="bi bi-geo-alt"></i> Mumbai, India</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem;">
        <div>
          <p style="margin-bottom:0.5rem; color:var(--text-muted); font-size:0.875rem;">© <?= date('Y') ?> LuxeStore. All rights reserved.</p>
          <div style="display:flex; gap:1rem; align-items:center; font-size:0.75rem; color:var(--text-muted);">
            <a href="#" style="color:inherit; text-decoration:none;">Privacy Policy</a>
            <span>•</span>
            <a href="#" style="color:inherit; text-decoration:none;">Terms of Service</a>
          </div>
        </div>
        
        <div style="display:flex; align-items:center; gap:2rem; flex-wrap:wrap;">
          <div style="text-align:right;">
             <div style="font-size:0.65rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.75rem; letter-spacing:1px;">Secure Payments</div>
             <div style="display:flex; gap:1.25rem; align-items:center;">
               <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa" style="height:14px; width:auto; filter: grayscale(1) opacity(0.6);">
               <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="Mastercard" style="height:22px; width:auto; filter: grayscale(1) opacity(0.6);">
               <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" alt="PayPal" style="height:18px; width:auto; filter: grayscale(1) opacity(0.6);">
               <span style="font-size:0.75rem; font-weight:900; color:var(--text-muted); letter-spacing:-0.2px; opacity:0.6;">RAZORPAY</span>
             </div>
          </div>
          <div class="mobile-hide" style="height:40px; width:1px; background:var(--border);"></div>
          <div style="display:flex; align-items:center; gap:0.6rem; color:var(--success);">
            <i class="bi bi-shield-lock-fill" style="font-size:1.4rem;"></i>
            <div style="line-height:1.1;">
              <div style="font-size:0.6rem; font-weight:900; text-transform:uppercase; letter-spacing:0.5px;">SSL</div>
              <div style="font-size:0.7rem; font-weight:800;">ENCRYPTED</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</footer>
