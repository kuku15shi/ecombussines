<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Privacy Policy – MIZ MAX</title>
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

  <?php include 'includes/mobile_header.php'; ?>
  <?php include 'includes/navbar.php'; ?>

  <div class="page-wrapper">
    <div class="container-sm">
      <div class="breadcrumb">
        <a href="<?= SITE_URL ?>/index">Home</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Privacy Policy</span>
      </div>

      <div class="glass-card" style="padding: 3rem 2rem;">
        <h1
          style="font-size: 2.25rem; font-weight: 900; background: linear-gradient(135deg, var(--primary), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 1.5rem;">
          Privacy Policy</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2.5rem;">Last Updated:
          <?= date('F d, Y') ?></p>

        <div style="display: flex; flex-direction: column; gap: 2rem; line-height: 1.7; color: var(--text-secondary);">
          <section>
            <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">1.
              Information We Collect</h2>
            <p>We collect information that you provide directly to us when you create an account, make a purchase, or
              communicate with us. This may include your name, email address, phone number, shipping address, and
              payment information.</p>
          </section>

          <section>
            <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">2. How We
              Use Your Information</h2>
            <p>We use the information we collect to:</p>
            <ul style="padding-left: 1.5rem; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.5rem;">
              <li>Process and fulfill your orders</li>
              <li>Send you order confirmations and updates</li>
              <li>Communicate with you about products, services, and promotions</li>
              <li>Improve and personalize your shopping experience</li>
              <li>Protect the security and integrity of our services</li>
            </ul>
          </section>

          <section>
            <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">3. Data
              Security</h2>
            <p>We implement a variety of security measures to maintain the safety of your personal information. Your
              sensitive data (like credit card information) is encrypted and transmitted via Secure Socket Layer (SSL)
              technology.</p>
          </section>

          <section>
            <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">4.
              Cookies and Tracking</h2>
            <p>We use cookies to enhance your experience, remember your cart items, and understand how you use our
              website. You can choose to disable cookies through your browser settings, but some features of our site
              may not function properly.</p>
          </section>

          <section>
            <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">5.
              Third-Party Services</h2>
            <p>We do not sell or trade your personally identifiable information. We may share information with trusted
              third parties who assist us in operating our website, conducting our business, or servicing you, so long
              as those parties agree to keep this information confidential.</p>
          </section>

          <section>
            <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">6.
              Contact Us</h2>
            <p>If you have any questions regarding this privacy policy, you may contact us using the information below:
            </p>
            <p style="margin-top: 1rem; font-weight: 600;">Email: support@MIZ MAX.com</p>
          </section>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/bottom_nav.php'; ?>

</body>

</html>