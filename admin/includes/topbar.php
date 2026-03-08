<?php
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<script>
// Theme Logic
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('luxeTheme', theme);
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'light';
  const target = current === 'dark' ? 'light' : 'dark';
  applyTheme(target);
}

// Initial application
try {
  const savedTheme = localStorage.getItem('luxeTheme') || 'light';
  applyTheme(savedTheme);
} catch (e) {
  console.warn('LocalStorage not available, falling back to light theme');
  applyTheme('light');
}

function toggleSidebar() {
  console.log('Toggle Sidebar Clicked');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (sidebar) {
    const isOpen = sidebar.classList.toggle('open');
    console.log('Sidebar state:', isOpen);
    if (overlay) overlay.classList.toggle('active', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
  } else {
    console.error('Sidebar element not found!');
  }
}

function closeSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if(sidebar) sidebar.classList.remove('open');
  if(overlay) overlay.classList.remove('active');
  document.body.style.overflow = '';
}

// Close sidebar on desktop resize
window.addEventListener('resize', () => {
  if (window.innerWidth > 991) closeSidebar();
});
</script>

<!-- Sidebar Overlay (mobile backdrop) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="topbar">
  <div style="display:flex; align-items:center; gap:1rem;">
    <button type="button" onclick="toggleSidebar()" id="sidebarToggle" title="Toggle Menu" class="btn-icon">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-title"><?= $pageTitle ?></div>
  </div>
  <div class="topbar-right">
    <div class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle Theme">
      <i class="bi bi-moon-stars moon-icon"></i>
      <i class="bi bi-sun sun-icon"></i>
      <div class="theme-toggle-dot" id="themeToggleDot"></div>
    </div>
    
    <a href="../index.php" target="_blank" class="nav-icon-btn" title="View Store" style="display:flex; align-items:center; gap:0.5rem; color:var(--text-muted); text-decoration:none;">
      <i class="bi bi-box-arrow-up-right"></i>
      <span class="store-link-text d-none d-md-inline" style="font-size:0.8rem; font-weight:600;">Store</span>
    </a>

    <div style="display:flex; align-items:center; gap:0.75rem;">
      <div style="text-align:right;" class="admin-name-wrap d-sm-block">
        <div style="font-size:0.8rem; font-weight:600;"><?= htmlspecialchars($adminName) ?></div>
        <div style="font-size:0.7rem; color:var(--text-muted);">Super Admin</div>
      </div>
      <div class="admin-avatar"><?= strtoupper(substr($adminName,0,1)) ?></div>
    </div>
  </div>
</div>

