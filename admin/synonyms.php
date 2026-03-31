<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Search Synonyms';

if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM synonyms WHERE id = ?")->execute([$id]);
    header('Location: synonyms.php?success=1'); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save'])){
    validateCsrf();
    $keyword = strtolower(trim($_POST['keyword'] ?? ''));
    $synonymsText = trim($_POST['synonyms'] ?? '');
    
    // clean synonyms (comma separated, lowercase, trim)
    $parts = explode(',', $synonymsText);
    $parts = array_map('trim', $parts);
    $parts = array_map('strtolower', $parts);
    $synonymsText = implode(', ', array_filter($parts));

    $editId = (int)($_POST['edit_id'] ?? 0);
    
    if($editId) {
        $stmt = $pdo->prepare("UPDATE synonyms SET keyword=?, synonyms=? WHERE id=?");
        $stmt->execute([$keyword, $synonymsText, $editId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO synonyms (keyword, synonyms) VALUES (?, ?)");
        $stmt->execute([$keyword, $synonymsText]);
    }
    header('Location: synonyms.php?success=1'); exit;
}

$syns = $pdo->query("SELECT * FROM synonyms ORDER BY keyword ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> – Admin</title>
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
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Synonyms saved!</div><?php endif; ?>
      <div class="admin-grid-form" style="display:grid; grid-template-columns: 1fr 2fr; gap:1.5rem;">
        <!-- Form -->
        <div class="form-card" id="formCard">
          <div style="font-weight:800; margin-bottom:1.25rem;" id="formTitle">➕ Add Synonym</div>
          <form method="POST" id="synForm">
            <?= csrfField() ?>
            <input type="hidden" name="edit_id" id="editId" value="0">
            <div class="form-group">
                <label class="form-label">Base Keyword *</label>
                <input type="text" name="keyword" id="synKeyword" class="form-control" required placeholder="e.g. mobile">
            </div>
            <div class="form-group">
                <label class="form-label">Synonyms (Comma separated) *</label>
                <textarea name="synonyms" id="synText" class="form-control" rows="3" required placeholder="e.g. phone, smartphone, cell phone"></textarea>
            </div>
            <div style="display:flex; gap:0.75rem;">
              <button type="submit" name="save" class="btn-primary" style="flex:1; justify-content:center;"><i class="bi bi-save"></i> Save</button>
              <button type="button" onclick="resetForm()" class="btn-primary" style="background:var(--glass); color:var(--text-primary); border:1px solid var(--glass-border);">Reset</button>
            </div>
          </form>
        </div>
        <!-- Table -->
        <div class="data-table-card">
          <div class="data-table-header"><div class="data-table-title">All Synonyms (<?= count($syns) ?>)</div></div>
          <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Keyword</th><th>Synonyms</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach($syns as $s): ?>
              <tr>
                <td style="font-weight:700;"><?= htmlspecialchars($s['keyword']) ?></td>
                <td style="font-size:0.85rem; color:var(--text-secondary);">
                    <?php foreach(explode(',', $s['synonyms']) as $p): ?>
                        <span style="background:var(--glass); border:1px solid var(--border); padding:2px 6px; border-radius:4px; margin:2px; display:inline-block;"><?= htmlspecialchars(trim($p)) ?></span>
                    <?php endforeach; ?>
                </td>
                <td>
                  <div style="display:flex; gap:0.4rem;">
                    <button onclick="editSyn(<?= htmlspecialchars(json_encode($s)) ?>)" class="btn-icon btn-edit" title="Edit"><i class="bi bi-pencil"></i></button>
                    <a href="synonyms.php?delete=<?= $s['id'] ?>" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Delete this synonym?')"><i class="bi bi-trash"></i></a>
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
function editSyn(s) {
  document.getElementById('editId').value = s.id;
  document.getElementById('synKeyword').value = s.keyword;
  document.getElementById('synText').value = s.synonyms;
  document.getElementById('formTitle').textContent = '✏️ Edit Synonym';
  document.getElementById('formCard').scrollIntoView({behavior:'smooth'});
}
function resetForm() {
  document.getElementById('editId').value = 0;
  document.getElementById('synForm').reset();
  document.getElementById('formTitle').textContent = '➕ Add Synonym';
}
</script>
</body>
</html>
