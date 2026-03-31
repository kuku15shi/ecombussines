<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Brands';

if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM brands WHERE id = ?")->execute([$id]);
    header('Location: brands.php?success=1'); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save'])){
    validateCsrf();
    $name = $_POST['name'] ?? '';
    $slug = generateSlug($name);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $editId = (int)($_POST['edit_id'] ?? 0);
    
    if($editId) {
        $stmt = $pdo->prepare("UPDATE brands SET name=?, slug=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $slug, $isActive, $editId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO brands (name,slug,is_active) VALUES (?,?,?)");
        $stmt->execute([$name, $slug, $isActive]);
    }
    header('Location: brands.php?success=1'); exit;
}

$brands = $pdo->query("SELECT b.*, COUNT(p.id) as product_count FROM brands b LEFT JOIN products p ON b.id=p.brand_id GROUP BY b.id ORDER BY b.name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Brands – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
</head>
<body>
<div class="admin-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="content-area">
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Brand saved!</div><?php endif; ?>
      <div class="admin-grid-form">
        <!-- Form -->
        <div class="form-card" id="formCard">
          <div style="font-weight:800; margin-bottom:1.25rem;" id="formTitle">➕ Add Brand</div>
          <form method="POST" id="brandForm">
            <?= csrfField() ?>
            <input type="hidden" name="edit_id" id="editId" value="0">
            <div class="form-group"><label class="form-label">Brand Name *</label><input type="text" name="name" id="brandName" class="form-control" required></div>
            <div class="form-group">
                <label class="toggle-switch" style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer;">
                  <input type="checkbox" name="is_active" id="brandActive" checked>
                  <span class="toggle-slider"></span> <span>Active</span>
                </label>
            </div>
            <div style="display:flex; gap:0.75rem;">
              <button type="submit" name="save" class="btn-primary" style="flex:1; justify-content:center;"><i class="bi bi-save"></i> Save</button>
              <button type="button" onclick="resetForm()" class="btn-primary" style="background:var(--glass); color:var(--text-primary); border:1px solid var(--glass-border);">Reset</button>
            </div>
          </form>
        </div>
        <!-- Table -->
        <div class="data-table-card">
          <div class="data-table-header"><div class="data-table-title">All Brands (<?= count($brands) ?>)</div></div>
          <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Brand</th><th>Slug</th><th>Status</th><th>Products</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach($brands as $b): ?>
              <tr>
                <td style="font-weight:700;"><?= htmlspecialchars($b['name']) ?></td>
                <td style="font-size:0.8rem; color:var(--text-muted);"><?= $b['slug'] ?></td>
                <td><span class="badge <?= $b['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $b['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td><span class="badge badge-processing"><?= $b['product_count'] ?></span></td>
                <td>
                  <div style="display:flex; gap:0.4rem;">
                    <button onclick="editBrand(<?= htmlspecialchars(json_encode($b)) ?>)" class="btn-icon btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                    <a href="brands.php?delete=<?= $b['id'] ?>" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Delete this brand?')"><i class="bi bi-trash"></i></a>
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
function editBrand(b) {
  document.getElementById('editId').value = b.id;
  document.getElementById('brandName').value = b.name;
  document.getElementById('brandActive').checked = (b.is_active == 1);
  document.getElementById('formTitle').textContent = '✏️ Edit Brand';
  document.getElementById('formCard').scrollIntoView({behavior:'smooth'});
}
function resetForm() {
  document.getElementById('editId').value = 0;
  document.getElementById('brandForm').reset();
  document.getElementById('formTitle').textContent = '➕ Add Brand';
}
</script>
</body>
</html>
