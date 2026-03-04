<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/AffiliateClass.php';
require_once __DIR__ . '/../config/affiliate_auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAffiliateLogin();
$affSystem = new AffiliateSystem($pdo);

$affId = $_SESSION['affiliate_id'];
$stats = $affSystem->getStats($affId);
$weeklyClicks = $affSystem->getWeeklyClicks($affId);

// Chart Data Prep (Ensuring all last 7 days are present)
$chartLabels = [];
$chartValues = [];
$clickData = [];

// Index current data by date
foreach($weeklyClicks as $c) {
    $clickData[$c['date']] = $c['count'];
}

// Build 7-day window
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($date));
    $chartValues[] = $clickData[$date] ?? 0;
}

// Recent Commissions
$stmt = $pdo->prepare("SELECT c.*, o.order_number 
                       FROM affiliate_commissions c 
                       JOIN orders o ON c.order_id = o.id 
                       WHERE c.affiliate_id = ? 
                       ORDER BY c.created_at DESC LIMIT 5");
$stmt->execute([$affId]);
$commissions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT referral_code FROM affiliates WHERE id = ?");
$stmt->execute([$affId]);
$affCode = $stmt->fetchColumn();
$referralLink = SITE_URL . "/index.php?ref=" . $affCode;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Affiliate Dashboard - <?= SITE_NAME ?></title>
    <?php include 'includes/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            height: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.06);
            border-color: var(--primary);
        }
        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            font-size: 1.5rem;
        }
        .link-box {
            background: rgba(99, 102, 241, 0.03);
            border: 2px dashed rgba(99, 102, 241, 0.2);
            padding: 1rem;
            border-radius: 12px;
            font-family: monospace;
            font-size: 0.9rem;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 style="font-weight: 800; font-size: 2rem;">Welcome back, <?= explode(' ', $_SESSION['affiliate_name'])[0] ?>! 👋</h1>
                <p class="text-secondary mb-0">Here's your performance snapshot for today.</p>
            </div>
            <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        </header>

        <!-- Stats Grid -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-primary-subtle text-primary">
                        <i class="bi bi-cursor-fill"></i>
                    </div>
                    <div class="text-secondary small fw-600">Total Link Clicks</div>
                    <div class="h3 fw-800 mt-1"><?= number_format($stats['clicks']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-warning-subtle text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="text-secondary small fw-600">Pending Payouts</div>
                    <div class="h3 fw-800 mt-1"><?= formatPrice($stats['pending_earnings']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-success-subtle text-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="text-secondary small fw-600">Total Earnings</div>
                    <div class="h3 fw-800 mt-1"><?= formatPrice($stats['total_earned']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card border-primary border-opacity-25" style="background: rgba(99, 102, 241, 0.03);">
                    <div class="icon-box bg-primary text-white shadow-sm">
                        <i class="bi bi-wallet-fill"></i>
                    </div>
                    <div class="text-secondary small fw-600">Available Balance</div>
                    <div class="h3 fw-800 mt-1" style="color: var(--primary);"><?= formatPrice($affiliate['balance'] ?? $stats['balance']) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-lg-8">
                <div class="glass-card h-100">
                    <h5 class="fw-800 mb-4">Weekly Engagement</h5>
                    <div style="position: relative; height: 320px; width: 100%;">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="glass-card h-100">
                    <h5 class="fw-800 mb-3">Your Referral Link</h5>
                    <p class="text-secondary small mb-4">Sharing this link earned you <?= AFFILIATE_COMMISSION_PERCENT ?>% commission on every sale from new customers.</p>
                    
                    <div class="link-box mb-4" id="refLink"><?= $referralLink ?></div>
                    
                    <button class="btn btn-primary-luxury w-100 py-3" onclick="copyRef()">
                        <i class="bi bi-clipboard2-check me-2"></i> Copy Link
                    </button>
                    
                    <div class="d-flex gap-2 mt-4">
                        <a href="https://wa.me/?text=Check this out: <?= $referralLink ?>" target="_blank" class="btn btn-outline-success flex-grow-1 border-2 rounded-3"><i class="bi bi-whatsapp"></i></a>
                        <a href="https://twitter.com/intent/tweet?url=<?= $referralLink ?>&text=Shop at LuxeStore!" target="_blank" class="btn btn-outline-info flex-grow-1 border-2 rounded-3"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" class="btn btn-outline-primary flex-grow-1 border-2 rounded-3"><i class="bi bi-facebook"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Table -->
        <div class="glass-card overflow-hidden" style="padding: 0;">
            <div class="px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-800 mb-0">Recent Commissions</h5>
                <a href="commissions.php" class="btn btn-link text-decoration-none fw-600 p-0">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ORDER INFO</th>
                            <th>DATE</th>
                            <th>AMOUNT</th>
                            <th>EARNING</th>
                            <th class="pe-4">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($commissions)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No sales generated yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($commissions as $row): ?>
                            <tr>
                                <td class="ps-4 fw-600">Order #<?= $row['order_number'] ?></td>
                                <td class="text-secondary"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td><?= formatPrice($row['order_amount']) ?></td>
                                <td class="fw-800 text-success">+<?= formatPrice($row['commission_amount']) ?></td>
                                <td class="pe-4">
                                    <span class="badge rounded-pill bg-<?= $row['status']==='paid'?'success':($row['status']==='verified'?'info':'warning') ?> px-3 py-2">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Performance Chart
            const canvas = document.getElementById('performanceChart');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.25)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

            if (typeof Chart !== 'undefined') {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($chartLabels) ?>,
                        datasets: [{
                            label: 'Clicks',
                            data: <?= json_encode($chartValues) ?>,
                            borderColor: '#6366f1',
                            borderWidth: 4,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: '#6366f1',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            tension: 0.4,
                            fill: true,
                            backgroundColor: gradient
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { 
                                beginAtZero: true, 
                                grid: { borderDash: [5, 5], color: '#e2e8f0', drawBorder: false },
                                ticks: { font: { family: 'Outfit', weight: '500' } }
                            },
                            x: { 
                                grid: { display: false },
                                ticks: { font: { family: 'Outfit', weight: '500' } }
                            }
                        },
                        interaction: { intersect: false, mode: 'index' }
                    }
                });
            }
        });

        function copyRef() {
            const link = document.getElementById('refLink').innerText;
            navigator.clipboard.writeText(link).then(() => {
                alert('Referral link copied to clipboard!');
            });
        }
    </script>
</body>
</html>
