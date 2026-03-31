<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Manage Live Streams';

$msg = '';
$msgType = 'success';

// Handle Delete
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->query("DELETE FROM live_streams WHERE id=$id");
        $pdo->query("DELETE FROM live_chat WHERE stream_id=$id");
        $msg = "Live stream session deleted.";
    } catch(PDOException $e) {
        $msg = "Error: " . $e->getMessage();
        $msgType = 'error';
    }
}

// Handle Status Change
if(isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $st = in_array($_GET['status'], ['scheduled','live','ended']) ? $_GET['status'] : 'scheduled';
    
    try {
        if($st === 'live') {
            // Auto-end other live streams since we only want one active global live feed
            $pdo->query("UPDATE live_streams SET status='ended' WHERE status='live'");
        }
        $stmt = $pdo->prepare("UPDATE live_streams SET status=? WHERE id=?");
        $stmt->execute([$st, $id]);
        $msg = "Stream status successfully changed to " . strtoupper($st) . ".";
    } catch(PDOException $e) {
         $msg = "Status update error. " . $e->getMessage();
         $msgType = 'error';
    }
}

// Handle Schedule Request
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule_live') {
    $title = sanitize($conn, $_POST['title']);
    $pinned_product_id = $_POST['pinned_product_id'] ? (int)$_POST['pinned_product_id'] : null;
    $start_time = $_POST['start_time'];
    
    // Auto-generate a stream key
    $stream_key = 'lxe_' . bin2hex(random_bytes(6));
    
    if(!$title || !$start_time) {
         $msg = "Event Title and Start Date/Time are required.";
         $msgType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO live_streams (title, stream_key, pinned_product_id, start_time, host_user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $stream_key, $pinned_product_id, $start_time, $_SESSION['admin_id'] ?? 1]);
            $msg = "Event Scheduled Successfully! Share your Stream Key: $stream_key with OBS.";
        } catch(PDOException $e) {
            $msg = "Database Error. Did you run the database_video_update.sql? " . $e->getMessage();
            $msgType = 'error';
        }
    }
}

// Fetch Streams
$streams = [];
try {
    $streams = $pdo->query("SELECT ls.*, p.name as product_name FROM live_streams ls LEFT JOIN products p ON ls.pinned_product_id = p.id ORDER BY FIELD(ls.status, 'live', 'scheduled', 'ended'), ls.start_time ASC")->fetchAll();
} catch(PDOException $e) { }

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
      <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.5rem; font-weight:800;"><i class="bi bi-broadcast" style="color:#ff0055;"></i> Live Commerce Controller</h1>
        <p style="color:var(--text-muted); font-size:0.9rem;">Schedule, Go Live, and pin products in real-time to your audience.</p>
      </div>
      
      <?php if($msg): ?>
      <div class="alert <?= $msgType === 'error' ? 'alert-danger' : 'alert-success' ?>" style="padding:1rem; border-radius:8px; margin-bottom:1.5rem; background: <?= $msgType === 'error' ? 'rgba(255,101,132,0.1)' : 'rgba(67, 233, 123, 0.1)' ?>; color: <?= $msgType === 'error' ? 'var(--danger)' : 'var(--success)' ?>;">
         <?= $msg ?>
      </div>
      <?php endif; ?>

      <div class="admin-grid-2">
      
        <!-- Scheduler Form -->
        <div class="data-table-card">
          <div class="data-table-header">
            <div class="data-table-title">Schedule Live Event</div>
          </div>
          <div style="padding:1.5rem;">
            <form method="POST">
                <input type="hidden" name="action" value="schedule_live">
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; margin-bottom:0.4rem; font-weight:600;">Event Title</label>
                    <input type="text" name="title" class="form-input" required placeholder="Mega Big Billion Sale Live!" style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--border); background:var(--glass);">
                </div>
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; margin-bottom:0.4rem; font-weight:600;">Pin Product during Live</label>
                    <select name="pinned_product_id" class="form-input" style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--border); background:var(--glass); color:var(--text-primary);">
                        <option value="">-- No Product Pinned --</option>
                        <?php foreach($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom:1.5rem;">
                    <label style="display:block; margin-bottom:0.4rem; font-weight:600;">Start Date & Time</label>
                    <input type="datetime-local" name="start_time" class="form-input" required style="width:100%; padding:0.6rem; border-radius:8px; border:1px solid var(--border); background:var(--glass); color:var(--text-primary);">
                </div>

                <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:0.75rem;"><i class="bi bi-calendar-check-fill"></i> Save to Schedule</button>
            </form>
          </div>
        </div>

        <!-- Event Listing -->
        <div class="data-table-card">
          <div class="data-table-header">
            <div class="data-table-title">Events Status</div>
          </div>
          <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title & Details</th>
                        <th>Pinned Product</th>
                        <th>Status / Controls</th>
                        <th>Key</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($streams)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);">No Live Streams scheduled.</td></tr>
                    <?php else: ?>
                        <?php foreach($streams as $ls): ?>
                        <tr>
                            <td style="font-weight:600; font-size: 0.9rem;">
                                <?= htmlspecialchars($ls['title']) ?>
                                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">
                                    <?php if($ls['status'] !== 'live') echo date('M j, g:i A', strtotime($ls['start_time'])); else echo "Streaming Active"; ?>
                                </div>
                            </td>
                            <td>
                                <?php if($ls['product_name']): ?>
                                  <span class="badge badge-warning" style="font-size:0.75rem;"><i class="bi bi-pin-fill"></i> <?= htmlspecialchars($ls['product_name']) ?></span>
                                <?php else: ?>
                                  <span style="color:var(--text-muted); font-size:0.8rem;">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Live Controls -->
                                <?php if($ls['status'] === 'live'): ?>
                                    <div class="badge badge-success" style="animation: pulse 1.5s infinite; background:#ff0055;"><i class="bi bi-broadcast"></i> LIVE</div>
                                    <div style="margin-top: 5px;">
                                      <a href="?status=ended&id=<?= $ls['id'] ?>" class="btn-primary btn-sm" style="background:var(--bg-dark); padding: 0.3rem 0.5rem; font-size:0.7rem;">End Stream</a>
                                    </div>
                                <?php elseif($ls['status'] === 'scheduled'): ?>
                                    <div class="badge badge-processing" style="margin-bottom: 5px;">Scheduled</div>
                                    <div>
                                      <a href="?status=live&id=<?= $ls['id'] ?>" class="btn-primary btn-sm" style="background:#ff0055; padding: 0.3rem 0.5rem; font-size:0.7rem; border:none;"><i class="bi bi-play-circle-fill"></i> GO LIVE</a>
                                    </div>
                                <?php else: ?>
                                    <div class="badge badge-cancelled" style="margin-bottom: 5px;">Ended</div>
                                    <div style="font-size:0.7rem; color:var(--text-muted);"><i class="bi bi-eye"></i> <?= number_format($ls['viewers_count']) ?> joined</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-family:monospace; background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:0.75rem; letter-spacing:1px;"><?= $ls['stream_key'] ?></div>
                                <div style="margin-top:5px;">
                                  <a href="?delete=<?= $ls['id'] ?>" onclick="return confirm('Remove event record permanently?');" style="color:var(--danger); font-size: 0.8rem; text-decoration:none;"><i class="bi bi-trash"></i> Delete</a>
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
