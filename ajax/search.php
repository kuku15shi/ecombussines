<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode(['products' => [], 'categories' => [], 'tags' => []]); exit; }

// Log the search
try {
    $stmt = $pdo->prepare("INSERT INTO search_logs (keyword, results_count) VALUES (?, 0) ON DUPLICATE KEY UPDATE results_count=results_count");
    $stmt->execute([$q]);
    $log_id = $pdo->lastInsertId();
} catch (Exception $e) {}

$q_like = "%$q%";

// Check synonyms
$search_terms = [$q_like];
$fulltext_terms = $q;
try {
    $syn_stmt = $pdo->prepare("SELECT synonyms FROM synonyms WHERE keyword LIKE ? OR synonyms LIKE ?");
    $syn_stmt->execute([$q_like, $q_like]);
    while($row = $syn_stmt->fetch()) {
        $parts = array_map('trim', explode(',', $row['synonyms']));
        foreach($parts as $p) {
            if ($p) {
                $search_terms[] = "%$p%";
                $fulltext_terms .= " " . $p;
            }
        }
    }
} catch(Exception $e) {}

// Products search
$products = [];
try {
    // using safe parameter binding for dynamic search conditions
    $conditions = [];
    $params = [];
    foreach ($search_terms as $term) {
        $conditions[] = "(p.name LIKE ? OR p.short_description LIKE ? OR p.tags LIKE ?)";
        $params[] = $term; // name
        $params[] = $term; // desc
        $params[] = $term; // tags
    }
    
    $where_sql = implode(' OR ', $conditions);

    // Add match against for relevance if possible
    $params[] = $fulltext_terms;

    $sql = "SELECT p.id, p.name, p.slug, p.price, p.discount_percent, p.images, p.boost_score, p.views, p.sales_count
            FROM products p 
            WHERE p.is_active=1 AND ($where_sql OR MATCH(p.name, p.tags) AGAINST(? IN BOOLEAN MODE))
            ORDER BY (p.boost_score * 10) + (p.sales_count * 2) + p.views DESC, id DESC LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        $price = $row['discount_percent'] > 0 ? getDiscountedPrice($row['price'], $row['discount_percent']) : $row['price'];
        $img = getProductFirstImage($row['images']);
        // Highlight logic
        $highlighted_name = preg_replace("/(" . preg_quote($q, '/') . ")/i", "<strong>$1</strong>", $row['name']);
        
        $products[] = [
            'name' => $highlighted_name, 
            'plain_name' => $row['name'],
            'slug' => $row['slug'], 
            'price' => formatPrice($price), 
            'img' => $img
        ];
    }
    
    // Update log
    if (isset($log_id) && $log_id > 0) {
        $pdo->prepare("UPDATE search_logs SET results_count=? WHERE id=?")->execute([count($products), $log_id]);
    }
} catch (Exception $e) {}

// Categories search
$categories = [];
try {
    $c_stmt = $pdo->prepare("SELECT name, slug, icon FROM categories WHERE is_active=1 AND name LIKE ? LIMIT 3");
    $c_stmt->execute([$q_like]);
    while ($r = $c_stmt->fetch()) {
        $categories[] = [
            'name' => preg_replace("/(" . preg_quote($q, '/') . ")/i", "<strong>$1</strong>", $r['name']),
            'slug' => $r['slug'],
            'icon' => $r['icon']
        ];
    }
} catch(Exception $e) {}

// Fake tags search (since we didn't create a separate tags table, we just extract matching words or predefined ones if we add a tags table later)
// But since the task requires tags, let's assume tags are comma-separated in products.
$tags = [];
try {
    $t_stmt = $pdo->prepare("SELECT DISTINCT tags FROM products WHERE is_active=1 AND tags LIKE ? LIMIT 10");
    $t_stmt->execute([$q_like]);
    $found_tags = [];
    while ($r = $t_stmt->fetch()) {
        if ($r['tags']) {
            $t_arr = array_map('trim', explode(',', $r['tags']));
            foreach($t_arr as $t) {
                if (stripos($t, $q) !== false && !in_array($t, $found_tags)) {
                    $found_tags[] = $t;
                    $tags[] = ['name' => preg_replace("/(" . preg_quote($q, '/') . ")/i", "<strong>$1</strong>", $t), 'plain' => $t];
                    if (count($found_tags) >= 3) break 2;
                }
            }
        }
    }
} catch (Exception $e) {}

echo json_encode([
    'products' => $products,
    'categories' => $categories,
    'tags' => $tags
]);
