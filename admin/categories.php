<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Categories';

if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    header('Location: categories.php?success=1'); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save'])){
    validateCsrf();
    $name = $_POST['name'] ?? '';
    $slug = generateSlug($name);
    $desc = $_POST['description'] ?? '';
    $icon = $_POST['icon'] ?? '';
    $editId = (int)($_POST['edit_id'] ?? 0);
    
    if($editId) {
        $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, description=?, icon=? WHERE id=?");
        $stmt->execute([$name, $slug, $desc, $icon, $editId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name,slug,description,icon) VALUES (?,?,?,?)");
        $stmt->execute([$name, $slug, $desc, $icon]);
    }
    header('Location: categories.php?success=1'); exit;
}

$categories = $pdo->query("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id=p.category_id GROUP BY c.id ORDER BY c.name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Categories – Admin</title>
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
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Category saved!</div><?php endif; ?>
      <div class="admin-grid-form">
        <!-- Form -->
        <div class="form-card" id="formCard">
          <div style="font-weight:800; margin-bottom:1.25rem;" id="formTitle">➕ Add Category</div>
          <form method="POST" id="catForm">
            <?= csrfField() ?>
            <input type="hidden" name="edit_id" id="editId" value="0">
            <div class="form-group"><label class="form-label">Category Name *</label><input type="text" name="name" id="catName" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Icon (emoji)</label><input type="text" name="icon" id="catIcon" class="form-control" placeholder="📱"></div>
            <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="catDesc" class="form-control" rows="3" placeholder="Brief category description"></textarea></div>
            <div style="display:flex; gap:0.75rem;">
              <button type="submit" name="save" class="btn-primary" style="flex:1; justify-content:center;"><i class="bi bi-save"></i> Save</button>
              <button type="button" onclick="resetForm()" class="btn-primary" style="background:var(--glass); color:var(--text-primary); border:1px solid var(--glass-border);">Reset</button>
            </div>
          </form>
        </div>
        <!-- Table -->
        <div class="data-table-card">
          <div class="data-table-header"><div class="data-table-title">All Categories (<?= count($categories) ?>)</div></div>
          <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Icon</th><th>Category</th><th>Slug</th><th>Products</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach($categories as $cat): ?>
              <tr>
                <td style="font-size:1.5rem;"><?= $cat['icon'] ?: '📦' ?></td>
                <td style="font-weight:700;"><?= htmlspecialchars($cat['name']) ?></td>
                <td style="font-size:0.8rem; color:var(--text-muted);"><?= $cat['slug'] ?></td>
                <td><span class="badge badge-processing"><?= $cat['product_count'] ?></span></td>
                <td>
                  <div style="display:flex; gap:0.4rem;">
                    <button onclick="editCat(<?= htmlspecialchars(json_encode($cat)) ?>)" class="btn-icon btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                    <a href="categories.php?delete=<?= $cat['id'] ?>" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Delete this category?')"><i class="bi bi-trash"></i></a>
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
function editCat(cat) {
  document.getElementById('editId').value = cat.id;
  document.getElementById('catName').value = cat.name;
  document.getElementById('catIcon').value = cat.icon || '';
  document.getElementById('catDesc').value = cat.description || '';
  document.getElementById('formTitle').textContent = '✏️ Edit Category';
  document.getElementById('formCard').scrollIntoView({behavior:'smooth'});
}
function resetForm() {
  document.getElementById('editId').value = 0;
  document.getElementById('catForm').reset();
  document.getElementById('formTitle').textContent = '➕ Add Category';
}
</script>
</body>
</html>
