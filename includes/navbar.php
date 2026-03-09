<!-- Navbar Partial -->
<?php
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
$currentUser = getCurrentUser($pdo);
$categories = getCategories($pdo);
?>
<!-- <div class="page-loader" id="pageLoader">
  <div class="loader-ring"></div>
  <div class="loader-brand">LuxeStore</div>
</div> -->
<div class="toast-container" id="toastContainer"></div>
<button class="back-to-top" id="backToTop" onclick="scrollToTop()"><i class="bi bi-arrow-up"></i></button>

<nav class="navbar">
  <div class="container">
    <div style="display:flex; align-items:center; gap:1.25rem; width:100%;">
      <a href="<?= SITE_URL ?>/index" class="navbar-brand">✦ LuxeStore</a>
      <form action="<?= SITE_URL ?>/products" method="GET" class="search-wrapper" style="flex:1; max-width:420px;">
        <input type="text" name="q" class="search-input" id="liveSearch" placeholder="Search products..." autocomplete="off">
        <button type="submit" class="search-btn-proper">
          <i class="bi bi-search"></i>
        </button>
        <div class="search-results-dropdown" id="searchDropdown"></div>
      </form>
      <div style="display:flex; align-items:center; gap:0.25rem;" class="d-none d-lg-flex">
        <a href="<?= SITE_URL ?>/index" class="nav-link">Home</a>
        <a href="<?= SITE_URL ?>/products" class="nav-link">Products</a>
        <div style="position:relative;" id="catDropdown">
          <a href="#" class="nav-link" onclick="toggleCatMenu(event)">Categories <i class="bi bi-chevron-down" style="font-size:0.7rem;"></i></a>
          <div class="search-results-dropdown" id="catMenu" style="width:220px; right:0; left:auto;">
            <?php foreach($categories as $cat): ?>
            <a href="<?= SITE_URL ?>/category/<?= $cat['slug'] ?>" class="search-result-item">
              <i class="<?= $cat['icon'] ?>" style="font-size:1.2rem; color:var(--primary); width:28px;"></i>
              <span class="item-name"><?= htmlspecialchars($cat['name']) ?></span>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:0.5rem; margin-left:auto;">
        <div class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle Theme">
          <i class="bi bi-moon-stars moon-icon"></i>
          <i class="bi bi-sun sun-icon"></i>
          <div class="theme-toggle-dot"></div>
        </div>
        <a href="<?= SITE_URL ?>/wishlist" class="nav-icon-btn">
          <i class="bi bi-heart"></i>
          <?php if($wishlistCount > 0): ?><span class="badge-count"><?= $wishlistCount ?></span><?php endif; ?>
        </a>
        <a href="<?= SITE_URL ?>/cart" class="nav-icon-btn">
          <i class="bi bi-bag"></i>
          <span class="badge-count" id="cartBadge"><?= $cartCount ?></span>
        </a>
        <?php if($currentUser): ?>
        <div style="position:relative;" id="userDropdown">
          <button onclick="toggleUserMenu()" class="nav-icon-btn"><i class="bi bi-person"></i></button>
          <div class="search-results-dropdown" style="width:200px; right:0; left:auto;" id="userMenu">
            <div style="padding:0.875rem; border-bottom:1px solid var(--border);">
              <div style="font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($currentUser['name']) ?></div>
            </div>
            <a href="<?= SITE_URL ?>/orders" class="search-result-item"><i class="bi bi-bag-check" style="color:var(--primary);"></i><span class="item-name">My Orders</span></a>
            <a href="<?= SITE_URL ?>/profile" class="search-result-item"><i class="bi bi-person-circle" style="color:var(--accent);"></i><span class="item-name">Profile</span></a>
            <a href="<?= SITE_URL ?>/logout" class="search-result-item"><i class="bi bi-box-arrow-right" style="color:var(--danger);"></i><span class="item-name">Logout</span></a>
          </div>
        </div>
        <?php else: ?>
        <a href="<?= SITE_URL ?>/login" class="btn-primary-luxury" style="padding:0.5rem 1.25rem; font-size:0.82rem;"><i class="bi bi-person"></i> Login</a>
        <?php endif; ?>
        <button class="nav-icon-btn d-lg-none" id="mobileMenuBtn" onclick="toggleMobileMenu()"><i class="bi bi-list"></i></button>
      </div>
    </div>
  </div>
</nav>

<!-- Mobile Sidebar (Offcanvas) -->
<div class="mobile-sidebar-overlay" id="mobileSidebarOverlay" onclick="toggleMobileMenu()"></div>
<div class="mobile-sidebar" id="mobileSidebar">
  <div class="sidebar-header" style="background: linear-gradient(135deg, var(--bg-dark), var(--glass));">
    <div class="brand-logo" style="display: flex; align-items: center; gap: 0.5rem;">
      <span style="font-size: 1.5rem;">✦</span>
      <span style="letter-spacing: -0.5px;">LuxeStore</span>
    </div>
    <button class="close-btn" onclick="toggleMobileMenu()"><i class="bi bi-x-lg"></i></button>
  </div>
  
  <div class="sidebar-content">
    <?php if($currentUser): ?>
    <div class="menu-section user-welcome" style="background: var(--glass); padding: 1.25rem; border-radius: var(--radius-sm); margin-bottom: 2rem; border: 1px solid var(--glass-border);">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; color: #fff; box-shadow: 0 4px 12px rgba(108, 99, 255, 0.3);">
          <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Welcome back,</div>
          <div style="font-weight: 800; font-size: 1rem; color: var(--text-primary);"><?= explode(' ', $currentUser['name'])[0] ?></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="menu-section">
      <div class="section-label">Main Navigation</div>
      <a href="<?= SITE_URL ?>/index" class="menu-item <?= basename($_SERVER['PHP_SELF'])=='index.php'?'active':'' ?>">
        <i class="bi bi-house-heart"></i> Home
      </a>
      <a href="<?= SITE_URL ?>/products" class="menu-item <?= basename($_SERVER['PHP_SELF'])=='products.php'?'active':'' ?>">
        <i class="bi bi-grid-3x3-gap"></i> All Products
      </a>
      <a href="<?= SITE_URL ?>/cart" class="menu-item <?= basename($_SERVER['PHP_SELF'])=='cart.php'?'active':'' ?>">
        <i class="bi bi-bag-heart"></i> My Shopping Cart
      </a>
      <a href="<?= SITE_URL ?>/wishlist" class="menu-item <?= basename($_SERVER['PHP_SELF'])=='wishlist.php'?'active':'' ?>">
        <i class="bi bi-heart"></i> My Wishlist
      </a>
    </div>

    <div class="menu-section">
      <div class="section-label">Shop by Category</div>
      <div style="display: grid; grid-template-columns: 1fr; gap: 0.25rem;">
        <?php foreach($categories as $cat): ?>
        <a href="<?= SITE_URL ?>/category/<?= $cat['slug'] ?>" class="menu-item">
          <i>
            <?php if (strlen($cat['icon']) > 5 || strpos($cat['icon'], 'bi-') !== false): ?>
              <span class="<?= $cat['icon'] ?>"></span>
            <?php else: ?>
              <span style="font-style: normal; font-size: 1rem;"><?= $cat['icon'] ?></span>
            <?php endif; ?>
          </i>
          <?= htmlspecialchars($cat['name']) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="sidebar-footer">
    <div class="section-label">User Account</div>
    <?php if($currentUser): ?>
      <div style="display: grid; grid-template-columns: 1fr; gap: 0.25rem;">
        <a href="<?= SITE_URL ?>/profile" class="menu-item">
          <i class="bi bi-person-circle"></i> My Profile
        </a>
        <a href="<?= SITE_URL ?>/orders" class="menu-item">
          <i class="bi bi-bag-check"></i> Orders
        </a>
        <a href="<?= SITE_URL ?>/logout" class="menu-item" style="color:var(--danger); border-color: rgba(255,101,132,0.1);">
          <i class="bi bi-box-arrow-right" style="color:var(--danger);"></i> Logout
        </a>
      </div>
    <?php else: ?>
      <a href="<?= SITE_URL ?>/login" class="btn-login">
        <i class="bi bi-box-arrow-in-right"></i> Login / Register
      </a>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleMobileMenu() {
  const sidebar = document.getElementById('mobileSidebar');
  const overlay = document.getElementById('mobileSidebarOverlay');
  const isActive = sidebar.classList.toggle('active');
  overlay.classList.toggle('active');
  document.body.style.overflow = isActive ? 'hidden' : '';
}
const _siteUrl = '<?= SITE_URL ?>';
const _uploadUrl = '<?= UPLOAD_URL ?>';

// Theme Logic
(function() {
  const savedTheme = localStorage.getItem('luxeTheme') || 'light';
  document.documentElement.setAttribute('data-theme', savedTheme);
})();

function toggleTheme() {
  const html = document.documentElement;
  const current = html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  const target = current === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', target);
  localStorage.setItem('luxeTheme', target);
  document.cookie = "luxeTheme=" + target + ";path=/;max-age=" + (365*24*60*60);
}

// UI Helpers
function toggleCatMenu(e) { 
  e.preventDefault(); 
  const m = document.getElementById('catMenu'); 
  if(m) m.style.display = m.style.display === 'block' ? 'none' : 'block'; 
}

function toggleUserMenu() { 
  const m = document.getElementById('userMenu'); 
  if(m) m.style.display = m.style.display === 'block' ? 'none' : 'block'; 
}

document.addEventListener('click', (e) => {
  if (!e.target.closest('#catDropdown')) { const m = document.getElementById('catMenu'); if(m) m.style.display = 'none'; }
  if (!e.target.closest('#userDropdown')) { const m = document.getElementById('userMenu'); if(m) m.style.display = 'none'; }
});

// Hide loader
const hideLoader = () => {
  const l = document.getElementById('pageLoader');
  if(l && !l.classList.contains('hidden')) {
    l.classList.add('hidden');
    setTimeout(() => l.remove(), 800);
  }
};
window.addEventListener('load', hideLoader);
// Safety fallback
setTimeout(hideLoader, 3000);

const btt = document.getElementById('backToTop');
if(btt) {
  window.addEventListener('scroll', () => btt.classList.toggle('show', window.scrollY > 400));
}
function scrollToTop() { window.scrollTo({top:0, behavior:'smooth'}); }

// Live Search Logic
function initLiveSearch(inputId, dropdownId) {
  const input = document.getElementById(inputId);
  const dd = document.getElementById(dropdownId);
  if (!input || !dd) return;

  let st;
  input.addEventListener('input', function() {
    clearTimeout(st);
    const q = this.value.trim();
    if (q.length < 2) { dd.style.display = 'none'; return; }
    st = setTimeout(() => {
      fetch(_siteUrl + '/ajax/search.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
          if(!data.length) { dd.style.display = 'none'; return; }
          let html = data.map(p => `
            <a href="${_siteUrl}/product/${p.slug}" class="search-result-item">
              <img src="${_uploadUrl}${p.img}" onerror="this.src='${_siteUrl}/assets/img/default_product.jpg'" alt="">
              <div>
                <div class="item-name">${p.name}</div>
                <div class="item-price">${p.price}</div>
              </div>
            </a>`).join('');
          
          html += `<a href="${_siteUrl}/products?q=${encodeURIComponent(q)}" class="search-result-item" style="justify-content:center; background:rgba(108,99,255,0.05); border-top:1px solid var(--border);">
                    <span style="font-weight:600; color:var(--primary); font-size:0.85rem;">View all results for "${q}"</span>
                   </a>`;
          
          dd.innerHTML = html;
          dd.style.display = 'block';
        });
    }, 300);
  });
}

initLiveSearch('liveSearch', 'searchDropdown');
initLiveSearch('mobileSearchInput', 'mobileSearchDropdown');

document.addEventListener('click', (e) => {
  if (!e.target.closest('.search-wrapper') && !e.target.closest('#mobileSearchOverlay')) {
    const dd = document.getElementById('searchDropdown');
    const mdd = document.getElementById('mobileSearchDropdown');
    if(dd) dd.style.display = 'none';
    if(mdd) mdd.style.display = 'none';
  }
});

// Toast System
function showToast(msg, type = 'success') {
  const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill', warning: 'bi-exclamation-triangle-fill' };
  const t = document.createElement('div');
  t.className = `toast-msg toast-${type}`;
  t.innerHTML = `<div class="toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div><div class="toast-text">${msg}</div>`;
  const tc = document.getElementById('toastContainer');
  if(tc) {
    tc.appendChild(t);
    setTimeout(() => { t.classList.add('fadeout'); setTimeout(() => t.remove(), 300); }, 3500);
  }
}

// Cart & Wishlist
function addToCart(productId, btn) {
  btn.disabled = true;
  fetch(_siteUrl + '/ajax/cart.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=add&product_id=' + productId
  }).then(r => r.json()).then(data => {
    btn.disabled = false;
    showToast(data.message || (data.success ? 'Added!' : 'Error'), data.success ? 'success' : 'error');
    const b = document.getElementById('cartBadge');
    if(b && data.cartCount !== undefined) b.textContent = data.cartCount;
  }).catch(() => { btn.disabled = false; showToast('Error', 'error'); });
}

function toggleWishlist(productId, btn) {
  fetch(_siteUrl + '/ajax/wishlist.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'product_id=' + productId
  }).then(r => r.json()).then(data => {
    if (data.success) {
      btn.classList.toggle('active', data.in_wishlist);
      btn.innerHTML = data.in_wishlist ? '<i class="bi bi-heart-fill"></i>' : '<i class="bi bi-heart"></i>';
      showToast(data.message, 'success');
    } else showToast(data.message || 'Please login', 'error');
  });
}
</script>

