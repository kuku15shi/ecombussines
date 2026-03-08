<?php
$current_page = basename($_SERVER['PHP_SELF']);
$affiliate = getCurrentAffiliate($conn);
?>
<div class="sidebar" id="sidebar">
    <div class="mb-5 px-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-3 bg-primary bg-opacity-10 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-rocket-takeoff-fill text-primary fs-5"></i>
            </div>
            <h5 class="fw-900 mb-0" style="letter-spacing: -0.5px; color: #1e293b;">Partner <span class="text-primary">Hub</span></h5>
        </div>
        <button class="btn d-lg-none p-0 text-secondary" onclick="toggleSidebar()"><i class="bi bi-x-lg"></i></button>
    </div>
    
    <nav class="px-2">
        <div class="mb-4">
            <p class="text-uppercase text-secondary fw-800 mb-3 px-3" style="font-size: 0.65rem; letter-spacing: 1.5px;">Workspace</p>
            <a href="dashboard.php" class="nav-link-custom <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Overview
            </a>
            <a href="commissions.php" class="nav-link-custom <?= $current_page == 'commissions.php' ? 'active' : '' ?>">
                <i class="bi bi-wallet-fill"></i> Commissions
            </a>
            <a href="products.php" class="nav-link-custom <?= $current_page == 'products.php' ? 'active' : '' ?>">
                <i class="bi bi-link-45deg"></i> Product Links
            </a>
        </div>

        <div class="mb-4">
            <p class="text-uppercase text-secondary fw-800 mb-3 px-3" style="font-size: 0.65rem; letter-spacing: 1.5px;">Financials</p>
            <a href="withdrawals.php" class="nav-link-custom <?= $current_page == 'withdrawals.php' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> Withdrawals
            </a>
            <a href="settings.php" class="nav-link-custom <?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear-wide-connected"></i> Payout Settings
            </a>
        </div>
        
        <div style="margin-top: auto;">
            <p class="text-uppercase text-secondary fw-800 mb-3 px-3" style="font-size: 0.65rem; letter-spacing: 1.5px;">Support</p>
            <a href="support.php" class="nav-link-custom <?= $current_page == 'support.php' ? 'active' : '' ?>">
                <i class="bi bi-headset"></i> Help Center
            </a>
            <a href="logout.php" class="nav-link-custom text-danger mt-4">
                <i class="bi bi-box-arrow-left"></i> Sign Out
            </a>
        </div>
    </nav>

    <!-- Profile Snippet -->
    <div class="mt-auto pt-5">
        <div class="p-3 bg-light rounded-4 d-flex align-items-center gap-3 border">
            <div class="rounded-circle bg-white p-1 border shadow-sm" style="width: 42px; height: 42px; overflow: hidden;">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($affiliate['name']) ?>&background=6366f1&color=fff" class="w-100 h-100 object-fit-cover rounded-circle">
            </div>
            <div class="overflow-hidden">
                <div class="fw-800 small text-truncate" style="color: #1e293b;"><?= htmlspecialchars($affiliate['name']) ?></div>
                <div class="x-small text-secondary text-truncate fw-500">Verified Partner</div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }
</script>
