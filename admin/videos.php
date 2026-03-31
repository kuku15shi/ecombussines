<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Manage Shoppable Videos';

$msg = '';
$msgType = 'success';

// Handle Delete
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->query("DELETE FROM videos WHERE id=$id");
        $msg = "Shoppable Video removed successfully.";
    } catch(PDOException $e) {
        $msg = "Error deleting video: " . $e->getMessage();
        $msgType = 'error';
    }
}

// Handle Edit Fetch
$editMode = false;
$editData = null;
if (isset($_GET['edit'])) {
    $editMode = true;
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}

// Handle Add / Edit
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if($action == 'add_video' || $action == 'edit_video') {
        $title = sanitize($conn, $_POST['title']);
        $description = sanitize($conn, $_POST['description']);
        $product_id = !empty($_POST['product_id']) ? (int) $_POST['product_id'] : null;
        
        $video_url = sanitize($conn, $_POST['video_url'] ?? '');
        
        if($action == 'edit_video' && empty($video_url) && empty($_FILES['video_file']['name'])) {
            $video_url = sanitize($conn, $_POST['existing_video_url']);
        }
        
        // Handle File Upload
        if(isset($_FILES['video_file']) && $_FILES['video_file']['error'] === 0) {
            $uploadDir = __DIR__ . '/../uploads/';
            if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = 'reel_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', basename($_FILES['video_file']['name']));
            if(move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadDir . $fileName)) {
                $video_url = SITE_URL . '/uploads/' . $fileName;
            } else {
                $msg = "Error moving uploaded file to uploads directory.";
                $msgType = 'error';
            }
        }
        
        // Validate that the URL is actually a raw video format and not a webpage (Instagram/YT/TikTok)
        if($video_url && preg_match('/(instagram\.com|youtube\.com|youtu\.be|tiktok\.com)/i', $video_url)) {
            $msg = "Social Media Links (Instagram, YouTube, TikTok) are not supported. Please download the .mp4 file to your computer and use the 'Upload Video File' button instead.";
            $msgType = 'error';
            $video_url = ''; // Prevent insert
        }
        
        if(!$video_url || !$title) {
             if(!$msg) {
                 $msg = "Please provide Title and either upload a Video File or enter a direct raw Video URL (.mp4).";
                 $msgType = 'error';
             }
        } elseif($msgType !== 'error') {
            try {
                if ($action == 'add_video') {
                    $stmt = $pdo->prepare("INSERT INTO videos (title, description, product_id, video_url, user_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $product_id, $video_url, $_SESSION['admin_id'] ?? 1]);
                    $msg = "Shoppable Reel added successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE videos SET title=?, description=?, product_id=?, video_url=? WHERE id=?");
                    $stmt->execute([$title, $description, $product_id, $video_url, (int)$_POST['video_id']]);
                    $msg = "Shoppable Reel updated successfully!";
                    $editMode = false;
                    $editData = null;
                }
            } catch(PDOException $e) {
                $msg = "Database error. Did you run the SQL script? " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
}

// Fetch lists
$videos = [];
try {
    $videos = $pdo->query("SELECT v.*, p.name as product_name FROM videos v LEFT JOIN products p ON v.product_id = p.id ORDER BY v.created_at DESC")->fetchAll();
} catch(PDOException $e) {
    // Graceful fail if db hasn't been migrated yet
    $msg = "Shoppable Videos table missing. Please import database_video_update.sql first.";
    $msgType = "error";
}

$products = $pdo->query("SELECT id, name FROM products WHERE is_active=1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $pageTitle ?> – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    
    <div class="content-area">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h1 style="font-size:1.5rem; font-weight:800;"><i class="bi bi-play-btn-fill" style="color:#fe2c55;"></i> Manage Shoppable short videos</h1>
      </div>
      
      <?php if($msg): ?>
      <div class="alert <?= $msgType === 'error' ? 'alert-danger' : 'alert-success' ?>" style="padding:1rem; border-radius:8px; margin-bottom:1.5rem; background: <?= $msgType === 'error' ? 'rgba(255,101,132,0.1)' : 'rgba(67, 233, 123, 0.1)' ?>; color: <?= $msgType === 'error' ? 'var(--danger)' : 'var(--success)' ?>;">
         <?= $msg ?>
      </div>
      <?php endif; ?>

      <div class="admin-grid-2">
      
        <!-- Form Container -->
        <div class="data-table-card">
          <div class="data-table-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div class="data-table-title"><?= $editMode ? 'Edit Short Video' : 'Add New Short Video' ?></div>
            <?php if ($editMode): ?>
                <a href="videos.php" class="btn-primary" style="background:#555; padding:6px 12px; font-size:0.8rem;"><i class="bi bi-x"></i> Cancel Edit</a>
            <?php endif; ?>
          </div>
          <div style="padding:1.5rem;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $editMode ? 'edit_video' : 'add_video' ?>">
                <?php if ($editMode): ?>
                    <input type="hidden" name="video_id" value="<?= $editData['id'] ?>">
                    <input type="hidden" name="existing_video_url" value="<?= htmlspecialchars($editData['video_url']) ?>">
                <?php endif; ?>
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; margin-bottom:0.4rem; font-weight:600;">Video Title</label>
                    <input type="text" name="title" value="<?= $editMode ? htmlspecialchars($editData['title']) : '' ?>" class="form-input" required placeholder="🔥 Exclusive Drop Review" style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--border); background:var(--glass);">
                </div>
                
                <div style="margin-bottom:1rem; padding: 1rem; border: 1px dashed var(--primary); border-radius: 8px; background: rgba(108, 99, 255, 0.05);">
                    <label style="display:block; margin-bottom:0.4rem; font-weight:600; color: var(--primary);">Upload Video File <?= $editMode ? '(Leave blank to keep existing)' : '' ?></label>
                    <input type="file" name="video_file" accept="video/mp4,video/x-m4v,video/*" style="width:100%; padding:0.4rem;">
                    <small style="color:var(--text-muted); display:block; margin-top:5px;">Upload an MP4 file. Max size depends on your PHP config.</small>
                    
                    <div style="text-align:center; margin: 10px 0; color:var(--text-muted); font-weight:bold;">OR</div>
                    
                    <label style="display:block; margin-bottom:0.4rem; font-weight:600; font-size: 0.9rem;">Provide Direct URL Instead <?= $editMode ? '(Leave blank to keep existing)' : '' ?></label>
                    <input type="url" name="video_url" class="form-input" placeholder="https://..." style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--border); background:var(--glass);">
                </div>
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; margin-bottom:0.4rem; font-weight:600;">Tag Shoppable Product</label>
                    <select name="product_id" class="form-input" style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--border); background:var(--glass); color:var(--text-primary);">
                        <option value="">-- Optional: No product tagged --</option>
                        <?php foreach($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($editMode && $editData['product_id'] == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom:1.5rem;">
                    <label style="display:block; margin-bottom:0.4rem; font-weight:600;">Caption (used for SEO & display)</label>
                    <textarea name="description" class="form-input" rows="3" style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--border); background:var(--glass);"><?= $editMode ? htmlspecialchars($editData['description']) : '' ?></textarea>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:0.75rem;"><i class="bi <?= $editMode ? 'bi-save-fill' : 'bi-cloud-arrow-up-fill' ?>"></i> <?= $editMode ? 'Update Short Video' : 'Publish to Feed' ?></button>
            </form>
          </div>
        </div>

        <!-- Videos Listing -->
        <div class="data-table-card">
          <div class="data-table-header">
            <div class="data-table-title">Uploaded Videos</div>
          </div>
          <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Product Tag</th>
                        <th>Metrics</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($videos)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);">No videos published yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($videos as $vid): ?>
                        <tr>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars($vid['title']) ?>
                                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">
                                    <a href="<?= htmlspecialchars($vid['video_url']) ?>" target="_blank" style="color:var(--info);">View Media</a>
                                </div>
                            </td>
                            <td>
                                <?php if($vid['product_name']): ?>
                                  <span class="badge badge-success"><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($vid['product_name']) ?></span>
                                <?php else: ?>
                                  <span style="color:var(--text-muted); font-size:0.8rem;">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:0.8rem; display:flex; gap:10px;">
                                   <span title="Likes"><i class="bi bi-heart-fill" style="color:#fe2c55;"></i> <?= number_format($vid['likes']) ?></span>
                                   <span title="Comments"><i class="bi bi-chat-dots-fill"></i> <?= number_format($vid['comments']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; gap: 8px;">
                                    <a href="?edit=<?= $vid['id'] ?>" class="btn-icon" style="color:var(--info); border-color:rgba(13,138,188,0.3);"><i class="bi bi-pencil"></i></a>
                                    <a href="?delete=<?= $vid['id'] ?>" class="btn-icon" onclick="return confirm('Delete this video?');" style="color:var(--danger); border-color:rgba(255,101,132,0.3);"><i class="bi bi-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
          </div>
        </div>
      </div>
      
    </div>
  </div>
</div>
</body>
</html>
