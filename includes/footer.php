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
      <div class="footer-bottom-grid">
        <div class="footer-copyright">
          <p class="copyright-text">© <?= date('Y') ?> LuxeStore. All rights reserved.</p>
          <div class="legal-links">
            <a href="<?= SITE_URL ?>/privacy">Privacy Policy</a>
            <span>•</span>
            <a href="<?= SITE_URL ?>/terms">Terms of Service</a>
          </div>
        </div>
        
        <div class="footer-trust">
          <div class="payment-section">
             <div class="trust-title">Secure Payments</div>
             <div class="payment-badges">
               <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa" class="trust-img-visa">
               <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="Mastercard" class="trust-img-mc">
               <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" alt="PayPal" class="trust-img-pp">
               <span class="razorpay-text">RAZORPAY</span>
             </div>
          </div>
          <div class="trust-divider mobile-hide"></div>
          <div class="ssl-section">
            <i class="bi bi-shield-lock-fill"></i>
            <div class="ssl-text">
              <div class="ssl-label">SSL</div>
              <div class="ssl-status">ENCRYPTED</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</footer>
