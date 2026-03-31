<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Delivery Addresses';

$search = $_GET['search'] ?? '';

$whereClause = "1=1";
$params = [];

if ($search) {
    // If search looks like an order ID (e.g. LUXE...)
    if (strpos(strtoupper($search), 'LUXE') === 0 || is_numeric($search)) {
        // Find user_id from order
        $whereClause .= " AND a.user_id IN (SELECT user_id FROM orders WHERE order_number LIKE ? OR id = ?)";
        $params[] = "%$search%";
        $params[] = $search;
    } else {
        $whereClause .= " AND (a.phone LIKE ? OR a.full_name LIKE ? OR a.pincode LIKE ? OR a.city LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

$query = "SELECT a.*, u.name as sys_user_name, u.email as sys_user_email 
          FROM user_addresses a 
          LEFT JOIN users u ON a.user_id = u.id 
          WHERE $whereClause 
          ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$addresses = $stmt->fetchAll();

// Export Logic
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="delivery_addresses.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'User ID', 'Sys Name', 'Contact Name', 'Phone', 'Address Type', 'House', 'Street', 'Landmark', 'City', 'District', 'State', 'Pincode', 'Country', 'Map Link']);
    foreach ($addresses as $row) {
        $mapLink = "https://www.google.com/maps/search/?api=1&query=" . urlencode($row['house'].", ".$row['street'].", ".$row['city'].", ".$row['state']." ".$row['pincode']);
        fputcsv($output, [
            $row['id'], $row['user_id'], $row['sys_user_name'], $row['full_name'], $row['phone'], 
            $row['address_type'], $row['house'], $row['street'], $row['landmark'], 
            $row['city'], $row['district'], $row['state'], $row['pincode'], $row['country'], $mapLink
        ]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> – MIZ MAX Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
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

        <div class="content-header">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                <div>
                    <h2 style="font-weight:800; font-size:1.3rem;"><i class="bi bi-geo-alt"></i> Delivery Addresses</h2>
                    <p class="text-muted" style="font-size:0.85rem;">Manage customer saved delivery addresses</p>
                </div>
                <div style="display:flex; gap:1rem;">
                    <a href="?export=csv<?= $search ? '&search='.urlencode($search) : '' ?>" class="btn-primary" style="background:var(--glass); color:var(--text-primary); border:1px solid var(--glass-border); font-size:0.82rem;"><i class="bi bi-download"></i> Export CSV</a>
                </div>
            </div>
        </div>

        <div class="data-table-card" style="padding:1.5rem; margin-top:1.5rem;">
            <form method="GET" style="display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
                <input type="text" name="search" class="form-control" placeholder="Search by Phone, Order ID, Name, Pincode..." value="<?= htmlspecialchars($search) ?>" style="max-width:400px; flex:1;">
                <button type="submit" class="btn-primary"><i class="bi bi-search"></i> Search</button>
                <?php if($search): ?>
                    <a href="delivery_addresses.php" class="btn-primary" style="background:var(--bg-lighter); color:var(--text-primary);"><i class="bi bi-x"></i> Clear</a>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Address Info</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($addresses)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">No addresses found.</td></tr>
                        <?php endif; ?>
                        <?php foreach($addresses as $addr): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700;"><?= htmlspecialchars($addr['full_name']) ?></div>
                                <div style="font-size:0.85rem; color:var(--text-muted);"><i class="bi bi-person"></i> Sys: <?= htmlspecialchars($addr['sys_user_name']) ?> (ID: <?= $addr['user_id'] ?>)</div>
                                <div style="font-size:0.85rem;"><i class="bi bi-telephone"></i> <?= htmlspecialchars($addr['phone']) ?></div>
                            </td>
                            <td>
                                <div style="font-size:0.9rem; max-width:300px; white-space:normal;">
                                    <?= htmlspecialchars($addr['house'] . ', ' . $addr['street']) ?><br>
                                    <?php if($addr['landmark']) echo 'Near ' . htmlspecialchars($addr['landmark']) . '<br>'; ?>
                                    <span style="color:var(--text-muted); font-size:0.75rem;">Added: <?= date('d M Y', strtotime($addr['created_at'])) ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?></div>
                                <div style="font-size:0.85rem; color:var(--text-muted);">PIN: <span class="badge" style="background:var(--primary); color:#fff; padding:0.2rem 0.5rem;"><?= htmlspecialchars($addr['pincode']) ?></span></div>
                                <div style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($addr['district']) ?></div>
                            </td>
                            <td>
                                <?php
                                    $types = ['home'=>'primary', 'work'=>'accent', 'other'=>'info'];
                                    $badgeColor = $types[$addr['address_type']] ?? 'secondary';
                                ?>
                                <span class="badge" style="background:var(--<?= $badgeColor ?>); color:#fff; text-transform:capitalize;"><?= $addr['address_type'] ?></span>
                                <?php if($addr['is_default']): ?>
                                    <span class="badge" style="background:var(--success); color:#fff;" title="Default Address"><i class="bi bi-star-fill"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($addr['house'].", ".$addr['street'].", ".$addr['city'].", ".$addr['state']." ".$addr['pincode']) ?>" target="_blank" class="btn-icon" style="background:rgba(0,184,148,0.1); color:#00b894; border:1px solid rgba(0,184,148,0.2);" title="Map View">
                                    <i class="bi bi-map-fill"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

      </div><!-- /content-area -->
    </div><!-- /main-content -->
  </div><!-- /admin-layout -->
</body>

</html>
