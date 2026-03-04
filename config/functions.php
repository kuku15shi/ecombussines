<?php
function getCategories($db = null) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    $stmt = $db->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order ASC");
    return $stmt->fetchAll();
}

function getFeaturedProducts($db = null, $limit = 8) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    $stmt = $db->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_featured=1 AND p.is_active=1 ORDER BY p.created_at DESC LIMIT ?");
    $stmt->execute([(int)$limit]);
    return $stmt->fetchAll();
}

function getTopProducts($db = null, $limit = 8) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    $stmt = $db->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_top=1 AND p.is_active=1 ORDER BY p.created_at DESC LIMIT ?");
    $stmt->execute([(int)$limit]);
    return $stmt->fetchAll();
}

function getTrendingProducts($db = null, $limit = 8) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    $stmt = $db->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_trending=1 AND p.is_active=1 ORDER BY p.created_at DESC LIMIT ?");
    $stmt->execute([(int)$limit]);
    return $stmt->fetchAll();
}

function getActiveBanners($db = null) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    $stmt = $db->query("SELECT * FROM banners WHERE is_active=1 ORDER BY position ASC");
    return $stmt->fetchAll();
}

function getProductById($db = null, $id = null) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    $stmt = $db->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=? AND p.is_active=1");
    $stmt->execute([(int)$id]);
    return $stmt->fetch();
}

function getProductBySlug($db = null, $slug = null) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    $stmt = $db->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.slug=? AND p.is_active=1");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

function getReviews($db = null, $product_id = null) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    $stmt = $db->prepare("SELECT r.*, u.name as user_name, u.avatar FROM reviews r LEFT JOIN users u ON r.user_id=u.id WHERE r.product_id=? ORDER BY r.created_at DESC");
    $stmt->execute([(int)$product_id]);
    return $stmt->fetchAll();
}

function isInWishlist($db = null, $user_id = null, $product_id = null) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    if (!$user_id) return false;
    $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=?");
    $stmt->execute([(int)$user_id, (int)$product_id]);
    return $stmt->rowCount() > 0;
}

function getProductImages($images_json) {
    if (empty($images_json)) return ['default_product.jpg'];
    $imgs = json_decode($images_json, true);
    return is_array($imgs) && count($imgs) > 0 ? $imgs : ['default_product.jpg'];
}

function getProductFirstImage($images_json) {
    $imgs = getProductImages($images_json);
    return $imgs[0];
}

function applyCoupon($pdo, $code, $subtotal) {
    if (!$code) return ['error' => 'Coupon code is required'];
    $code = strtoupper(trim($code));
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND is_active=1 AND (expires_at IS NULL OR expires_at >= ?) AND (max_uses=0 OR used_count < max_uses) LIMIT 1");
    $stmt->execute([$code, $today]);
    $coupon = $stmt->fetch();

    if (!$coupon) return ['error' => 'Invalid or expired coupon code'];
    if ($subtotal < $coupon['min_order']) return ['error' => 'Minimum order amount for this coupon is ' . formatPrice($coupon['min_order'])];
    
    $discount = ($coupon['type'] === 'percent') ? ($subtotal * $coupon['value'] / 100) : $coupon['value'];
    return ['success' => true, 'discount' => $discount, 'coupon' => $coupon];
}

function uploadImage($file, $folder = 'products') {
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!$ext) return ['error' => 'Invalid file.'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
        return ['error' => 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF.'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) return ['error' => 'File too large (max 5MB)'];
    
    $uploadDir = UPLOAD_PATH . $folder . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['success' => true, 'filename' => $folder . '/' . $filename];
    }
    return ['error' => 'Upload failed'];
}

function uploadVideo($file, $folder = 'videos') {
    $allowed_ext = ['mp4', 'webm', 'ogg'];
    $allowed_mime = ['video/mp4', 'video/webm', 'video/ogg'];
    
    $file_ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($file_ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
        return ['error' => 'Invalid video format. Only MP4, WebM and OGG are allowed.'];
    }
    
    if ($file['size'] > 100 * 1024 * 1024) {
        return ['error' => 'Video file is too large. Max size is 100MB.'];
    }

    $uploadDir = UPLOAD_PATH . $folder . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = 'v_' . bin2hex(random_bytes(16)) . '.' . $file_ext;
    
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['success' => true, 'filename' => $folder . '/' . $filename];
    }
    return ['error' => 'Failed to upload video.'];
}

function upload3DModel($file, $folder = 'models') {
    $allowed_ext = ['glb', 'gltf'];
    $file_ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_ext)) {
        return ['error' => 'Invalid 3D model format. Only GLB and GLTF are allowed.'];
    }
    
    if ($file['size'] > 50 * 1024 * 1024) {
        return ['error' => 'Model file is too large. Max size is 50MB.'];
    }

    $uploadDir = UPLOAD_PATH . $folder . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = 'm_' . bin2hex(random_bytes(16)) . '.' . $file_ext;

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['success' => true, 'filename' => $folder . '/' . $filename];
    }
    return ['error' => 'Failed to upload 3D model.'];
}

function uploadProductImages($files, $folder = 'products') {
    $uploaded = [];
    $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;
    for ($i = 0; $i < min($count, 5); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $file = ['name'=>$files['name'][$i],'type'=>$files['type'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]];
        $result = uploadImage($file, $folder);
        if (isset($result['filename'])) $uploaded[] = $result['filename'];
    }
    return $uploaded;
}

function getRelatedProducts($db = null, $categoryId = null, $excludeId = null, $limit = 4) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    $stmt = $db->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.category_id=? AND p.id!=? AND p.is_active=1 ORDER BY RAND() LIMIT ?");
    $stmt->execute([(int)$categoryId, (int)$excludeId, (int)$limit]);
    return $stmt->fetchAll() ?: [];
}

function getOrderStatusClass($status) {
    return match($status) {
        'pending' => 'status-pending',
        'processing' => 'status-processing',
        'shipped' => 'status-shipped',
        'delivered' => 'status-delivered',
        'cancelled' => 'status-cancelled',
        default => 'status-pending'
    };
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hrs ago';
    return floor($time/86400) . ' days ago';
}

function recordAffiliateCommission($orderId, $total) {
    global $pdo;
    require_once __DIR__ . '/AffiliateClass.php';
    $affSystem = new AffiliateSystem($pdo);
    return $affSystem->recordCommission($orderId, $total);
}
