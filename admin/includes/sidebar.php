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

  <div class="sidebar-profile" style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 1rem;">
      <div class="admin-avatar" style="width: 45px; height: 45px; transform: scale(1); box-shadow: 0 5px 15px rgba(108, 99, 255, 0.3);"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
      <div>
          <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-primary);"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
          <div style="font-size: 0.72rem; color: var(--text-muted);">Master Admin</div>
      </div>
  </div>

  <div class="sidebar-menu">
    <div class="menu-section-label">Main</div>
    <a href="index.php" class="menu-item <?= $currentPage==='index'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-grid-1x2"></i></div> Dashboard
    </a>

    <div class="menu-section-label">Management</div>
    <a href="products.php" class="menu-item <?= ($currentPage==='products' || ($currentPage==='products' && isset($_GET['action'])))?'active':'' ?>">
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

    <div class="menu-section-label">Marketing & Support</div>
    <a href="coupons.php" class="menu-item <?= $currentPage==='coupons'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-ticket-perforated"></i></div> Coupons
    </a>
    <a href="banners.php" class="menu-item <?= $currentPage==='banners'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-images"></i></div> Banners & Ads
    </a>
    <a href="wa_messages.php" class="menu-item <?= ($currentPage==='wa_messages' && !isset($_GET['tab']))?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-robot"></i></div> Bot Settings
    </a>
    <a href="wa_messages.php?tab=chat" class="menu-item <?= ($currentPage==='wa_messages' && ($_GET['tab']??'')==='chat')?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-chat-dots"></i></div> Customer Chats
    </a>
    <a href="wa_messages.php?tab=broadcast" class="menu-item <?= ($currentPage==='wa_messages' && ($_GET['tab']??'')==='broadcast')?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-megaphone"></i></div> Broadcast
    </a>
    <a href="wa_logs.php" class="menu-item <?= $currentPage==='wa_logs'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-journal-text"></i></div> WhatsApp Logs
    </a>

    <div class="menu-section-label">Affiliate Center</div>
    <a href="affiliates.php" class="menu-item <?= $currentPage==='affiliates'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-person-badge"></i></div> Affiliates
      <?php
      $pendingAffCount = $conn->query("SELECT COUNT(*) as c FROM affiliates WHERE status='pending'")->fetch_assoc()['c'];
      if($pendingAffCount > 0): ?>
      <span class="menu-badge" style="background:var(--warning); color:black;"><?= $pendingAffCount ?></span>
      <?php endif; ?>
    </a>
    <a href="commissions.php" class="menu-item <?= $currentPage==='commissions'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-percent"></i></div> Commissions
      <?php
      $pendingComm = $conn->query("SELECT COUNT(*) as c FROM affiliate_commissions WHERE status='pending'")->fetch_assoc()['c'];
      if($pendingComm > 0): ?>
      <span class="menu-badge"><?= $pendingComm ?></span>
      <?php endif; ?>
    </a>
    <a href="withdrawals.php" class="menu-item <?= $currentPage==='withdrawals'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-cash-stack"></i></div> Withdrawals
      <?php
      $pendingWithdrawals = $conn->query("SELECT COUNT(*) as c FROM affiliate_withdrawals WHERE status='pending'")->fetch_assoc()['c'];
      if($pendingWithdrawals > 0): ?>
      <span class="menu-badge" style="background:var(--warning); color:black;"><?= $pendingWithdrawals ?></span>
      <?php endif; ?>
    </a>

    <div class="menu-section-label">System</div>
    <a href="users.php" class="menu-item <?= $currentPage==='users'?'active':'' ?>">
      <div class="menu-icon"><i class="bi bi-people"></i></div> Users
    </a>

    <div class="menu-section-label">Safety</div>
    <a href="logout.php" class="menu-item" style="margin-top: 1rem;">
      <div class="menu-icon" style="background:rgba(255,101,132,0.15);"><i class="bi bi-box-arrow-right" style="color:var(--danger);"></i></div>
      <span style="color:var(--danger); font-weight: 700;">Logout</span>
    </a>
  </div>
</nav>
