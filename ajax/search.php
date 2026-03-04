<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$q = $conn->real_escape_string($q);
$res = $conn->query("SELECT p.id, p.name, p.slug, p.price, p.discount_percent, p.images FROM products p WHERE p.is_active=1 AND (p.name LIKE '%$q%' OR p.short_description LIKE '%$q%') LIMIT 8");
$results = [];
while ($row = $res->fetch_assoc()) {
    $price = $row['discount_percent'] > 0 ? getDiscountedPrice($row['price'], $row['discount_percent']) : $row['price'];
    $img = getProductFirstImage($row['images']);
    $results[] = ['name'=>$row['name'], 'slug'=>$row['slug'], 'price'=>formatPrice($price), 'img'=>$img];
}
echo json_encode($results);
