<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

requireUserLogin();
$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
$uid = $currentUser['id'];

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validateCsrf();
  $name = $_POST['name'] ?? '';
  $phone = $_POST['phone'] ?? '';
  $address = $_POST['address'] ?? '';
  $city = $_POST['city'] ?? '';
  $state = $_POST['state'] ?? '';
  $pincode = $_POST['pincode'] ?? '';
  $oldPw = $_POST['old_password'] ?? '';
  $newPw = $_POST['new_password'] ?? '';

  $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, address=?, city=?, state=?, pincode=? WHERE id=?");
  $stmt->execute([$name, $phone, $address, $city, $state, $pincode, $uid]);

  if ($oldPw && $newPw) {
    if (password_verify($oldPw, $currentUser['password'])) {
      if (strlen($newPw) >= 8) {
        $hash = password_hash($newPw, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
        $success = 'Password updated successfully!';
      } else {
        $error = 'New password must be at least 8 characters.';
      }
    } else {
      $error = 'Current password is incorrect.';
    }
  } else {
    $success = 'Profile updated successfully!';
  }
  $currentUser = getCurrentUser($pdo);
}

$stmtWl = $pdo->prepare("SELECT p.*, c.name as cat_name FROM wishlist w LEFT JOIN products p ON w.product_id=p.id LEFT JOIN categories c ON p.category_id=c.id WHERE w.user_id=? AND p.is_active=1");
$stmtWl->execute([$uid]);
$wishlist = $stmtWl->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile – MIZ MAX</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
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
  <div class="container-sm">
    <h1 style="font-size:2rem; font-weight:800; margin-bottom:2rem;"><i class="bi bi-person-circle"
        style="color:var(--primary);"></i> My Profile</h1>
    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $success ?></div><?php endif; ?>

    <div class="profile-layout-grid">
      <!-- Profile Card -->
      <div>
        <div class="glass-card" style="padding:2rem; text-align:center;">
          <div
            style="width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--accent2)); display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:800; margin:0 auto 1rem;">
            <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
          </div>
          <div style="font-weight:800; font-size:1.1rem;"><?= htmlspecialchars($currentUser['name']) ?></div>
          <div style="color:var(--text-muted); font-size:0.85rem;"><?= $currentUser['email'] ?></div>
          <div style="color:var(--text-muted); font-size:0.8rem; margin-top:0.25rem;">Member since
            <?= date('M Y', strtotime($currentUser['created_at'])) ?></div>
          <div class="grid-2" style="gap:0.75rem; margin-top:1.5rem;">
            <a href="orders"
              style="background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.875rem; text-decoration:none; text-align:center; transition:var(--transition);">
              <div style="font-size:1.5rem; font-weight:800; color:var(--primary);">
                <?php
                $cntStmt = $pdo->prepare("SELECT COUNT(*) as c FROM orders WHERE user_id=?");
                $cntStmt->execute([$uid]);
                echo $cntStmt->fetch()['c'];
                ?>
              </div>
              <div style="font-size:0.75rem; color:var(--text-muted);">Orders</div>
            </a>
            <a href="wishlist"
              style="background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:0.875rem; text-decoration:none; text-align:center; transition:var(--transition);">
              <div style="font-size:1.5rem; font-weight:800; color:var(--secondary);"><?= count($wishlist) ?></div>
              <div style="font-size:0.75rem; color:var(--text-muted);">Wishlist</div>
            </a>
          </div>
        </div>
      </div>

      <!-- Edit Form -->
      <div>
        <div class="glass-card" style="padding:2rem;">
          <h3 style="font-weight:800; margin-bottom:1.5rem;">Edit Profile</h3>
          <form method="POST">
            <?= csrfField() ?>
            <div class="grid-2">
              <div class="form-group" style="margin-bottom:0;grid-column:1/-1;"><label class="form-label">Full
                  Name</label><input type="text" name="name" class="form-control"
                  value="<?= htmlspecialchars($currentUser['name']) ?>" required></div>
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">Phone</label><input type="tel"
                  name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>"></div>
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">City</label><input type="text"
                  name="city" class="form-control" value="<?= htmlspecialchars($currentUser['city'] ?? '') ?>"></div>
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">State</label><input type="text"
                  name="state" class="form-control" value="<?= htmlspecialchars($currentUser['state'] ?? '') ?>"></div>
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">Pincode</label><input
                  type="text" name="pincode" class="form-control"
                  value="<?= htmlspecialchars($currentUser['pincode'] ?? '') ?>"></div>
              <div class="form-group" style="margin-bottom:0;grid-column:1/-1;"><label
                  class="form-label">Address</label><textarea name="address" class="form-control"
                  rows="2"><?= htmlspecialchars($currentUser['address'] ?? '') ?></textarea></div>
              <div style="grid-column:1/-1;border-top:1px solid var(--border);padding-top:1.25rem;margin-top:0.5rem;">
                <div style="font-weight:700; margin-bottom:0.875rem; font-size:0.9rem; color:var(--text-secondary);">
                  Change Password (Optional)</div>
                <div class="grid-2">
                  <div class="form-group" style="margin-bottom:0;"><label class="form-label">Current
                      Password</label><input type="password" name="old_password" class="form-control"
                      placeholder="Current password"></div>
                  <div class="form-group" style="margin-bottom:0;"><label class="form-label">New Password</label><input
                      type="password" name="new_password" class="form-control" placeholder="Min 8 characters"></div>
                </div>
              </div>
            </div>
            <button type="submit" class="btn-primary-luxury" style="margin-top:1.5rem;"><i class="bi bi-save"></i> Save
              Changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
<?php include 'includes/bottom_nav.php'; ?>
</body>

</html>