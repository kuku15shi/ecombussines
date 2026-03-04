<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

$code = trim($_POST['code'] ?? '');
if (!$code) { echo json_encode(['success'=>false,'message'=>'Enter a coupon code']); exit; }

$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;
foreach ($cart as $key => $item) {
    if (!isset($item['id'])) continue;
    $p = getProductById($pdo, $item['id']);
    if (!$p) continue;
    $price = $p['discount_percent'] > 0 ? ($p['price'] * (1 - $p['discount_percent'] / 100)) : $p['price'];
    $subtotal += $price * $item['qty'];
}

$result = applyCoupon($pdo, $code, $subtotal);
if (isset($result['error'])) {
    echo json_encode(['success'=>false,'message'=>$result['error']]);
} else {
    $_SESSION['coupon_discount'] = $result['discount'];
    $_SESSION['coupon_code'] = strtoupper($code);
    echo json_encode(['success'=>true,'message'=>'Coupon applied! You saved '.formatPrice($result['discount']),'discount'=>$result['discount']]);
}
