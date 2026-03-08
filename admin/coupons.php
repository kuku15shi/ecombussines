<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Coupons';

if(isset($_GET['delete'])){ 
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([$id]);
    header('Location: coupons.php?success=1'); exit; 
}

if(isset($_GET['toggle'])){ 
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id=?")->execute([$id]);
    header('Location: coupons.php'); exit; 
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save'])){
    validateCsrf();
    $code = strtoupper($_POST['code'] ?? '');
    $type = $_POST['type'] ?? 'percent';
    $value = (float)$_POST['value'];
    $minOrder = (float)($_POST['min_order'] ?? 0);
    $maxUses = (int)($_POST['max_uses'] ?? 0);
    $expires = $_POST['expires_at'] ?? null;
    $editId = (int)($_POST['edit_id'] ?? 0);

    if($editId) {
        $stmt = $pdo->prepare("UPDATE coupons SET code=?, type=?, value=?, min_order=?, max_uses=?, expires_at=? WHERE id=?");
        $stmt->execute([$code, $type, $value, $minOrder, $maxUses, $expires, $editId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO coupons (code, type, value, min_order, max_uses, expires_at, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$code, $type, $value, $minOrder, $maxUses, $expires]);
    }
    header('Location: coupons.php?success=1'); exit;
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Coupons – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="content-area">
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Saved!</div><?php endif; ?>
      <div class="admin-grid-form">
        <div class="form-card" id="formCard">
          <div style="font-weight:800; margin-bottom:1.25rem;" id="formTitle">🎟 Add Coupon</div>
          <form method="POST" id="couponForm">
            <?= csrfField() ?>
            <input type="hidden" name="edit_id" id="editId" value="0">
            <div class="form-group"><label class="form-label">Coupon Code *</label><input type="text" name="code" id="cCode" class="form-control" placeholder="SAVE20" required style="text-transform:uppercase;"></div>
            <div class="form-grid-2">
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">Type</label><select name="type" id="cType" class="form-control"><option value="percent">Percentage (%)</option><option value="fixed">Fixed (₹)</option></select></div>
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">Value *</label><input type="number" name="value" id="cValue" class="form-control" step="0.01" placeholder="e.g. 15" required></div>
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">Min. Order (₹)</label><input type="number" name="min_order" id="cMin" class="form-control" step="0.01" placeholder="0"></div>
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">Max Uses</label><input type="number" name="max_uses" id="cMax" class="form-control" placeholder="0 = unlimited"></div>
            </div>
            <div class="form-group"><label class="form-label">Expiry Date</label><input type="date" name="expires_at" id="cExp" class="form-control"></div>
            <div style="display:flex; gap:0.75rem;">
              <button type="submit" name="save" class="btn-primary" style="flex:1; justify-content:center;"><i class="bi bi-save"></i> Save</button>
              <button type="button" onclick="resetForm()" class="btn-primary" style="background:var(--glass); color:var(--text-primary); border:1px solid var(--glass-border);">Reset</button>
            </div>
          </form>
        </div>
        <div class="data-table-card">
          <div class="data-table-header"><div class="data-table-title">All Coupons (<?= count($coupons) ?>)</div></div>
          <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Min Order</th><th>Uses</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach($coupons as $c): $exp = $c['expires_at'] ? new DateTime($c['expires_at']) : null; $isExpired = $exp && $exp < new DateTime(); ?>
              <tr>
                <td><code style="background:rgba(108,99,255,0.15); color:var(--primary); padding:0.2rem 0.6rem; border-radius:4px; font-weight:700;"><?= $c['code'] ?></code></td>
                <td><?= ucfirst($c['type']) ?></td>
                <td style="font-weight:700;"><?= $c['type']==='percent' ? $c['value'].'%' : '₹'.$c['value'] ?></td>
                <td><?= $c['min_order'] > 0 ? '₹'.$c['min_order'] : 'Any' ?></td>
                <td><?= $c['used_count'] ?>/<?= $c['max_uses'] ?: '∞' ?></td>
                <td style="font-size:0.8rem;"><?= $exp ? $exp->format('d M Y') : 'No Expiry' ?></td>
                <td>
                  <?php if($isExpired): ?><span class="badge badge-cancelled">Expired</span>
                  <?php elseif(!$c['is_active']): ?><span class="badge badge-inactive">Inactive</span>
                  <?php else: ?><span class="badge badge-active">Active</span><?php endif; ?>
                </td>
                <td>
                  <div style="display:flex; gap:0.4rem;">
                    <button onclick="editCoupon(<?= htmlspecialchars(json_encode($c)) ?>)" class="btn-icon btn-edit"><i class="bi bi-pencil"></i></button>
                    <a href="coupons.php?toggle=<?= $c['id'] ?>" class="btn-icon" title="Toggle"><i class="bi bi-toggle-on"></i></a>
                    <a href="coupons.php?delete=<?= $c['id'] ?>" class="btn-icon btn-delete" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function editCoupon(c) {
  document.getElementById('editId').value = c.id;
  document.getElementById('cCode').value = c.code;
  document.getElementById('cType').value = c.type;
  document.getElementById('cValue').value = c.value;
  document.getElementById('cMin').value = c.min_order;
  document.getElementById('cMax').value = c.max_uses;
  document.getElementById('cExp').value = c.expires_at ? c.expires_at.substring(0,10) : '';
  document.getElementById('formTitle').textContent = '✏️ Edit Coupon';
  document.getElementById('formCard').scrollIntoView({behavior:'smooth'});
}
function resetForm() {
  document.getElementById('editId').value = 0;
  document.getElementById('couponForm').reset();
  document.getElementById('formTitle').textContent = '🎟 Add Coupon';
}
</script>
</body>
</html>
