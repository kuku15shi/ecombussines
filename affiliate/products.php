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
    $sql .= " AND (p.name LIKE ? OR p.short_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryId) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

$sql .= " ORDER BY p.created_at DESC";
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
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card-aff:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-color: var(--primary);
        }
        .product-img-wrapper {
            position: relative;
            height: 200px;
            background: #f8f9fa;
        }
        .product-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-info {
            padding: 1.25rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .category-tag {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: block;
        }
        .product-name {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: #1a1a1a;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.4rem;
        }
        .product-price {
            font-weight: 900;
            font-size: 1.1rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .commission-badge {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .share-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 100%;
            padding: 0.75rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .share-btn:hover {
            background: var(--primary-dark);
            color: white;
        }
        .filter-header {
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .search-input {
            background: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
        }
        .search-input:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }
        .category-select {
            background: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 style="font-weight: 800; font-size: 2rem;">Product Links</h1>
                <p class="text-secondary mb-0">Generate affiliate links for specific products and earn commissions.</p>
            </div>
            <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        </header>

        <!-- Filters -->
        <div class="filter-header">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label small fw-700 text-secondary">Search Products</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 pe-0" style="border-radius: 12px 0 0 12px; border-color: #e2e8f0;"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control search-input border-start-0" placeholder="Product name or description..." value="<?= htmlspecialchars($search) ?>" style="border-radius: 0 12px 12px 0;">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-700 text-secondary">Category</label>
                    <select name="category" class="form-select category-select">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary-luxury w-100 py-2 rounded-3">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="row g-4">
            <?php if (empty($products)): ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
                    <h5 class="fw-700">No products found</h5>
                    <p class="text-secondary">Try adjusting your filters or search terms.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $p): 
                    $productLink = SITE_URL . "/product.php?slug=" . $p['slug'] . "&ref=" . $affCode;
                    $mainImg = getProductFirstImage($p['images']);
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="product-card-aff">
                        <div class="product-img-wrapper">
                            <img src="<?= SITE_URL ?>/uploads/<?= $mainImg ?>" onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'" alt="<?= htmlspecialchars($p['name']) ?>">
                        </div>
                        <div class="product-info">
                            <span class="category-tag"><?= htmlspecialchars($p['category_name'] ?: 'Uncategorized') ?></span>
                            <h3 class="product-name"><?= htmlspecialchars($p['name']) ?></h3>
                            <div class="product-price"><?= formatPrice($p['price']) ?></div>
                            
                            <div class="mt-auto">
                                <div class="commission-badge">
                                    <?php 
                                        $commRate = ($p['affiliate_commission'] !== null) ? (float)$p['affiliate_commission'] : (float)AFFILIATE_COMMISSION_PERCENT;
                                    ?>
                                    <i class="bi bi-gem me-1"></i> Earn <?= formatPrice($p['price'] * ($commRate / 100)) ?>
                                </div>
                                <button class="share-btn" onclick="copyProductLink('<?= $productLink ?>', '<?= addslashes($p['name']) ?>')">
                                    <i class="bi bi-link-45deg"></i> Get Link
                                </button>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="https://wa.me/?text=Check out this <?= addslashes($p['name']) ?>: <?= $productLink ?>" target="_blank" class="btn btn-outline-success btn-sm flex-grow-1"><i class="bi bi-whatsapp"></i></a>
                                    <a href="https://twitter.com/intent/tweet?text=Check out this <?= addslashes($p['name']) ?>&url=<?= $productLink ?>" target="_blank" class="btn btn-outline-info btn-sm flex-grow-1"><i class="bi bi-twitter-x"></i></a>
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
        function copyProductLink(link, name) {
            navigator.clipboard.writeText(link).then(() => {
                Swal.fire({
                    title: 'Link Copied!',
                    text: 'Affiliate link for "' + name + '" copied to clipboard.',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        }
    </script>
</body>
</html>
