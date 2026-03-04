<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success'=>false,'message'=>'Please login to manage your wishlist']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$uid = (int)($_SESSION['user_id'] ?? 0);

if (!$productId) { echo json_encode(['success'=>false,'message'=>'Invalid product']); exit; }

$p = getProductById($pdo, $productId);
if (!$p) { echo json_encode(['success'=>false,'message'=>'Product not found']); exit; }

$stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=?");
$stmt->execute([$uid, $productId]);
$exists = $stmt->fetch();

if ($exists) {
    $pdo->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")->execute([$uid, $productId]);
    echo json_encode(['success'=>true,'in_wishlist'=>false,'message'=>'Removed from wishlist']);
} else {
    $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)")->execute([$uid, $productId]);
    echo json_encode(['success'=>true,'in_wishlist'=>true,'message'=>'Added to wishlist!']);
}
