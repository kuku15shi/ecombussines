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
  <title>Terms of Service – LuxeStore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
      <span class="current">Terms of Service</span>
    </div>

    <div class="glass-card" style="padding: 3rem 2rem;">
      <h1 style="font-size: 2.25rem; font-weight: 900; background: linear-gradient(135deg, var(--primary), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 1.5rem;">Terms of Service</h1>
      <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2.5rem;">Last Updated: <?= date('F d, Y') ?></p>

      <div style="display: flex; flex-direction: column; gap: 2rem; line-height: 1.7; color: var(--text-secondary);">
        <section>
          <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">1. Acceptance of Terms</h2>
          <p>By accessing and using this website, you agree to comply with and be bound by these Terms of Service. If you do not agree to these terms, please refrain from using our website.</p>
        </section>

        <section>
          <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">2. User Accounts</h2>
          <p>You are responsible for maintaining the confidentiality of your account information and for all activities that occur under your account. You agree to provide accurate and complete information when registering an account.</p>
        </section>

        <section>
          <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">3. Products and Pricing</h2>
          <p>All products and prices displayed on the website are subject to availability and change without notice. We make every effort to display accurate information but do not warrant that product descriptions or pricing are error-free.</p>
        </section>

        <section>
          <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">4. Shipping and Returns</h2>
          <p>Shipping times and costs are provided as estimates. Please refer to our returns policy for information regarding exchanging or returning products. We reserve the right to refuse returns that do not comply with our policy.</p>
        </section>

        <section>
          <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">5. Intellectual Property</h2>
          <p>All content on this website, including logos, graphics, and text, is the property of LuxeStore and is protected by copyright and other intellectual property laws.</p>
        </section>

        <section>
          <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">6. Limitation of Liability</h2>
          <p>LuxeStore shall not be liable for any direct, indirect, incidental, or consequential damages resulting from the use or inability to use our website or products.</p>
        </section>

        <section>
          <h2 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">7. Governing Law</h2>
          <p>These Terms of Service are governed by and construed in accordance with the laws of India. Any disputes shall be subject to the exclusive jurisdiction of the courts in Mumbai.</p>
        </section>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/bottom_nav.php'; ?>

</body>
</html>
