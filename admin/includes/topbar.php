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

// --- NEW ORDER NOTIFICATIONS (WEB PUSH SIMULATION) ---
const notificationSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

async function checkNewOrders() {
    try {
        const res = await fetch('ajax/get_latest_order.php');
        const data = await res.json();
        
        if (data.success && data.latest_order) {
            const order = data.latest_order;
            const lastSeen = localStorage.getItem('last_seen_order_id') || 0;
            
            if (Number(order.id) > Number(lastSeen)) {
                // If it's a first load, just set the ID and don't notify (to avoid spamming)
                if (lastSeen > 0) {
                    showOrderNotification(order);
                }
                localStorage.setItem('last_seen_order_id', order.id);
            }
        }
    } catch (e) { console.error('Order check failed:', e); }
}

function showOrderNotification(order) {
    // 1. Play Sound
    notificationSound.play().catch(e => console.warn('Sound blocked by browser policy.'));

    // 2. Browser Notification
    if ("Notification" in window) {
        if (Notification.permission === "granted") {
            const n = new Notification("🚀 New Order Received!", {
                body: `Order #${order.order_number} by ${order.name} - Total: ₹${Number(order.total).toLocaleString()}`,
                icon: 'https://cdn-icons-png.flaticon.com/512/1162/1162456.png'
            });
            n.onclick = () => { window.location.href = `order_detail.php?id=${order.id}`; n.close(); };
        }
    }
}

// Request Permission on first load
document.addEventListener('DOMContentLoaded', () => {
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }
    // Start Polling every 15 seconds
    setInterval(checkNewOrders, 15000);
    checkNewOrders(); // Initial check
});
// ---------------------------------------------------

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

