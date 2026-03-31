<div class="mobile-header">
  <!-- Left: Menu toggle + Brand -->
  <div style="display: flex; align-items: center; gap: 0.65rem; flex-shrink:0;">
    <button class="nav-icon-btn" onclick="toggleMobileMenu()"
      style="width: 36px; height: 36px; font-size: 1.35rem; border: none; background: var(--glass);">
      <i class="bi bi-list"></i>
    </button>
    <a href="<?= SITE_URL ?>/index" style="text-decoration:none;">
      <div
        style="font-size: 1.25rem; font-weight: 900; background: linear-gradient(135deg, var(--primary), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -0.5px; white-space:nowrap;">
        ✦ MIZ MAX</div>
    </a>
  </div>

  <!-- Center: Products + Categories quick links -->
  <!-- <div style="display: flex; align-items: center; gap: 0.4rem; flex: 1; justify-content: center; padding: 0 0.5rem;">
    <a href="<?= SITE_URL ?>/products"
       style="display:flex; align-items:center; gap:0.35rem; padding:0.35rem 0.85rem; border-radius:50px; font-size:0.8rem; font-weight:700; color:<?= (basename($_SERVER['PHP_SELF']) === 'products.php' && !isset($_GET['category'])) ? '#fff' : 'var(--text-secondary)' ?>; background:<?= (basename($_SERVER['PHP_SELF']) === 'products.php' && !isset($_GET['category'])) ? 'linear-gradient(135deg, var(--primary), var(--primary-dark))' : 'var(--glass)' ?>; border:1px solid <?= (basename($_SERVER['PHP_SELF']) === 'products.php' && !isset($_GET['category'])) ? 'transparent' : 'var(--glass-border)' ?>; text-decoration:none; white-space:nowrap; transition:all 0.25s;">
      <i class="bi bi-grid-3x3-gap-fill" style="font-size:0.8rem;"></i> Products
    </a>
    <button onclick="toggleMobileCatSheet()" id="mobileCatBtn"
       style="display:flex; align-items:center; gap:0.35rem; padding:0.35rem 0.85rem; border-radius:50px; font-size:0.8rem; font-weight:700; color:var(--text-secondary); background:var(--glass); border:1px solid var(--glass-border); cursor:pointer; white-space:nowrap; transition:all 0.25s; font-family:var(--font);">
      <i class="bi bi-tag-fill" style="font-size:0.8rem;"></i> Categories <i class="bi bi-chevron-down" style="font-size:0.6rem;"></i>
    </button>
  </div> -->

  <!-- Right: Theme + Search -->
  <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink:0;">
    <div class="theme-toggle" id="mobileThemeToggle" onclick="toggleTheme()" title="Toggle Theme">
      <i class="bi bi-moon-stars moon-icon"></i>
      <i class="bi bi-sun sun-icon"></i>
      <div class="theme-toggle-dot"></div>
    </div>
    <button onclick="toggleMobileSearch()" class="nav-icon-btn"
      style="width: 36px; height: 36px; font-size: 1.1rem; border: none; background: var(--glass);">
      <i class="bi bi-search"></i>
    </button>
  </div>

  <!-- Mobile Search Overlay -->
  <div id="mobileSearchOverlay"
    style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:var(--drop-bg); z-index:10; padding:0 0.75rem; align-items:center;">
    <form action="<?= SITE_URL ?>/products" method="GET"
      style="flex:1; display:flex; align-items:center; gap:0.5rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:50px; padding:2px; position:relative;">
      <input type="text" name="q" id="mobileSearchInput" placeholder="Search products..." autocomplete="off"
        style="flex:1; background:none; border:none; padding:0.6rem 1.25rem; color:var(--text-primary); outline:none; font-size:0.9rem;">
      <button type="submit"
        style="width:38px; height:38px; border-radius:50%; border:none; background:linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#fff; display:flex; align-items:center; justify-content:center;">
        <i class="bi bi-search"></i>
      </button>
      <button type="button" onclick="toggleMobileSearch()"
        style="background:none; border:none; color:var(--text-muted); padding:0 0.75rem; cursor:pointer;">
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

  function toggleMobileCatSheet() {
    const sheet = document.getElementById('mobileCatSheet');
    const overlay = document.getElementById('mobileCatSheetOverlay');
    const btn = document.getElementById('mobileCatBtn');
    const isOpen = sheet.style.transform === 'translateY(0%)';
    if (!isOpen) {
      overlay.style.display = 'block';
      setTimeout(() => overlay.style.opacity = '1', 10);
      sheet.style.transform = 'translateY(0%)';
      document.body.style.overflow = 'hidden';
      if (btn) btn.style.background = 'linear-gradient(135deg,var(--primary),var(--primary-dark))';
      if (btn) btn.style.color = '#fff';
      if (btn) btn.style.borderColor = 'transparent';
    } else {
      overlay.style.opacity = '0';
      setTimeout(() => overlay.style.display = 'none', 300);
      sheet.style.transform = 'translateY(100%)';
      document.body.style.overflow = '';
      if (btn) btn.style.background = 'var(--glass)';
      if (btn) btn.style.color = 'var(--text-secondary)';
      if (btn) btn.style.borderColor = 'var(--glass-border)';
    }
  }
</script>