<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'WhatsApp API Logs';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page-1) * $perPage;

$total = $pdo->query("SELECT COUNT(*) FROM whatsapp_logs")->fetchColumn();
$pages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT l.*, o.order_number FROM whatsapp_logs l LEFT JOIN orders o ON l.order_id = o.id ORDER BY l.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$perPage, $offset]);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WhatsApp Logs – Admin</title>
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
      <div class="data-table-card">
        <div class="card-header" style="padding:1.5rem; display:flex; justify-content:space-between; align-items:center;">
          <h2 style="font-weight:800; margin:0;">WhatsApp API Logs</h2>
          <div class="d-flex gap-2">
            <span class="badge badge-delivered">Total: <?= $total ?></span>
          </div>
        </div>

        <?php 
        // Quick health check based on latest log
        $latest = $logs[0] ?? null;
        if ($latest && $latest['status'] === 'fail'): 
            $isExpired = strpos($latest['error_message'], 'expired') !== false;
            $isSandbox = strpos($latest['error_message'], 'not in allowed list') !== false;
        ?>
        <div style="margin: 0 1.5rem 1.5rem 1.5rem; padding: 1.25rem; border-radius: 12px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2);">
            <div style="display: flex; gap: 1rem; align-items: center;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #ef4444; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div>
                    <h5 style="margin: 0; font-weight: 800; color: #b91c1c;">
                        <?= $isExpired ? 'WhatsApp Access Token Expired' : ($isSandbox ? 'Sandbox Restriction Detected' : 'WhatsApp API Alert') ?>
                    </h5>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: #7f1d1d; opacity: 0.85; line-height: 1.4;">
                        <?php if($isExpired): ?>
                            Your temporary access token has expired. Please log in to your <strong>Meta App Dashboard</strong>, generate a <strong>Permanent Access Token</strong>, and update it in the <a href="wa_messages.php?tab=settings" style="color:inherit; text-decoration:underline;">Bot Settings</a> dashboard.
                        <?php elseif($isSandbox): ?>
                            You are in Sandbox mode. The recipient number <strong><?= $latest['phone'] ?></strong> is not in your allowed test list. Add it in the <strong>WhatsApp > Configuration</strong> section of your Meta App.
                        <?php else: ?>
                            The latest API call failed with: <em>"<?= htmlspecialchars($latest['error_message']) ?>"</em>. Check your configuration immediately.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Order #</th>
                <th>Phone</th>
                <th>Type</th>
                <th>Status</th>
                <th>Response / Error</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($logs as $log): ?>
              <tr>
                <td style="font-size:0.8rem;"><?= date('d M, H:i', strtotime($log['created_at'])) ?></td>
                <td><?= $log['order_number'] ?: 'N/A' ?></td>
                <td><?= $log['phone'] ?></td>
                <td><span class="badge badge-pending"><?= $log['type'] ?></span></td>
                <td>
                  <span class="badge badge-<?= $log['status']==='success'?'delivered':'cancelled' ?>">
                    <?= strtoupper($log['status']) ?>
                  </span>
                </td>
                <td style="max-width:300px;">
                  <?php if($log['status'] === 'fail'): ?>
                    <div style="color:var(--danger); font-size:0.75rem; font-weight:600;"><?= htmlspecialchars($log['error_message']) ?></div>
                  <?php else: ?>
                    <div style="font-size:0.7rem; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($log['api_response']) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($logs)): ?>
              <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);">No logs found</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if($pages > 1): ?>
        <div class="pagination">
          <?php for($i=1;$i<=$pages;$i++): ?>
          <a href="?page=<?= $i ?>" class="page-link <?= $i==$page?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
