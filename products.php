<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$categories = getCategories($pdo);
$currentUser = getCurrentUser($pdo);
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);

// Filters
$q = $_GET['q'] ?? '';
$search = $q;
$q_raw = $q;
$search_html = htmlspecialchars($q);

if (!function_exists('e')) {
  function e($str)
  {
    return htmlspecialchars($str);
  }
}
$category_slug = $_GET['category'] ?? $_GET['cat'] ?? '';
$filter = $_GET['filter'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$min_price = (isset($_GET['min_price']) && $_GET['min_price'] !== '') ? (float) $_GET['min_price'] : 0;
$max_price = (isset($_GET['max_price']) && $_GET['max_price'] !== '') ? (float) $_GET['max_price'] : 999999;
$brand_id = (isset($_GET['brand']) && $_GET['brand'] !== '') ? (int) $_GET['brand'] : 0;
$in_stock = isset($_GET['in_stock']) ? (int) $_GET['in_stock'] : 0;
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build WHERE clause with placeholders
$where = "p.is_active = 1";
$params = [];

if ($q) {
  $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

if ($category_slug) {
  $catStmt = $pdo->prepare("SELECT id FROM categories WHERE slug=? AND is_active=1 LIMIT 1");
  $catStmt->execute([$category_slug]);
  $catRow = $catStmt->fetch();
  if ($catRow) {
    $where .= " AND p.category_id = ?";
    $params[] = $catRow['id'];
  }
}

if ($filter === 'featured')
  $where .= " AND p.is_featured = 1";
if ($filter === 'top')
  $where .= " AND p.is_top = 1";
if ($filter === 'trending')
  $where .= " AND p.is_trending = 1";

if ($brand_id > 0) {
  $where .= " AND p.brand_id = ?";
  $params[] = $brand_id;
}
if ($in_stock) {
  $where .= " AND p.stock > 0";
}

$where .= " AND p.price >= ? AND p.price <= ?";
$params[] = $min_price;
$params[] = $max_price;

// Sort (Allowed sorts only)
$allowedSorts = ['price_asc', 'price_desc', 'popular', 'rating', 'newest'];
if (!in_array($sort, $allowedSorts))
  $sort = 'newest';

$orderBy = match ($sort) {
  'price_asc' => "p.price ASC",
  'price_desc' => "p.price DESC",
  'popular' => "p.review_count DESC",
  'rating' => "p.rating DESC",
  default => "p.created_at DESC"
};

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM products p WHERE $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'] ?? 0;
$totalPages = ceil($total / $perPage);

// Fetch products
$params[] = $perPage;
$params[] = $offset;
$productsStmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $where ORDER BY $orderBy LIMIT ? OFFSET ?");
$productsStmt->execute($params);
$products = $productsStmt->fetchAll();

$pageTitle = $category_slug ? ucfirst(str_replace('-', ' ', $category_slug)) : ($q ? "Search: " . e($q) : "All Products");
$brands = [];
try {
  $brands = $pdo->query("SELECT * FROM brands WHERE is_active=1")->fetchAll();
} catch (Exception $e) {
}

if ($is_ajax) {
  ob_start();
  ?>
  <!-- Products Grid -->
  <?php if (empty($products)): ?>
    <div class="glass-card" style="text-align:center; padding:5rem 2rem; width:100%;">
      <div
        style="width:80px; height:80px; background:var(--glass); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem;">
        <i class="bi bi-search" style="font-size:2rem; color:var(--text-muted);"></i>
      </div>
      <h3 style="color:var(--text-primary); margin-bottom:0.75rem; font-weight:800;">No products found</h3>
      <p style="color:var(--text-muted); margin-bottom:2rem; font-size:0.95rem;">We couldn't find any products matching your
        current filters.</p>
    </div>
  <?php else: ?>
    <div class="products-grid grid-cols-3" id="productsGridAjax">
      <?php foreach ($products as $p): ?>
        <?php include 'includes/product_card.php'; ?>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div style="display:flex; justify-content:center; gap:0.5rem; margin-top:2.5rem; flex-wrap:wrap; width:100%;">
        <?php
        $qParams = $_GET;
        unset($qParams['ajax']); // remove ajax param for links
        for ($pi = 1; $pi <= $totalPages; $pi++):
          $qParams['page'] = $pi;
          $isActive = $pi === $page;
          ?>
          <a href="<?= SITE_URL ?>/products?<?= http_build_query($qParams) ?>" class="ajax-page-link" data-page="<?= $pi ?>"
            style="width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:0.875rem; font-weight:600; background:<?= $isActive ? 'linear-gradient(135deg,var(--primary),var(--primary-dark))' : 'var(--glass)' ?>; color:<?= $isActive ? '#fff' : 'var(--text-secondary)' ?>; border:1px solid <?= $isActive ? 'transparent' : 'var(--glass-border)' ?>; transition:var(--transition);">
            <?= $pi ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  <?php
  $html = ob_get_clean();
  echo json_encode(['html' => $html, 'total' => $total]);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> – MIZ MAX</title>
  <meta name="description" content="Browse our premium product collection at MIZ MAX.">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
</head>
<!-- MOBILE HEADER -->
<?php include 'includes/mobile_header.php'; ?>

<?php include 'includes/navbar.php'; ?>

<div class="page-wrapper">
  <div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="<?= SITE_URL ?>/index">Home</a>
      <span class="separator"><i class="bi bi-chevron-right"></i></span>
      <?php if ($category_slug): ?><a href="<?= SITE_URL ?>/products">Products</a><span class="separator"><i
            class="bi bi-chevron-right"></i></span><span class="current"><?= $pageTitle ?></span>
      <?php elseif ($search): ?><a href="<?= SITE_URL ?>/products">Products</a><span class="separator"><i
            class="bi bi-chevron-right"></i></span><span class="current">Search Results</span>
      <?php else: ?><span class="current">All Products</span><?php endif; ?>
    </div>

    <div class="products-layout-wrapper">
      <!-- SIDEBAR FILTERS -->
      <aside class="filters-sidebar">
        <div class="glass-card" style="padding:1.5rem; position:sticky; top:90px;">
          <h3
            style="font-size:1rem; font-weight:700; margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center;">
            <span><i class="bi bi-sliders"></i> Filters</span>
            <div style="display:flex; gap:1rem; align-items:center;">
              <a href="<?= SITE_URL ?>/products"
                style="font-size:0.75rem; color:var(--text-muted); text-decoration:none;">Clear All</a>
              <button class="d-md-none" onclick="toggleFilters()"
                style="background:none; border:none; color:var(--text-primary); font-size:1.25rem;"><i
                  class="bi bi-x"></i></button>
            </div>
          </h3>

          <form method="GET" action="<?= SITE_URL ?>/products" id="filterForm">
            <?php if ($q_raw): ?><input type="hidden" name="q" value="<?= $search_html ?>"><?php endif; ?>
            <?php if ($sort): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>

            <!-- Categories -->
            <div style="margin-bottom:1.5rem;">
              <div
                style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.875rem;">
                Categories</div>
              <div class="filter-group">
                <label class="filter-option <?= !$category_slug ? 'active' : '' ?>">
                  <input type="radio" name="category" value="" <?= !$category_slug ? 'checked' : '' ?>
                    onchange="applyAjaxFilters()"> All Categories
                </label>
                <?php foreach ($categories as $cat): ?>
                  <label class="filter-option <?= $category_slug === $cat['slug'] ? 'active' : '' ?>">
                    <input type="radio" name="category" value="<?= $cat['slug'] ?>" <?= $category_slug === $cat['slug'] ? 'checked' : '' ?> onchange="applyAjaxFilters()">
                    <span class="filter-icon"><?= $cat['icon'] ?></span> <?= htmlspecialchars($cat['name']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Price Range -->
            <div style="margin-bottom:1.5rem;">
              <div
                style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.875rem;">
                Price Range</div>
              <div style="display:flex; gap:0.5rem; margin-bottom:0.75rem;">
                <!-- Updated onchange -->
                <input type="number" name="min_price" id="min_price"
                  value="<?= isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : '' ?>" placeholder="Min"
                  class="form-control" style="flex:1; padding:0.5rem 0.75rem; font-size:0.85rem;" step="0.01"
                  onchange="applyAjaxFilters()">
                <input type="number" name="max_price" id="max_price"
                  value="<?= isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : '' ?>" placeholder="Max"
                  class="form-control" style="flex:1; padding:0.5rem 0.75rem; font-size:0.85rem;" step="0.01"
                  onchange="applyAjaxFilters()">
              </div>
              <div style="padding:0 0.5rem;"><input type="range" class="form-range" min="0" max="10000" id="priceSlider"
                  oninput="document.getElementById('max_price').value=this.value" onchange="applyAjaxFilters()"></div>
            </div>

            <!-- Brands -->
            <?php if (!empty($brands)): ?>
              <div style="margin-bottom:1.5rem;">
                <div
                  style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.875rem;">
                  Brands</div>
                <div class="filter-group">
                  <label class="filter-option <?= !$brand_id ? 'active' : '' ?>">
                    <input type="radio" name="brand" value="" <?= !$brand_id ? 'checked' : '' ?>
                      onchange="applyAjaxFilters()"> All Brands
                  </label>
                  <?php foreach ($brands as $b): ?>
                    <label class="filter-option <?= $brand_id == $b['id'] ? 'active' : '' ?>">
                      <input type="radio" name="brand" value="<?= $b['id'] ?>" <?= $brand_id == $b['id'] ? 'checked' : '' ?>
                        onchange="applyAjaxFilters()">
                      <?= htmlspecialchars($b['name']) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- Availability -->
            <div style="margin-bottom:1.5rem;">
              <div
                style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.875rem;">
                Availability</div>
              <label class="filter-option <?= $in_stock ? 'active' : '' ?>">
                <input type="checkbox" name="in_stock" value="1" <?= $in_stock ? 'checked' : '' ?>
                  onchange="applyAjaxFilters()">
                In Stock Only
              </label>
            </div>

            <!-- Filter Type -->
            <div style="margin-bottom:1.5rem;">
              <div
                style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.875rem;">
                Product Type</div>
              <div class="filter-group">
                <?php foreach (['' => 'All Products', 'featured' => 'Featured', 'top' => 'Best Sellers', 'trending' => 'Trending'] as $v => $l): ?>
                  <label class="filter-option <?= $filter === $v ? 'active' : '' ?>">
                    <input type="radio" name="filter" value="<?= $v ?>" <?= $filter === $v ? 'checked' : '' ?>
                      onchange="applyAjaxFilters()"> <?= $l ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <button type="submit" class="btn-primary-luxury"
              style="width:100%; justify-content:center; padding:0.65rem;">
              Apply Filters
            </button>
          </form>
        </div>
      </aside>

      <!-- PRODUCT LISTING -->
      <div style="flex:1; min-width:0;">
        <!-- Top Bar -->
        <div class="glass-card"
          style="padding:0.75rem 1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
          <div style="display:flex; align-items:center; gap:1rem;">
            <button class="btn-outline-luxury d-md-none"
              style="padding:0.5rem 1rem; font-size:0.875rem; border-radius:var(--radius-sm);"
              onclick="toggleFilters()">
              <i class="bi bi-sliders"></i> Filter
            </button>
            <div style="color:var(--text-secondary); font-size:0.9rem; font-weight:500;">
              Showing <span style="color:var(--primary); font-weight:700;"><?= $total ?></span> luxury items
            </div>
          </div>

          <div style="display:flex; align-items:center; gap:0.75rem;">
            <!-- Sort Trigger (Mobile Only) -->
            <button class="btn-outline-luxury d-md-none"
              style="padding:0.5rem 1rem; font-size:0.875rem; border-radius:var(--radius-sm);"
              onclick="toggleSortSheet()">
              <i class="bi bi-sort-down"></i> Sort
            </button>

            <!-- Sort Select (Desktop Only) -->
            <div class="d-none d-md-flex" style="align-items:center; gap:0.5rem;">
              <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600; text-transform:uppercase;">Sort
                by:</span>
              <select name="sort" onchange="changeSortAndNavigate(this.value)" class="form-control"
                style="width:160px; padding:0.45rem 0.75rem; font-size:0.875rem; cursor:pointer;">
                <?php foreach (['newest' => 'Newest First', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low', 'popular' => 'Most Popular', 'rating' => 'Top Rated'] as $v => $l): ?>
                  <option value="<?= $v ?>" <?= $sort === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- View Mode (Desktop Only) -->
            <div style="display:flex; gap:0.5rem; padding-left:0.5rem; border-left:1px solid var(--border);"
              class="d-none d-sm-flex">
              <button onclick="setGrid(3)" id="grid4" class="nav-icon-btn active" title="3 Column View"
                style="width:36px; height:36px;"><i class="bi bi-grid-3x3-gap"></i></button>
              <button onclick="setGrid(2)" id="grid2" class="nav-icon-btn" title="2 Column View"
                style="width:36px; height:36px;"><i class="bi bi-grid"></i></button>
            </div>
          </div>
        </div>

        <!-- (Moved to bottom of file) -->

        <div id="ajaxProductContainer" style="position:relative;">
          <div id="filterLoader"
            style="display:none; position:absolute; inset:0; background:rgba(255,255,255,0.5); z-index:10; backdrop-filter:blur(2px); justify-content:center; align-items:center;">
            <div class="loader-ring"></div>
          </div>
          <!-- Products Grid -->
          <?php if (empty($products)): ?>
            <div class="glass-card" style="text-align:center; padding:5rem 2rem; width:100%;">
              <div
                style="width:80px; height:80px; background:var(--glass); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem;">
                <i class="bi bi-search" style="font-size:2rem; color:var(--text-muted);"></i>
              </div>
              <h3 style="color:var(--text-primary); margin-bottom:0.75rem; font-weight:800;">No products found</h3>
              <p style="color:var(--text-muted); margin-bottom:2rem; font-size:0.95rem;">We couldn't find any products
                matching your current filters.</p>
              <a href="<?= SITE_URL ?>/products" class="btn-primary-luxury">
                <i class="bi bi-arrow-left"></i> View All Products
              </a>
            </div>
          <?php else: ?>
            <div class="products-grid grid-cols-3" id="productsGridAjax">
              <?php foreach ($products as $p): ?>
                <?php include 'includes/product_card.php'; ?>
              <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
              <div style="display:flex; justify-content:center; gap:0.5rem; margin-top:2.5rem; flex-wrap:wrap; width:100%;">
                <?php
                $qParams = $_GET;
                for ($pi = 1; $pi <= $totalPages; $pi++):
                  $qParams['page'] = $pi;
                  $isActive = $pi === $page;
                  ?>
                  <a href="<?= SITE_URL ?>/products?<?= http_build_query($qParams) ?>" class="ajax-page-link"
                    data-page="<?= $pi ?>"
                    style="width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:0.875rem; font-weight:600; background:<?= $isActive ? 'linear-gradient(135deg,var(--primary),var(--primary-dark))' : 'var(--glass)' ?>; color:<?= $isActive ? '#fff' : 'var(--text-secondary)' ?>; border:1px solid <?= $isActive ? 'transparent' : 'var(--glass-border)' ?>; transition:var(--transition);">
                    <?= $pi ?>
                  </a>
                <?php endfor; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>


<?php include 'includes/footer.php'; ?>

<!-- Mobile Sort Sheet (Moved for stacking context) -->
<div id="sortSheetOverlay" onclick="toggleSortSheet()"
  style="position:fixed; inset:0; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); z-index:5000; display:none; opacity:0; transition:var(--transition);">
</div>
<div id="sortSheet" class="sort-sheet">
  <div class="sheet-header">
    <div class="sheet-handle"></div>
    <div style="font-weight:800; font-size:1.1rem; color:var(--text-primary);">Sort Products</div>
  </div>
  <div class="sheet-content">
    <?php foreach (['newest' => 'Newest First', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low', 'popular' => 'Most Popular', 'rating' => 'Top Rated'] as $v => $l): ?>
      <label class="sort-sheet-item <?= $sort === $v ? 'active' : '' ?>">
        <input type="radio" name="mobile_sort" value="<?= $v ?>" <?= $sort === $v ? 'checked' : '' ?>
          onchange="changeSortAndNavigate(this.value)">
        <span><?= $l ?></span>
        <i class="bi bi-check2"></i>
      </label>
    <?php endforeach; ?>
  </div>
</div>

<div id="filterOverlay" onclick="toggleFilters()"
  style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:5000; display:none; backdrop-filter:blur(4px);">
</div>

<script>
  function changeSortAndNavigate(sortVal) {
    document.querySelector('select[name="sort"]').value = sortVal;
    applyAjaxFilters(1);
  }

  let searchTimeout;
  function applyAjaxFilters(page = 1) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      const form = document.getElementById('filterForm');
      const formData = new FormData(form);
      const params = new URLSearchParams(formData);

      // Highlight active labels in form
      form.querySelectorAll('label').forEach(l => l.classList.remove('active'));
      form.querySelectorAll('input:checked').forEach(i => {
        if (i.closest('label')) i.closest('label').classList.add('active');
      });

      params.set('ajax', '1');
      params.set('page', page);
      params.set('sort', document.querySelector('select[name="sort"]').value);

      const loader = document.getElementById('filterLoader');
      if (loader) loader.style.display = 'flex';

      fetch(`<?= SITE_URL ?>/products?${params.toString()}`)
        .then(r => r.json())
        .then(data => {
          document.getElementById('ajaxProductContainer').innerHTML = data.html;
          document.querySelector('.filters-sidebar').classList.remove('active');
          document.getElementById('filterOverlay').style.display = 'none';
          // update total showing count gracefully
          const tc = document.querySelector('span[style*="font-weight:700"]');
          if (tc) tc.innerText = data.total;

          // Restore grid class
          const savedGrid = localStorage.getItem('luxeProductGrid');
          if (savedGrid) {
            const g = document.getElementById('productsGridAjax');
            if (g) {
              g.className = 'products-grid grid-cols-' + savedGrid;
            }
          }

          // attach ajax to new pagination
          bindAjaxPagination();

          // Update URL visually
          params.delete('ajax');
          window.history.replaceState({}, '', `<?= SITE_URL ?>/products?${params.toString()}`);
        })
        .catch(err => console.error(err));
    }, 300);
  }

  function bindAjaxPagination() {
    document.querySelectorAll('.ajax-page-link').forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        applyAjaxFilters(this.getAttribute('data-page'));
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  }

  // Initial bind
  document.addEventListener('DOMContentLoaded', () => {
    bindAjaxPagination();
  });

  function setGrid(cols) {
    const grid = document.getElementById('productsGridAjax');
    const btnGrid = document.getElementById('grid4');
    const btnList = document.getElementById('grid2');

    if (grid) {
      grid.classList.remove('grid-cols-2', 'grid-cols-3', 'grid-cols-4');
      grid.classList.add('grid-cols-' + cols);
    }

    if (cols === 2) {
      btnList.classList.add('active');
      btnGrid.classList.remove('active');
    } else {
      btnGrid.classList.add('active');
      btnList.classList.remove('active');
    }
    localStorage.setItem('luxeProductGrid', cols);
  }

  function toggleFilters() {
    const sidebar = document.querySelector('.filters-sidebar');
    const overlay = document.getElementById('filterOverlay');
    const isActive = sidebar.classList.toggle('active');
    if (overlay) {
      overlay.style.display = isActive ? 'block' : 'none';
    }
    document.body.style.overflow = isActive ? 'hidden' : '';
  }

  function toggleSortSheet() {
    const sheet = document.getElementById('sortSheet');
    const overlay = document.getElementById('sortSheetOverlay');
    const isShowing = sheet.classList.toggle('active');

    if (isShowing) {
      overlay.style.display = 'block';
      setTimeout(() => overlay.style.opacity = '1', 10);
      document.body.style.overflow = 'hidden';
    } else {
      overlay.style.opacity = '0';
      setTimeout(() => overlay.style.display = 'none', 300);
      document.body.style.overflow = '';
    }
  }

  // Load preferred grid view
  window.addEventListener('DOMContentLoaded', () => {
    const savedGrid = localStorage.getItem('luxeProductGrid');
    if (savedGrid) setGrid(parseInt(savedGrid));
  });
</script>

<?php include 'includes/mobile_sheets.php'; ?>
<?php include 'includes/bottom_nav.php'; ?>
</body>

</html>