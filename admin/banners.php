<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Banners / Ads';

if(isset($_GET['delete'])){ 
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM banners WHERE id=?")->execute([$id]);
    header('Location: banners.php?success=1'); exit; 
}

if(isset($_GET['toggle'])){ 
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE banners SET is_active = NOT is_active WHERE id=?")->execute([$id]);
    header('Location: banners.php'); exit; 
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save'])){
    validateCsrf();
    $title = $_POST['title'] ?? '';
    $subtitle = $_POST['subtitle'] ?? '';
    $btnText = $_POST['btn_text'] ?? '';
    $btnUrl = $_POST['btn_url'] ?? '';
    $position = (int)($_POST['position'] ?? 0);
    $imgPath = '';
    
    if(!empty($_FILES['image']['name'])) {
        $imgRes = uploadImage($_FILES['image'], 'banners');
        if (isset($imgRes['filename'])) {
            $imgPath = $imgRes['filename'];
        }
    }
    
    $editId = (int)($_POST['edit_id'] ?? 0);
    if($editId){
        if ($imgPath) {
            $stmt = $pdo->prepare("UPDATE banners SET title=?, subtitle=?, btn_text=?, btn_url=?, image=?, position=? WHERE id=?");
            $stmt->execute([$title, $subtitle, $btnText, $btnUrl, $imgPath, $position, $editId]);
        } else {
            $stmt = $pdo->prepare("UPDATE banners SET title=?, subtitle=?, btn_text=?, btn_url=?, position=? WHERE id=?");
            $stmt->execute([$title, $subtitle, $btnText, $btnUrl, $position, $editId]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO banners (title,subtitle,btn_text,btn_url,image,position,is_active) VALUES (?,?,?,?,?,?,1)");
        $stmt->execute([$title, $subtitle, $btnText, $btnUrl, $imgPath, $position]);
    }
    header('Location: banners.php?success=1'); exit;
}

$banners = $pdo->query("SELECT * FROM banners ORDER BY position ASC, id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Banners – Admin</title>
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
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Saved!</div><?php endif; ?>
      <div style="display:grid; grid-template-columns:360px 1fr; gap:1.5rem; align-items:start;">
        <div class="form-card">
          <div style="font-weight:800; margin-bottom:1.25rem;" id="formTitle">🖼 Add Banner</div>
          <form method="POST" enctype="multipart/form-data" id="bannerForm">
            <?= csrfField() ?>
            <input type="hidden" name="edit_id" id="editId" value="0">
            <div class="form-group"><label class="form-label">Title *</label><input type="text" name="title" id="bTitle" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Subtitle</label><input type="text" name="subtitle" id="bSub" class="form-control"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.875rem;">
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">Button Text</label><input type="text" name="btn_text" id="bBtn" class="form-control" placeholder="Shop Now"></div>
              <div class="form-group" style="margin-bottom:0;"><label class="form-label">Button URL</label><input type="text" name="btn_url" id="bUrl" class="form-control" placeholder="/products.php"></div>
            </div>
            <div class="form-group"><label class="form-label">Position / Sort Order</label><input type="number" name="position" id="bPos" class="form-control" value="0"></div>
            <div class="form-group"><label class="form-label">Banner Image</label><div class="img-uploader"><input type="file" name="image" accept="image/*"><div class="img-uploader-icon"><i class="bi bi-image"></i></div><div class="img-uploader-text">Click to upload banner image</div></div></div>
            <button type="submit" name="save" class="btn-primary" style="width:100%; justify-content:center;"><i class="bi bi-save"></i> Save Banner</button>
          </form>
        </div>
        <div class="data-table-card">
          <div class="data-table-header"><div class="data-table-title">All Banners (<?= count($banners) ?>)</div></div>
          <div style="padding:1.25rem; display:flex; flex-direction:column; gap:1rem;">
            <?php foreach($banners as $b): ?>
            <div style="display:flex; gap:1.25rem; align-items:center; background:var(--glass); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:1rem; flex-wrap:wrap;">
              <?php if($b['image']): ?>
              <img src="<?= UPLOAD_URL . $b['image'] ?>" onerror="this.src='../assets/img/default_product.jpg'" style="width:120px; height:70px; object-fit:cover; border-radius:var(--radius-sm);" alt="">
              <?php else: ?>
              <div style="width:120px; height:70px; background:linear-gradient(135deg,var(--primary),var(--secondary)); border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:1.5rem;">🖼</div>
              <?php endif; ?>
              <div style="flex:1;">
                <div style="font-weight:800; font-size:1rem;"><?= htmlspecialchars($b['title']) ?></div>
                <?php if($b['subtitle']): ?><div style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($b['subtitle']) ?></div><?php endif; ?>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">Position: <?= $b['position'] ?></div>
              </div>
              <div style="display:flex; gap:0.5rem; align-items:center;">
                <a href="banners.php?toggle=<?= $b['id'] ?>">
                  <span class="badge <?= $b['is_active']?'badge-active':'badge-inactive' ?>"><?= $b['is_active']?'Active':'Inactive' ?></span>
                </a>
                <a href="banners.php?delete=<?= $b['id'] ?>" class="btn-icon btn-delete" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($banners)): ?><div style="text-align:center; padding:2rem; color:var(--text-muted);">No banners yet</div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
