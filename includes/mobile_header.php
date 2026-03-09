<div class="mobile-header">
  <div style="display: flex; align-items: center; gap: 0.75rem;">
    <button class="nav-icon-btn" onclick="toggleMobileMenu()" style="width: 36px; height: 36px; font-size: 1.35rem; border: none; background: var(--glass);">
      <i class="bi bi-list"></i>
    </button>
    <a href="<?= SITE_URL ?>/index" style="text-decoration:none;">
      <div style="font-size: 1.35rem; font-weight: 900; background: linear-gradient(135deg, var(--primary), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -0.5px;">✦ LuxeStore</div>
    </a>
  </div>
  <div style="display: flex; align-items: center; gap: 0.75rem;">
    <div class="theme-toggle" id="mobileThemeToggle" onclick="toggleTheme()" title="Toggle Theme">
      <i class="bi bi-moon-stars moon-icon"></i>
      <i class="bi bi-sun sun-icon"></i>
      <div class="theme-toggle-dot"></div>
    </div>
    <button onclick="toggleMobileSearch()" class="nav-icon-btn" style="width: 36px; height: 36px; font-size: 1.1rem; border: none; background: var(--glass);">
      <i class="bi bi-search"></i>
    </button>
  </div>

  <!-- Mobile Search Overlay -->
  <div id="mobileSearchOverlay" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:var(--drop-bg); z-index:10; padding:0 0.75rem; align-items:center;">
    <form action="<?= SITE_URL ?>/products" method="GET" style="flex:1; display:flex; align-items:center; gap:0.5rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:50px; padding:2px; position:relative;">
      <input type="text" name="q" id="mobileSearchInput" placeholder="Search products..." autocomplete="off" style="flex:1; background:none; border:none; padding:0.6rem 1.25rem; color:var(--text-primary); outline:none; font-size:0.9rem;">
      <button type="submit" style="width:38px; height:38px; border-radius:50%; border:none; background:linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#fff; display:flex; align-items:center; justify-content:center;">
        <i class="bi bi-search"></i>
      </button>
      <button type="button" onclick="toggleMobileSearch()" style="background:none; border:none; color:var(--text-muted); padding:0 0.75rem; cursor:pointer;">
        <i class="bi bi-x-lg"></i>
      </button>
      <div class="search-results-dropdown" id="mobileSearchDropdown"></div>
    </form>
  </div>
</div>

<script>
function toggleMobileSearch() {
  const overlay = document.getElementById('mobileSearchOverlay');
  if (overlay.style.display === 'none' || overlay.style.display === '') {
    overlay.style.display = 'flex';
    document.getElementById('mobileSearchInput').focus();
  } else {
    overlay.style.display = 'none';
  }
}
</script>
