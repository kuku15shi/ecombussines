<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" id="sidebar">
    <div class="mb-5 px-3 d-flex align-items-center justify-content-between">
        <h2 style="font-weight: 800; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0;">Luxe Affiliate</h2>
        <button class="btn d-lg-none p-0 text-secondary" onclick="toggleSidebar()"><i class="bi bi-x-lg"></i></button>
    </div>
    
    <nav class="px-2">
        <a href="dashboard.php" class="nav-link-custom <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-fill"></i> Overview
        </a>
        <a href="commissions.php" class="nav-link-custom <?= $current_page == 'commissions.php' ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i> Commissions
        </a>
        <a href="products.php" class="nav-link-custom <?= $current_page == 'products.php' ? 'active' : '' ?>">
            <i class="bi bi-bag-plus"></i> Product Links
        </a>
        <a href="withdrawals.php" class="nav-link-custom <?= $current_page == 'withdrawals.php' ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Withdrawals
        </a>
        <a href="settings.php" class="nav-link-custom <?= $current_page == 'settings.php' ? 'active' : '' ?>">
            <i class="bi bi-person-gear"></i> Account Settings
        </a>
        
        <div style="margin-top: 3rem;">
            <p class="text-uppercase text-secondary x-small fw-700 px-3 mb-3" style="font-size: 0.7rem; letter-spacing: 1px;">Partner Support</p>
            <a href="support.php" class="nav-link-custom">
                <i class="bi bi-question-circle"></i> Help Center
            </a>
            <hr class="my-4" style="opacity: 0.05;">
            <a href="logout.php" class="nav-link-custom text-danger">
                <i class="bi bi-box-arrow-left"></i> Sign Out
            </a>
        </div>
    </nav>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }
</script>
