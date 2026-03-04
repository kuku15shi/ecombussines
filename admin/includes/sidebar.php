<?php $currentPage = basename($_SERVER['SCRIPT_NAME'], '.php'); ?>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
      <div class="brand-logo">✦ LuxeStore</div>
      <button onclick="closeSidebar()" class="btn-icon d-lg-none" style="background:none; border:none; color:var(--text-muted); font-size:1.5rem; padding:0;" id="mobileCloseBtn">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="brand-sub">Admin Dashboard</div>
  </div>
  <div class="sidebar-menu">
    <div class="menu-section-label">Main</div>
    <a href="index.php" class="menu-item <?= $currentPage==='index'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-grid-1x2"></i></div> Dashboard
    </a>

    <div class="menu-section-label">Store</div>
    <a href="products.php" class="menu-item <?= $currentPage==='products'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-box-seam"></i></div> Products
    </a>
    <a href="categories.php" class="menu-item <?= $currentPage==='categories'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-grid"></i></div> Categories
    </a>
    <a href="orders.php" class="menu-item <?= $currentPage==='orders'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-bag-check"></i></div> Orders
      <?php
      $pendingCount = $conn->query("SELECT COUNT(*) as c FROM orders WHERE order_status='pending'")->fetch_assoc()['c'];
      if($pendingCount > 0): ?>
      <span class="menu-badge"><?= $pendingCount ?></span>
      <?php endif; ?>
    </a>

    <div class="menu-section-label">Marketing</div>
    <a href="coupons.php" class="menu-item <?= $currentPage==='coupons'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-ticket-perforated"></i></div> Coupons
    </a>
    <a href="banners.php" class="menu-item <?= $currentPage==='banners'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-images"></i></div> Banners / Ads
    </a>
    <a href="affiliates.php" class="menu-item <?= $currentPage==='affiliates'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-person-badge"></i></div> Affiliates
    </a>
    <a href="commissions.php" class="menu-item <?= $currentPage==='commissions'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-currency-dollar"></i></div> Commissions
    </a>
    <a href="withdrawals.php" class="menu-item <?= $currentPage==='withdrawals'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-cash-stack"></i></div> Withdrawals
      <?php
      $pendingWithdrawals = $conn->query("SELECT COUNT(*) as c FROM affiliate_withdrawals WHERE status='pending'")->fetch_assoc()['c'];
      if($pendingWithdrawals > 0): ?>
      <span class="menu-badge" style="background:var(--warning); color:black;"><?= $pendingWithdrawals ?></span>
      <?php endif; ?>
    </a>

    <div class="menu-section-label">Users</div>
    <a href="users.php" class="menu-item <?= $currentPage==='users'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-people"></i></div> Users
    </a>

    <div class="menu-section-label">Account</div>
    <a href="logout.php" class="menu-item">
      <div class="menu-icon" style="background:rgba(255,101,132,0.15);"><i class="bi bi-box-arrow-right" style="color:var(--danger);"></i></div>
      <span style="color:var(--danger);">Logout</span>
    </a>
  </div>
</nav>
