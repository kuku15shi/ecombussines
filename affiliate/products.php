<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/affiliate_auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAffiliateLogin();
$affId = $_SESSION['affiliate_id'];

// Get affiliate referral code
$stmt = $pdo->prepare("SELECT referral_code FROM affiliates WHERE id = ?");
$stmt->execute([$affId]);
$affCode = $stmt->fetchColumn();

// Get search/category filters
$search = $_GET['search'] ?? '';
$categoryId = $_GET['category'] ?? '';

// Build query
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1";

$params = [];
if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.short_description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryId) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

$sql .= " ORDER BY p.created_at DESC";

// Check if it's an AJAX request for search
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    if (empty($products)) {
        echo '<div class="col-12 text-center py-5">
                <div class="mb-4">
                    <span class="p-4 rounded-circle bg-light d-inline-block">
                        <i class="bi bi-search fs-1 text-muted"></i>
                    </span>
                </div>
                <h4 class="fw-800">No matches found</h4>
                <p class="text-secondary">Try refining your search or changing categories.</p>
              </div>';
    } else {
        foreach ($products as $p) {
            $productLink = SITE_URL . "/product.php?slug=" . $p['slug'] . "&ref=" . $affCode;
            $mainImg = getProductFirstImage($p['images']);
            $commRate = ($p['affiliate_commission'] !== null) ? (float)$p['affiliate_commission'] : (float)AFFILIATE_COMMISSION_PERCENT;
            $earning = $p['price'] * ($commRate / 100);
            
            echo '<div class="col-xl-3 col-lg-4 col-md-6 mb-4 product-item">
                    <div class="product-card-aff">
                        <div class="product-img-wrapper">
                            <img src="'.SITE_URL.'/uploads/'.$mainImg.'" onerror="this.src=\''.SITE_URL.'/assets/img/default_product.jpg\'" alt="'.htmlspecialchars($p['name']).'">
                        </div>
                        <div class="product-info">
                            <span class="category-tag">'.htmlspecialchars($p['category_name'] ?: 'Uncategorized').'</span>
                            <h3 class="product-name">'.htmlspecialchars($p['name']).'</h3>
                            <div class="product-price">'.formatPrice($p['price']).'</div>
                            
                            <div class="mt-auto">
                                <div class="commission-pill">
                                    <i class="bi bi-plus-circle-fill"></i> Earn '.formatPrice($earning).'
                                </div>
                                <div class="share-actions">
                                    <button class="get-link-btn" onclick="copyProductLink(\''.$productLink.'\', \''.addslashes($p['name']).'\')">
                                        <i class="bi bi-link-45deg"></i> Get Link
                                    </button>
                                    <a href="https://wa.me/?text=Check out this '.addslashes($p['name']).': '.$productLink.'" target="_blank" class="social-share-btn" style="color: #25D366;"><i class="bi bi-whatsapp"></i></a>
                                    <a href="https://twitter.com/intent/tweet?text=Check out this '.addslashes($p['name']).'&url='.$productLink.'" target="_blank" class="social-share-btn" style="color: #1DA1F2;"><i class="bi bi-twitter-x"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
        }
    }
    exit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Product Links - <?= SITE_NAME ?></title>
    <?php include 'includes/head.php'; ?>
    <style>
        .product-card-aff {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .product-card-aff:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            border-color: var(--primary);
        }
        .product-img-wrapper {
            position: relative;
            height: 240px;
            overflow: hidden;
            background: #f8fafc;
        }
        .product-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .product-card-aff:hover .product-img-wrapper img {
            transform: scale(1.1);
        }
        .product-info {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .category-tag {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            width: fit-content;
            margin-bottom: 0.75rem;
        }
        .product-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #1e293b;
            line-height: 1.4;
            height: 3rem;
            overflow: hidden;
        }
        .product-price {
            font-weight: 800;
            font-size: 1.25rem;
            color: #1e293b;
            margin-bottom: 1.25rem;
        }
        .commission-pill {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.5rem 1rem;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2);
        }
        .share-actions {
            display: grid;
            grid-template-columns: 1fr 48px 48px;
            gap: 10px;
            margin-top: auto;
        }
        .get-link-btn {
            background: #1e293b;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .get-link-btn:hover {
            background: #0f172a;
            color: white;
            transform: scale(1.02);
        }
        .social-share-btn {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid #e2e8f0;
            background: white;
            color: #64748b;
            transition: all 0.2s;
        }
        .social-share-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #f8fafc;
        }
        .filter-header {
            background: white;
            padding: 2.5rem;
            border-radius: 24px;
            border: 1px solid #f1f5f9;
            margin-bottom: 3rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
        }
        .search-input-group {
            position: relative;
        }
        .search-input-group i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 5;
        }
        .search-input-group input {
            padding-left: 3rem !important;
            padding-right: 3rem !important;
            height: 54px;
            border-radius: 14px !important;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            font-weight: 500;
        }
        .search-input-group input:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .clear-search {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            display: none;
            z-index: 5;
            transition: color 0.2s;
        }
        .clear-search:hover { color: #ef4444; }

        /* Shimmer loading effect */
        .shimmer {
            background: #f6f7f8;
            background-image: linear-gradient(to right, #f6f7f8 0%, #edeef1 20%, #f6f7f8 40%, #f6f7f8 100%);
            background-repeat: no-repeat;
            background-size: 800px 104px; 
            display: inline-block;
            position: relative; 
            animation-duration: 1s;
            animation-fill-mode: forwards; 
            animation-iteration-count: infinite;
            animation-name: shimmer;
            animation-timing-function: linear;
        }
        @keyframes shimmer {
            0% { background-position: -468px 0; }
            100% { background-position: 468px 0; }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 style="font-weight: 800; font-size: 2.25rem; letter-spacing: -1px; color: #1e293b;">Product Links</h1>
                <p class="text-secondary mb-0 fw-500">Generate affiliate links for specific products and earn commissions.</p>
            </div>
            <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        </header>

        <!-- Filters -->
        <div class="filter-header">
            <form id="searchForm" method="GET" class="row g-4 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-800 text-uppercase text-secondary mb-2" style="letter-spacing: 0.5px;">Search Products</label>
                    <div class="search-input-group">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search by name, category or info..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                        <i class="bi bi-x-circle-fill clear-search" id="clearSearch"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-800 text-uppercase text-secondary mb-2" style="letter-spacing: 0.5px;">Specific Category</label>
                    <select id="categorySelect" name="category" class="form-select" style="height: 54px; border-radius: 14px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-weight: 600;">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary-luxury w-100 py-3 rounded-4" style="height: 54px;">
                        <i class="bi bi-lightning-charge-fill me-2"></i> Update View
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="row g-4" id="productsGrid">
            <?php if (empty($products)): ?>
                <div class="col-12 text-center py-5">
                    <div class="mb-4">
                        <span class="p-4 rounded-circle bg-light d-inline-block">
                            <i class="bi bi-search fs-1 text-muted"></i>
                        </span>
                    </div>
                    <h4 class="fw-800">No matches found</h4>
                    <p class="text-secondary">Try refining your search or changing categories.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $p): 
                    $productLink = SITE_URL . "/product.php?slug=" . $p['slug'] . "&ref=" . $affCode;
                    $mainImg = getProductFirstImage($p['images']);
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4 product-item">
                    <div class="product-card-aff">
                        <div class="product-img-wrapper">
                            <img src="<?= SITE_URL ?>/uploads/<?= $mainImg ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'" alt="<?= htmlspecialchars($p['name']) ?>">
                        </div>
                        <div class="product-info">
                            <span class="category-tag"><?= htmlspecialchars($p['category_name'] ?: 'Uncategorized') ?></span>
                            <h3 class="product-name"><?= htmlspecialchars($p['name']) ?></h3>
                            <div class="product-price"><?= formatPrice($p['price']) ?></div>
                            
                            <div class="mt-auto">
                                <div class="commission-pill">
                                    <?php 
                                        $commRate = ($p['affiliate_commission'] !== null) ? (float)$p['affiliate_commission'] : (float)AFFILIATE_COMMISSION_PERCENT;
                                        $earning = $p['price'] * ($commRate / 100);
                                    ?>
                                    <i class="bi bi-plus-circle-fill"></i> Earn <?= formatPrice($earning) ?>
                                </div>
                                <div class="share-actions">
                                    <button class="get-link-btn" onclick="copyProductLink('<?= $productLink ?>', '<?= addslashes($p['name']) ?>')">
                                        <i class="bi bi-link-45deg"></i> Get Link
                                    </button>
                                    <a href="https://wa.me/?text=Check out this <?= addslashes($p['name']) ?>: <?= $productLink ?>" target="_blank" class="social-share-btn" style="color: #25D366;"><i class="bi bi-whatsapp"></i></a>
                                    <a href="https://twitter.com/intent/tweet?text=Check out this <?= addslashes($p['name']) ?>&url=<?= $productLink ?>" target="_blank" class="social-share-btn" style="color: #1DA1F2;"><i class="bi bi-twitter-x"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.getElementById('clearSearch');
        const categorySelect = document.getElementById('categorySelect');
        const productsGrid = document.getElementById('productsGrid');
        const searchForm = document.getElementById('searchForm');
        let searchTimeout;

        // Toggle clear button
        const toggleClearBtn = () => {
            clearBtn.style.display = searchInput.value.length > 0 ? 'block' : 'none';
        };

        // Live Search Logic
        const performSearch = () => {
            const query = searchInput.value;
            const cat = categorySelect.value;
            
            // Show loading state
            productsGrid.style.opacity = '0.5';
            
            fetch(`products.php?search=${encodeURIComponent(query)}&category=${cat}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.text())
            .then(html => {
                productsGrid.innerHTML = html;
                productsGrid.style.opacity = '1';
                // Re-initialize any dynamic components if needed
            })
            .catch(err => {
                console.error('Search failed:', err);
                productsGrid.style.opacity = '1';
            });
        };

        searchInput.addEventListener('input', () => {
            toggleClearBtn();
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 400); // 400ms debounce
        });

        categorySelect.addEventListener('change', performSearch);

        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            toggleClearBtn();
            performSearch();
            searchInput.focus();
        });

        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            performSearch();
        });

        // Initialize toggle
        toggleClearBtn();

        function copyProductLink(link, name) {
            navigator.clipboard.writeText(link).then(() => {
                Swal.fire({
                    title: 'Link Secured!',
                    text: 'Affiliate link for ' + name + ' is ready to share.',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true,
                    background: '#fff',
                    color: '#1e293b'
                });
            });
        }
    </script>
</body>
</html>
