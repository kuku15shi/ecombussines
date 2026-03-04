<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();

$pageTitle = 'Dashboard';

// Stats
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE order_status != 'cancelled'")->fetch()['v'];
$totalOrders = $pdo->query("SELECT COUNT(*) as v FROM orders")->fetch()['v'];
$totalUsers = $pdo->query("SELECT COUNT(*) as v FROM users")->fetch()['v'];
$totalProducts = $pdo->query("SELECT COUNT(*) as v FROM products WHERE is_active=1")->fetch()['v'];
$pendingOrders = $pdo->query("SELECT COUNT(*) as v FROM orders WHERE order_status='pending'")->fetch()['v'];

// Profit Calculations
$totalProfit = $pdo->query("SELECT SUM(oi.total - (p.purchase_price * oi.quantity)) as v FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN orders o ON oi.order_id = o.id WHERE o.order_status = 'delivered'")->fetch()['v'] ?? 0;
$potentialProfit = $pdo->query("SELECT SUM((price * (1 - COALESCE(discount_percent,0)/100) - purchase_price) * stock) as v FROM products WHERE is_active=1 AND stock > 0")->fetch()['v'] ?? 0;

// Monthly Revenue (last 6 months)
$monthlyData = [];
for($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $res = $pdo->prepare("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE DATE_FORMAT(created_at,'%Y-%m')=? AND order_status!='cancelled'");
    $res->execute([$m]);
    $monthlyData[] = ['month'=>date('M', strtotime("-$i months")), 'revenue'=>round($res->fetch()['v'])];
}

// Recent Orders
$recentOrders = $pdo->query("SELECT o.*, u.name as user_name FROM orders o LEFT JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();

// Low Stock Products
$lowStock = $pdo->query("SELECT * FROM products WHERE stock <= 5 AND is_active=1 ORDER BY stock ASC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> – LuxeStore Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="admin-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="content-area">

      <!-- Welcome -->
      <div style="background:linear-gradient(135deg,rgba(108,99,255,0.15),rgba(255,101,132,0.1)); border:1px solid rgba(108,99,255,0.25); border-radius:var(--radius); padding:1.25rem 1.5rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.875rem;">
        <div>
          <div style="font-size:1.2rem; font-weight:800; margin-bottom:0.2rem;">Good <?= (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening')) ?>, <?= htmlspecialchars($_SESSION['admin_name']) ?>! 👋</div>
          <div style="color:var(--text-muted); font-size:0.8rem;"><?= date('d M Y') ?> · LuxeStore Admin</div>
        </div>
        <div style="display:flex; gap:0.625rem; flex-wrap:wrap;">
          <a href="products.php?action=add" class="btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Product</a>
          <a href="orders.php" class="btn-primary btn-sm btn-warning"><i class="bi bi-bag-check"></i> Orders</a>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="stat-grid">
        <?php
        $stats = [
          ['icon'=>'bi-currency-rupee','label'=>'Total Revenue','value'=>'₹'.number_format($totalRevenue),'change'=>'+12.5%','up'=>true,'color'=>'linear-gradient(135deg,var(--primary),var(--primary-dark))'],
          ['icon'=>'bi-graph-up-arrow','label'=>'Total Profit','value'=>'₹'.number_format($totalProfit),'change'=>'Delivered orders','up'=>null,'color'=>'linear-gradient(135deg,#10B981,#059669)'],
          ['icon'=>'bi-bag-check','label'=>'Total Orders','value'=>$totalOrders,'change'=>'+8.2%','up'=>true,'color'=>'linear-gradient(135deg,var(--accent),#38BDF8)'],
          ['icon'=>'bi-box-seam','label'=>'Products','value'=>$totalProducts,'change'=>'Potential: ₹'.number_format($potentialProfit),'up'=>null,'color'=>'linear-gradient(135deg,var(--gold),#F97316)'],
        ];
        foreach($stats as $s):
        ?>
        <div class="stat-card">
          <div class="stat-icon" style="background:<?= $s['color'] ?>;">
            <i class="bi <?= $s['icon'] ?>" style="color:#fff;"></i>
          </div>
          <div class="stat-num"><?= $s['value'] ?></div>
          <div class="stat-label"><?= $s['label'] ?></div>
          <?php if($s['up'] !== null): ?>
          <div class="stat-change <?= $s['up']?'up':'down' ?>">
            <i class="bi bi-arrow-<?= $s['up']?'up':'down' ?>-right"></i> <?= $s['change'] ?> <span class="stat-time-label">this month</span>
          </div>
          <?php else: ?>
          <div style="font-size:0.72rem; color:var(--text-muted);"><span class="stat-time-label"><?= $s['change'] ?></span></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Charts + Quick Info -->
      <div class="admin-grid-2" style="margin-bottom:1.5rem;">
        <!-- Revenue Chart -->
        <div class="data-table-card">
          <div class="data-table-header">
            <div class="data-table-title">📊 Monthly Revenue</div>
            <div style="font-size:0.8rem; color:var(--text-muted);">Last 6 months</div>
          </div>
          <div style="padding:1.5rem;">
            <div class="chart-container" style="height:260px;">
              <canvas id="revenueChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="data-table-card">
          <div class="data-table-header"><div class="data-table-title">⚡ Quick Stats</div></div>
          <div style="padding:1.25rem;">
            <?php
            $qstats = [
              ['Pending Orders', $pendingOrders, 'badge-pending', 'bi-clock'],
              ['Pending Withdrawals', $pdo->query("SELECT COUNT(*) as c FROM affiliate_withdrawals WHERE status='pending'")->fetch()['c'], 'badge-warning', 'bi-cash-stack'],
              ['Processing', $pdo->query("SELECT COUNT(*) as c FROM orders WHERE order_status='processing'")->fetch()['c'], 'badge-processing', 'bi-gear'],
              ['Shipped', $pdo->query("SELECT COUNT(*) as c FROM orders WHERE order_status='shipped'")->fetch()['c'], 'badge-shipped', 'bi-truck'],
              ['Delivered', $pdo->query("SELECT COUNT(*) as c FROM orders WHERE order_status='delivered'")->fetch()['c'], 'badge-delivered', 'bi-house-check'],
              ['Cancelled', $pdo->query("SELECT COUNT(*) as c FROM orders WHERE order_status='cancelled'")->fetch()['c'], 'badge-cancelled', 'bi-x-circle'],
            ];
            foreach($qstats as $qs):
            ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 0; border-bottom:1px solid var(--border);">
              <div style="display:flex; align-items:center; gap:0.6rem; font-size:0.875rem;">
                <i class="bi <?= $qs[3] ?>" style="font-size:1rem;"></i> <?= $qs[0] ?>
              </div>
              <span class="badge <?= $qs[2] ?>"><?= $qs[1] ?></span>
            </div>
            <?php endforeach; ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 0;">
              <div style="font-size:0.875rem;">Low Stock Products</div>
              <span class="badge badge-cancelled"><?= count($lowStock) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Orders + Low Stock -->
      <div class="admin-grid-2">
        <!-- Recent Orders -->
        <div class="data-table-card">
          <div class="data-table-header">
            <div class="data-table-title">🛒 Recent Orders</div>
            <a href="orders.php" class="btn-primary btn-sm"><i class="bi bi-arrow-right"></i> View All</a>
          </div>
          <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach($recentOrders as $order): ?>
              <tr>
                <td><a href="order_detail.php?id=<?= $order['id'] ?>" style="color:var(--primary); text-decoration:none; font-weight:600; font-size:0.8rem;"><?= $order['order_number'] ?></a></td>
                <td style="max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($order['user_name'] ?? $order['name']) ?></td>
                <td style="font-weight:700;"><?= formatPrice($order['total']) ?></td>
                <td><span class="badge badge-<?= $order['order_status'] ?>"><?= ucfirst($order['order_status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($recentOrders)): ?>
              <tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:2rem;">No orders yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>

        <!-- Low Stock -->
        <div class="data-table-card">
          <div class="data-table-header">
            <div class="data-table-title">⚠️ Low Stock</div>
            <a href="products.php" class="btn-primary btn-sm"><i class="bi bi-box-seam"></i> Manage</a>
          </div>
          <div class="table-responsive">
          <table class="admin-table">
            <thead><tr><th>Product</th><th>Stock</th></tr></thead>
            <tbody>
              <?php foreach($lowStock as $p): ?>
              <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></td>
                <td><span class="badge <?= $p['stock'] <= 0 ? 'badge-cancelled' : 'badge-pending' ?>"><?= $p['stock'] <= 0 ? 'Out of Stock' : $p['stock'].' left' ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($lowStock)): ?>
              <tr><td colspan="2" style="text-align:center; color:var(--success); padding:2rem;">✓ All stocked!</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// Revenue Chart
const isLight = document.documentElement.getAttribute('data-theme') === 'light';
const textColor = isLight ? 'rgba(18,18,37,0.5)' : 'rgba(240,240,255,0.5)';
const gridColor = isLight ? 'rgba(108,99,255,0.06)' : 'rgba(255,255,255,0.05)';

const ctx = document.getElementById('revenueChart').getContext('2d');
const chartData = <?= json_encode($monthlyData) ?>;
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: chartData.map(d => d.month),
    datasets: [{
      label: 'Revenue (₹)',
      data: chartData.map(d => d.revenue),
      backgroundColor: chartData.map((_,i) => i === 5 ? '#6C63FF' : `rgba(108,99,255,${0.3 + i*0.12})`),
      borderColor: '#6C63FF',
      borderWidth: 0,
      borderRadius: 10,
      hoverBackgroundColor: '#5A52D5',
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: isLight ? 'rgba(255,255,255,0.98)' : 'rgba(10,10,31,0.98)',
        borderColor: 'rgba(108,99,255,0.3)',
        borderWidth: 1,
        titleColor: isLight ? '#121225' : '#F8F9FF',
        bodyColor: isLight ? '#666' : 'rgba(248,249,255,0.7)',
        padding: 12,
        cornerRadius: 12,
        callbacks: { label: ctx => '₹' + ctx.parsed.y.toLocaleString() }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: textColor, font: { weight: 600 } } },
      y: { grid: { color: gridColor }, ticks: { color: textColor, callback: v => '₹' + v.toLocaleString() }, beginAtZero: true }
    }
  }
});

</script>
</body>
</html>
