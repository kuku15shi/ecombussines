<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Search Analytics';

// Fetch Analytics
// 1. Most searched terms
$mostSearched = $pdo->query("SELECT keyword, COUNT(*) as query_count, MAX(results_count) as last_results_count FROM search_logs GROUP BY keyword ORDER BY query_count DESC LIMIT 20")->fetchAll();

// 2. Searches with NO results
$noResults = $pdo->query("SELECT keyword, COUNT(*) as query_count FROM search_logs WHERE results_count = 0 GROUP BY keyword ORDER BY query_count DESC LIMIT 20")->fetchAll();

// 3. Recent Searches
$recent = $pdo->query("SELECT * FROM search_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();

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
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
        
        <!-- Most Searched -->
        <div class="data-table-card">
          <div class="data-table-header"><div class="data-table-title"><i class="bi bi-graph-up text-primary"></i> Most Searched Terms</div></div>
          <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Keyword</th><th>Search Volume</th><th>Results Found</th></tr></thead>
            <tbody>
              <?php foreach($mostSearched as $row): ?>
              <tr>
                <td style="font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($row['keyword']) ?></td>
                <td><span class="badge badge-success"><?= $row['query_count'] ?></span></td>
                <td><?= $row['last_results_count'] > 0 ? "<span style='color:var(--success)'><i class='bi bi-check-circle'></i> {$row['last_results_count']}</span>" : "<span style='color:var(--danger)'>0</span>" ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>

        <!-- No Results -->
        <div class="data-table-card">
          <div class="data-table-header"><div class="data-table-title"><i class="bi bi-exclamation-triangle text-danger"></i> No Results (Missed Opportunities)</div></div>
          <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Keyword</th><th>Search Volume</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach($noResults as $row): ?>
              <tr>
                <td style="font-weight:700; color:var(--danger);"><?= htmlspecialchars($row['keyword']) ?></td>
                <td><span class="badge badge-error"><?= $row['query_count'] ?></span></td>
                <td><a href="synonyms.php?new=<?= urlencode($row['keyword']) ?>" class="btn-sm btn-outline-primary" style="text-decoration:none; font-size:0.75rem; padding:0.25rem 0.5rem; border-radius:4px;">Add Synonym</a></td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($noResults)): ?><tr><td colspan="3" style="text-align:center; padding:2rem;">Great! All recent searches found results.</td></tr><?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- Recent Searches -->
      <div class="data-table-card">
          <div class="data-table-header"><div class="data-table-title">Recent Real-time Searches</div></div>
          <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Time</th><th>Keyword</th><th>Results Count</th></tr></thead>
            <tbody>
              <?php foreach($recent as $row): ?>
              <tr>
                <td style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y H:i:s', strtotime($row['created_at'])) ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($row['keyword']) ?></td>
                <td>
                    <?php if($row['results_count'] > 0): ?>
                        <span class="badge badge-success"><?= $row['results_count'] ?> results</span>
                    <?php else: ?>
                        <span class="badge badge-error">0 results</span>
                    <?php endif; ?>
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
</body>
</html>
