<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

requireAdminLogin();
$pageTitle = 'Manage Products';

$error = '';
$success = '';

// Handle Delete
if (isset($_GET['delete'])) {
  validateCsrf(); // Require CSRF for delete actions if they are via GET? 
  // Usually GET shouldn't modify state, but if it does, we need a token.
  // However, the existing UI might not have tokens in generic links.
  // Better to use POST for deletes, but for now I'll just secure it with PDO.
  $id = (int) $_GET['delete'];
  $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
  if ($stmt->execute([$id])) {
    $success = 'Product deleted successfully.';
  }
}

// Handle Toggle Active
if (isset($_GET['toggle'])) {
  $id = (int) $_GET['toggle'];
  $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id=?")->execute([$id]);
  header('Location: products.php');
  exit;
}

// Handle Add/Edit
$editProduct = null;
$editVariants = [];
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
  $id = (int) $_GET['id'];
  $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
  $stmt->execute([$id]);
  $editProduct = $stmt->fetch();
  if ($editProduct) {
    $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id=?");
    $vStmt->execute([$id]);
    $editVariants = $vStmt->fetchAll();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
  validateCsrf();

  $name = $_POST['name'] ?? '';
  $catId = (int) $_POST['category_id'];
  $price = (float) $_POST['price'];
  $purchasePrice = (float) ($_POST['purchase_price'] ?? 0);
  $affCommission = $_POST['affiliate_commission'] === '' ? null : (float) $_POST['affiliate_commission'];
  $stock = (int) $_POST['stock'];
  $discount = (int) $_POST['discount_percent'];
  $shortDesc = $_POST['short_description'] ?? '';
  $desc = $_POST['description'] ?? '';
  $specs = $_POST['specifications'] ?? '';
  $videoUrl = $_POST['video_url'] ?? '';

  // Handle Video Upload
  if (!empty($_FILES['video_file']['name'])) {
    $videoRes = uploadVideo($_FILES['video_file']);
    if (isset($videoRes['filename'])) {
      $videoUrl = $videoRes['filename'];
    } else {
      $error = $videoRes['error'];
    }
  }

  $slug = generateSlug($name);
  $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
  $isTop = isset($_POST['is_top']) ? 1 : 0;
  $isTrending = isset($_POST['is_trending']) ? 1 : 0;
  $isActive = isset($_POST['is_active']) ? 1 : 0;
  $sizes = $_POST['sizes'] ?? '';
  $colors = $_POST['colors'] ?? '';

  // New fields
  $brandId = !empty($_POST['brand_id']) ? (int) $_POST['brand_id'] : null;
  $tags = $_POST['tags'] ?? '';
  $boostScore = (int) ($_POST['boost_score'] ?? 0);

  $editId = (int) ($_POST['edit_id'] ?? 0);

  // Handle images
  $imagesJson = '';
  if (!empty($_FILES['images']['name'][0])) {
    $existingImages = [];
    if ($editId) {
      $curStmt = $pdo->prepare("SELECT images FROM products WHERE id=?");
      $curStmt->execute([$editId]);
      $cur = $curStmt->fetch();
      $existingImages = json_decode($cur['images'] ?? '[]', true) ?: [];
    }
    $newImages = uploadProductImages($_FILES['images']);
    $allImages = array_merge($existingImages, $newImages);
    $imagesJson = json_encode($allImages);
  } elseif ($editId) {
    $curStmt = $pdo->prepare("SELECT images FROM products WHERE id=?");
    $curStmt->execute([$editId]);
    $cur = $curStmt->fetch();
    $imagesJson = $cur['images'] ?? '[]';
  } else {
    $imagesJson = '[]';
  }

  // Handle Size Chart
  $sizeChartPath = '';
  if (!empty($_FILES['size_chart']['name'])) {
    $chartRes = uploadImage($_FILES['size_chart'], 'size_charts');
    if (isset($chartRes['filename'])) {
      $sizeChartPath = $chartRes['filename'];
    }
  } elseif ($editId) {
    $curStat = $pdo->prepare("SELECT size_chart FROM products WHERE id=?");
    $curStat->execute([$editId]);
    $sizeChartPath = $curStat->fetch()['size_chart'] ?? '';
  }

  // Handle 3D Model Upload
  $model3d = '';
  if (!empty($_FILES['model_3d']['name'])) {
    $modelRes = upload3DModel($_FILES['model_3d']);
    if (isset($modelRes['filename'])) {
      $model3d = $modelRes['filename'];
    } else {
      $error = $modelRes['error'];
    }
  } elseif ($editId) {
    $curModelStmt = $pdo->prepare("SELECT model_3d FROM products WHERE id=?");
    $curModelStmt->execute([$editId]);
    $model3d = $curModelStmt->fetch()['model_3d'] ?? '';
  }

  if (!$error) {
    if ($editId) {
      $stmt = $pdo->prepare("UPDATE products SET name=?, slug=?, category_id=?, brand_id=?, price=?, purchase_price=?, affiliate_commission=?, stock=?, discount_percent=?, short_description=?, description=?, specifications=?, images=?, sizes=?, colors=?, size_chart=?, video_url=?, model_3d=?, tags=?, boost_score=?, is_featured=?, is_top=?, is_trending=?, is_active=?, updated_at=NOW() WHERE id=?");
      $stmt->execute([$name, $slug, $catId, $brandId, $price, $purchasePrice, $affCommission, $stock, $discount, $shortDesc, $desc, $specs, $imagesJson, $sizes, $colors, $sizeChartPath, $videoUrl, $model3d, $tags, $boostScore, $isFeatured, $isTop, $isTrending, $isActive, $editId]);
      $productId = $editId;
      $success = 'Product updated successfully!';
    } else {
      $stmt = $pdo->prepare("INSERT INTO products (name,slug,category_id,brand_id,price,purchase_price,affiliate_commission,stock,discount_percent,short_description,description,specifications,images,sizes,colors,size_chart,video_url,model_3d,tags,boost_score,is_featured,is_top,is_trending,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$name, $slug, $catId, $brandId, $price, $purchasePrice, $affCommission, $stock, $discount, $shortDesc, $desc, $specs, $imagesJson, $sizes, $colors, $sizeChartPath, $videoUrl, $model3d, $tags, $boostScore, $isFeatured, $isTop, $isTrending, $isActive]);
      $productId = $pdo->lastInsertId();
      $success = 'Product added successfully!';
    }
  }

  // Handle Color Variants (Color + Image)
  if (isset($_POST['variant_colors'])) {
    if ($editId) {
      $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$editId]);
    }

    $vStmt = $pdo->prepare("INSERT INTO product_variants (product_id, color, image) VALUES (?, ?, ?)");
    foreach ($_POST['variant_colors'] as $i => $vColor) {
      $vColor = trim($vColor);
      if (!$vColor)
        continue;

      $vImg = '';
      if (!empty($_FILES['variant_images']['name'][$i])) {
        $file = [
          'name' => $_FILES['variant_images']['name'][$i],
          'type' => $_FILES['variant_images']['type'][$i],
          'tmp_name' => $_FILES['variant_images']['tmp_name'][$i],
          'error' => $_FILES['variant_images']['error'][$i],
          'size' => $_FILES['variant_images']['size'][$i]
        ];
        $res = uploadImage($file, 'variants');
        if (isset($res['filename']))
          $vImg = $res['filename'];
      }

      if (!$vImg && !empty($_POST['existing_variant_images'][$i])) {
        $vImg = $_POST['existing_variant_images'][$i];
      }

      if ($vImg) {
        $vStmt->execute([$productId, $vColor, $vImg]);
      }
    }
  }

  header('Location: products.php?success=' . urlencode($success));
  exit;
}

// Fetch
$search = $_GET['search'] ?? '';
$catFilter = (int) ($_GET['cat'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$where = "WHERE 1=1";
$params = [];

if ($search) {
  $where .= " AND (p.name LIKE ? OR p.slug LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

if ($catFilter) {
  $where .= " AND p.category_id=?";
  $params[] = $catFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) as c FROM products p $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['c'];
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$sql = "SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = getCategories($pdo);
$brands = [];
try {
  $brands = $pdo->query("SELECT * FROM brands WHERE is_active=1 ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
}
$action = $_GET['action'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products – MIZ MAX Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
  <script>
    const savedTheme = localStorage.getItem('luxeTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
</head>

<body>
  <div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
      <?php include 'includes/topbar.php'; ?>
      <div class="content-area">

        <?php if (isset($_GET['success'])): ?>
          <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
          </div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><i class="bi bi-x-circle"></i> <?= $error ?></div><?php endif; ?>

        <?php if ($action === 'add' || $action === 'edit'): ?>
          <!-- ADD / EDIT FORM -->
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h2 style="font-weight:800; font-size:1.3rem;"><?= $action === 'edit' ? '✏️ Edit Product' : '➕ Add New Product' ?>
            </h2>
            <a href="products.php" class="btn-primary"
              style="background:var(--glass); color:var(--text-primary); border:1px solid var(--glass-border);"><i
                class="bi bi-arrow-left"></i> Back</a>
          </div>
          <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="save_product" value="1">
            <input type="hidden" name="edit_id" value="<?= $editProduct['id'] ?? 0 ?>">
            <div class="admin-grid-2">
              <div class="admin-stack">
                <div class="form-card">
                  <div style="font-weight:700; margin-bottom:1.25rem; font-size:0.95rem;">Basic Information</div>
                  <div class="form-group"><label class="form-label">Product Name *</label><input type="text" name="name"
                      class="form-control" value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>" required></div>
                  <div class="admin-grid-half" style="gap:1rem;">
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Category *</label><select
                        name="category_id" class="form-control" required>
                        <option value="">Select Category</option><?php foreach ($categories as $c): ?>
                          <option value="<?= $c['id'] ?>" <?= ($editProduct['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                      </select></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Brand</label><select
                        name="brand_id" class="form-control">
                        <option value="">Select Brand</option><?php foreach ($brands as $b): ?>
                          <option value="<?= $b['id'] ?>" <?= ($editProduct['brand_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
                      </select></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Selling Price (₹)
                        *</label><input type="number" name="price" class="form-control" step="0.01" min="0"
                        value="<?= $editProduct['price'] ?? '' ?>" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Purchase Price (₹)
                        *</label><input type="number" name="purchase_price" class="form-control" step="0.01" min="0"
                        value="<?= $editProduct['purchase_price'] ?? '' ?>" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Affiliate Commission
                        (%)</label><input type="number" name="affiliate_commission" class="form-control" step="0.01"
                        min="0" max="100" value="<?= $editProduct['affiliate_commission'] ?? '' ?>"
                        placeholder="Default: <?= AFFILIATE_COMMISSION_PERCENT ?>%"></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Stock Quantity
                        *</label><input type="number" name="stock" class="form-control" min="0"
                        value="<?= $editProduct['stock'] ?? '0' ?>" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Discount (%)</label><input
                        type="number" name="discount_percent" class="form-control"
                        value="<?= $editProduct['discount_percent'] ?? '0' ?>" min="0" max="90"></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Search Boost
                        Score</label><input type="number" name="boost_score" class="form-control"
                        value="<?= $editProduct['boost_score'] ?? '0' ?>" min="-10" max="100"
                        title="Higher score ranks product higher in search"></div>
                  </div>
                </div>
                <div class="form-card">
                  <div style="font-weight:700; margin-bottom:1.25rem; font-size:0.95rem;">Description & Search</div>
                  <div class="form-group"><label class="form-label">Tags / Keywords</label><input type="text" name="tags"
                      class="form-control" placeholder="e.g. casual, summer, cotton (comma separated)"
                      value="<?= htmlspecialchars($editProduct['tags'] ?? '') ?>"></div>
                  <div class="form-group"><label class="form-label">Short Description</label><textarea
                      name="short_description" class="form-control" rows="2"
                      placeholder="Brief product summary..."><?= htmlspecialchars($editProduct['short_description'] ?? '') ?></textarea>
                  </div>
                  <div class="form-group"><label class="form-label">Full Description</label><textarea name="description"
                      class="form-control" rows="4"
                      placeholder="Detailed product description..."><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                  </div>
                  <div class="form-group" style="margin-bottom:0;"><label
                      class="form-label">Specifications</label><textarea name="specifications" class="form-control"
                      rows="3"
                      placeholder="Technical specs, dimensions, materials..."><?= htmlspecialchars($editProduct['specifications'] ?? '') ?></textarea>
                  </div>
                </div>

                <!-- Fashion Options -->
                <div class="form-card">
                  <div style="font-weight:700; margin-bottom:1.25rem; font-size:0.95rem;">👗 Fashion Product Settings
                    (Optional)</div>
                  <div class="admin-grid-half" style="gap:1.2rem;">
                    <div class="form-group">
                      <label class="form-label">Available Sizes</label>
                      <input type="text" name="sizes" class="form-control" placeholder="e.g. S, M, L, XL"
                        value="<?= htmlspecialchars($editProduct['sizes'] ?? '') ?>">
                      <small style="color:var(--text-muted); font-size:0.7rem;">Separate with commas</small>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Available Colors</label>
                      <div style="display:flex; gap:0.5rem; align-items:center;">
                        <input type="text" name="colors" id="colorsInput" class="form-control"
                          placeholder="e.g. Red, Blue, #000"
                          value="<?= htmlspecialchars($editProduct['colors'] ?? '') ?>">
                        <div
                          style="position:relative; width:42px; height:42px; border-radius:8px; overflow:hidden; border:1px solid var(--border); flex-shrink:0;">
                          <input type="color" id="quickPicker" style="position:absolute; inset:-10px; cursor:pointer;"
                            onchange="addColorFromPicker(this.value)">
                        </div>
                      </div>
                      <small style="color:var(--text-muted); font-size:0.7rem;">Separate with commas. Use picker to add
                        hex colors.</small>
                    </div>
                  </div>
                  <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Dress Size Chart</label>
                    <input type="file" name="size_chart" class="form-control" accept="image/*">
                    <?php if (!empty($editProduct['size_chart'])): ?>
                      <div style="margin-top:0.75rem; display:flex; align-items:center; gap:0.75rem;">
                        <img src="<?= UPLOAD_URL . $editProduct['size_chart'] ?>"
                          style="width:60px; height:60px; border-radius:4px; object-fit:cover; border:1px solid var(--border);">
                        <span style="font-size:0.75rem; color:var(--text-muted);">Current size chart</span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Color Variant Images -->
                <div class="form-card">
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                    <div style="font-weight:700; font-size:0.95rem;">📸 Color Variant Gallery (Change Image with Color)
                    </div>
                    <button type="button" class="btn-primary btn-sm" onclick="addVariantRow()"><i
                        class="bi bi-plus-circle"></i> Add Variant</button>
                  </div>
                  <div id="variantsList" style="display:flex; flex-direction:column; gap:1rem;">
                    <?php if (!empty($editVariants)):
                      foreach ($editVariants as $v): ?>
                        <div class="variant-row"
                          style="display:grid; grid-template-columns:120px 1fr 40px; gap:1rem; align-items:center; padding:1rem; background:var(--bg-lighter); border-radius:8px; border:1px solid var(--border);">
                          <div
                            style="position:relative; width:100%; aspect-ratio:1; background:var(--border); border-radius:4px; overflow:hidden;">
                            <img src="<?= UPLOAD_URL . $v['image'] ?>" style="width:100%; height:100%; object-fit:cover;">
                            <input type="hidden" name="existing_variant_images[]" value="<?= $v['image'] ?>">
                          </div>
                          <div>
                            <label class="form-label" style="font-size:0.75rem;">Color Name/Hex</label>
                            <input type="text" name="variant_colors[]" class="form-control"
                              value="<?= htmlspecialchars($v['color']) ?>" placeholder="e.g. Red">
                            <label class="form-label" style="font-size:0.75rem; margin-top:0.5rem;">Change Image</label>
                            <input type="file" name="variant_images[]" class="form-control" accept="image/*">
                          </div>
                          <button type="button" class="btn-icon" style="color:var(--danger);"
                            onclick="this.closest('.variant-row').remove()"><i class="bi bi-trash"></i></button>
                        </div>
                      <?php endforeach; else: ?>
                      <div
                        style="text-align:center; padding:1.5rem; color:var(--text-muted); font-size:0.85rem; border:2px dashed var(--border); border-radius:8px;">
                        No color variants added yet. Click "Add Variant" to link images to colors.
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="admin-stack">
              <div class="form-card">
                <div style="font-weight:700; margin-bottom:1.25rem; font-size:0.95rem;">Product Images</div>
                <div class="img-uploader" id="imgUploader">
                  <input type="file" name="images[]" id="imgInput" multiple accept="image/*"
                    onchange="previewImages(this)">
                  <div class="img-uploader-icon"><i class="bi bi-cloud-upload"></i></div>
                  <div class="img-uploader-text">Click or drag images here<br><span style="font-size:0.75rem;">Max 5
                      images, JPG/PNG/WebP</span></div>
                </div>
                <div id="imgPreview"
                  style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.5rem; margin-top:0.75rem;">
                  <?php if (!empty($editProduct['images'])):
                    $imgs = json_decode($editProduct['images'], true) ?: [];
                    foreach ($imgs as $img): ?>
                      <div style="position:relative;">
                        <img src="<?= UPLOAD_URL . $img ?>"
                          style="width:100%; aspect-ratio:1; object-fit:cover; border-radius:var(--radius-sm);"
                          onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'">
                      </div>
                    <?php endforeach; endif; ?>
                </div>
              </div>
              <!-- Product Video & 3D Model -->
              <div class="form-card">
                <div style="font-weight:700; margin-bottom:1.25rem; font-size:0.95rem;">🎬 Media & 3D Assets</div>

                <!-- Video Section -->
                <div style="margin-bottom:1.5rem; padding-bottom:1.5rem; border-bottom:1px solid var(--border);">
                  <div style="font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Product Video <span
                      style="font-size:0.75rem; font-weight:400; color:var(--text-muted);">(Optional)</span></div>
                  <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:1rem;">Upload a video file OR
                    paste a YouTube link.</div>

                  <div class="form-group">
                    <label class="form-label">Upload Video File</label>
                    <input type="file" name="video_file" class="form-control" accept="video/mp4,video/webm,video/ogg">
                  </div>

                  <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">OR Video URL (YouTube/Direct)</label>
                    <input type="text" name="video_url" class="form-control" placeholder="https://youtube.com/watch?v=..."
                      value="<?= htmlspecialchars($editProduct['video_url'] ?? '') ?>">
                    <?php if (!empty($editProduct['video_url'])): ?>
                      <div style="margin-top:0.5rem; font-size:0.7rem; color:var(--primary);"><i
                          class="bi bi-check-circle"></i> Video is set</div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- 3D Model Section (Three.js) -->
                <div>
                  <div style="font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">3D Product Model <span
                      style="font-size:0.75rem; font-weight:400; color:var(--text-muted);">(Optional)</span></div>
                  <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:1rem;">Upload a .glb or .gltf file
                    for Three.js 3D visualization.</div>

                  <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Upload 3D Model (.GLB/.GLTF)</label>
                    <input type="file" name="model_3d" class="form-control" accept=".glb,.gltf">
                    <?php if (!empty($editProduct['model_3d'])): ?>
                      <div
                        style="margin-top:0.75rem; padding:0.6rem 0.9rem; background:rgba(108,99,255,0.08); border-radius:8px; border:1px solid rgba(108,99,255,0.2); font-size:0.78rem; color:var(--primary); display:flex; align-items:center; gap:0.5rem;">
                        <i class="bi bi-box"></i> Current: <?= basename($editProduct['model_3d']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="form-card">
                <div style="font-weight:700; margin-bottom:1.25rem; font-size:0.95rem;">Product Labels</div>
                <?php $toggles = [['is_active', 'Active / Visible'], ['is_featured', 'Featured Product'], ['is_top', 'Top Product'], ['is_trending', 'Trending']];
                foreach ($toggles as $t): ?>
                  <div
                    style="display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0; border-bottom:1px solid var(--border);">
                    <label style="font-size:0.875rem; cursor:pointer;"><?= $t[1] ?></label>
                    <label class="toggle-switch">
                      <input type="checkbox" name="<?= $t[0] ?>" <?= (!isset($editProduct) || ($editProduct[$t[0]] ?? 0)) ? 'checked' : '' ?>>
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                <?php endforeach; ?>
                <div style="margin-top:1.25rem;">
                  <button type="submit" name="save_product" class="btn-primary"
                    style="width:100%; justify-content:center; padding:0.875rem;">
                    <i class="bi bi-save"></i> <?= $action === 'edit' ? 'Update Product' : 'Save Product' ?>
                  </button>
                </div>
              </div>
            </div>
        </div>
        </form>

      <?php else: ?>
        <!-- PRODUCTS LIST -->
        <div
          style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:0.75rem;">
          <h2 style="font-weight:800; font-size:1.3rem;">Products <span
              style="font-size:1rem; color:var(--text-muted); font-weight:500;">(<?= $total ?>)</span></h2>
          <a href="products.php?action=add" class="btn-primary"><i class="bi bi-plus-lg"></i> Add Product</a>
        </div>

        <!-- Filters -->
        <div class="data-table-card" style="margin-bottom:1.25rem;">
          <div style="padding:1rem 1.25rem;">
            <form method="GET" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
              <input type="text" name="search" class="filter-input" placeholder="🔍 Search products..."
                value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:200px;">
              <select name="cat" class="filter-input">
                <option value="">All Categories</option><?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
              </select>
              <button type="submit" class="btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
              <?php if ($search || $catFilter): ?><a href="products.php" class="btn-primary btn-sm"
                  style="background:var(--glass); color:var(--text-primary); border:1px solid var(--glass-border);">✕
                  Clear</a><?php endif; ?>
            </form>
          </div>
        </div>

        <!-- Table -->
        <div class="data-table-card">
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Image</th>
                  <th>Product</th>
                  <th>Category</th>
                  <th>Price / Cost</th>
                  <th>Profit</th>
                  <th>Stock</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($products as $p):
                  $imgs = json_decode($p['images'], true) ?: [];
                  $firstImg = $imgs[0] ?? 'default_product.jpg';
                  $price = $p['discount_percent'] > 0 ? getDiscountedPrice($p['price'], $p['discount_percent']) : $p['price'];
                  ?>
                  <tr>
                    <td><img src="<?= UPLOAD_URL . $firstImg ?>"
                        onerror="this.src='<?= SITE_URL ?>/assets/img/default_product.jpg'"
                        style="width:52px; height:52px; border-radius:var(--radius-sm); object-fit:cover;" alt=""></td>
                    <td>
                      <div style="font-weight:700; margin-bottom:0.2rem;"><?= htmlspecialchars($p['name']) ?></div>
                      <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                        <?php if ($p['is_featured']): ?><span class="badge badge-processing"
                            style="font-size:0.6rem;">Featured</span><?php endif; ?>
                        <?php if ($p['is_top']): ?><span class="badge badge-delivered"
                            style="font-size:0.6rem;">Top</span><?php endif; ?>
                        <?php if ($p['is_trending']): ?><span class="badge badge-pending"
                            style="font-size:0.6rem;">Trending</span><?php endif; ?>
                        <?php if ($p['discount_percent'] > 0): ?><span class="badge badge-cancelled"
                            style="font-size:0.6rem;"><?= $p['discount_percent'] ?>% OFF</span><?php endif; ?>
                      </div>
                    </td>
                    <td><?= htmlspecialchars($p['cat_name'] ?? '–') ?></td>
                    <td>
                      <?php if ($p['discount_percent'] > 0): ?>
                        <div style="font-weight:800; color:var(--success);"><?= formatPrice($price) ?></div>
                        <div style="font-size:0.75rem; text-decoration:line-through; color:var(--text-muted);">
                          <?= formatPrice($p['price']) ?></div>
                      <?php else: ?>
                        <div style="font-weight:700;"><?= formatPrice($p['price']) ?></div>
                      <?php endif; ?>
                      <div style="font-size:0.72rem; color:var(--text-muted); margin-top:0.25rem;">Cost:
                        <?= formatPrice($p['purchase_price']) ?></div>
                    </td>
                    <?php
                    $profit = $price - $p['purchase_price'];
                    $profitPct = $p['purchase_price'] > 0 ? ($profit / $p['purchase_price']) * 100 : 0;
                    ?>
                    <td>
                      <div style="font-weight:800; color:<?= $profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= ($profit >= 0 ? '+' : '') . formatPrice($profit) ?>
                      </div>
                      <div style="font-size:0.75rem; color:var(--text-muted);"><?= round($profitPct, 1) ?>% margin</div>
                    </td>
                    <td><span
                        class="badge <?= $p['stock'] <= 0 ? 'badge-cancelled' : ($p['stock'] <= 5 ? 'badge-pending' : 'badge-delivered') ?>"><?= $p['stock'] <= 0 ? 'Out of stock' : $p['stock'] . ' in stock' ?></span>
                    </td>
                    <td>
                      <a href="products.php?toggle=<?= $p['id'] ?>">
                        <span
                          class="badge <?= $p['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span>
                      </a>
                    </td>
                    <td>
                      <div style="display:flex; gap:0.4rem;">
                        <a href="products.php?action=edit&id=<?= $p['id'] ?>" class="btn-icon btn-edit" title="Edit"><i
                            class="bi bi-pencil"></i></a>
                        <a href="../product.php?slug=<?= $p['slug'] ?>" target="_blank" class="btn-icon" title="View"><i
                            class="bi bi-eye"></i></a>
                        <a href="products.php?delete=<?= $p['id'] ?>" class="btn-icon btn-delete" title="Delete"
                          onclick="return confirm('Delete this product?')"><i class="bi bi-trash"></i></a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                  <tr>
                    <td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No products found</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($pages > 1): ?>
            <div class="pagination">
              <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&cat=<?= $catFilter ?>"
                  class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
              <?php endfor; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  </div>

  <script>
    function addVariantRow() {
      const list = document.getElementById('variantsList');
      if (list.innerHTML.includes('No color variants')) list.innerHTML = '';

      const div = document.createElement('div');
      div.className = 'variant-row';
      div.style = 'display:grid; grid-template-columns:120px 1fr 40px; gap:1rem; align-items:center; padding:1rem; background:var(--bg-lighter); border-radius:8px; border:1px solid var(--border);';
      div.innerHTML = `
    <div style="position:relative; width:100%; aspect-ratio:1; background:var(--border); border-radius:4px; overflow:hidden; display:flex; align-items:center; justify-content:center;">
       <i class="bi bi-image text-muted" style="font-size:1.5rem;"></i>
    </div>
    <div>
      <label class="form-label" style="font-size:0.75rem;">Color Name/Hex</label>
      <input type="text" name="variant_colors[]" class="form-control" placeholder="e.g. Red">
      <label class="form-label" style="font-size:0.75rem; margin-top:0.5rem;">Variant Image</label>
      <input type="file" name="variant_images[]" class="form-control" accept="image/*" required>
    </div>
    <button type="button" class="btn-icon" style="color:var(--danger);" onclick="this.closest('.variant-row').remove()"><i class="bi bi-trash"></i></button>
  `;
      list.appendChild(div);
    }

    function addColorFromPicker(color) {
      const input = document.getElementById('colorsInput');
      const current = input.value.trim();
      if (current) {
        input.value = current + ', ' + color;
      } else {
        input.value = color;
      }
    }

    function previewImages(input) {
      const preview = document.getElementById('imgPreview');
      preview.innerHTML = '';
      Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
          const div = document.createElement('div');
          div.innerHTML = `<img src="${e.target.result}" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;">`;
          preview.appendChild(div);
        };
        reader.readAsDataURL(file);
      });
    }
  </script>
</body>

</html>