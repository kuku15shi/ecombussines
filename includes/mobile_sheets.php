<!-- Mobile Categories Bottom Sheet -->
<div id="mobileCatSheetOverlay" onclick="toggleMobileCatSheet()" style="position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(6px); z-index:5000; display:none; opacity:0; transition:opacity 0.3s;"></div>
<div id="mobileCatSheet" style="position:fixed; bottom:0; left:0; right:0; background:var(--drop-bg); border-top:1px solid var(--glass-border); border-radius:24px 24px 0 0; z-index:5001; transform:translateY(100%); transition:transform 0.4s cubic-bezier(0.19,1,0.22,1); padding:1rem 1.25rem 2.5rem;">
  <div style="text-align:center; margin-bottom:1.25rem;">
    <div style="width:40px; height:4px; background:var(--border); border-radius:10px; display:inline-block;"></div>
  </div>
  <div style="font-weight:800; font-size:1rem; margin-bottom:1.25rem; color:var(--text-primary);">
    <i class="bi bi-tag-fill" style="color:var(--primary); margin-right:0.5rem;"></i>Shop by Category
  </div>
  <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.75rem; max-height:60vh; overflow-y:auto; padding-bottom:0.5rem;">
    <!-- All Products -->
    <a href="<?= SITE_URL ?>/products" onclick="toggleMobileCatSheet()"
       style="display:flex; flex-direction:column; align-items:center; gap:0.5rem; padding:0.875rem 0.5rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:14px; text-decoration:none; color:var(--text-primary); transition:0.25s;">
      <div style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--accent2)); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.1rem;">
        <i class="bi bi-grid-fill"></i>
      </div>
      <span style="font-size:0.72rem; font-weight:700; text-align:center; line-height:1.2;">All</span>
    </a>
    <?php foreach($categories as $cat): ?>
    <a href="<?= SITE_URL ?>/category/<?= $cat['slug'] ?>" onclick="toggleMobileCatSheet()"
       style="display:flex; flex-direction:column; align-items:center; gap:0.5rem; padding:0.875rem 0.5rem; background:var(--glass); border:1px solid var(--glass-border); border-radius:14px; text-decoration:none; color:var(--text-primary); transition:0.25s;">
      <div style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,rgba(108,99,255,0.15),rgba(250,112,154,0.12)); display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:var(--primary);">
        <?php if(strpos($cat['icon'], 'bi-') !== false): ?>
          <i class="<?= $cat['icon'] ?>"></i>
        <?php else: ?>
          <span style="font-style:normal;"><?= $cat['icon'] ?></span>
        <?php endif; ?>
      </div>
      <span style="font-size:0.72rem; font-weight:700; text-align:center; line-height:1.2;"><?= htmlspecialchars($cat['name']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
