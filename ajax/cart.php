<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? 'add';
$productId = (int)($_POST['product_id'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));
$delta = (int)($_POST['delta'] ?? 1);
$size = $_POST['size'] ?? '';
$color = $_POST['color'] ?? '';

// Generate a unique key for the cart item
$cartKey = $productId;
if ($size) $cartKey .= '-' . strtolower(trim($size));
if ($color) $cartKey .= '-' . strtolower(trim($color));

if (!$productId && !isset($_POST['cart_key']) && $action !== 'get') {
    echo json_encode(['success'=>false,'message'=>'Invalid product or cart key']);
    exit;
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

function cartSummary($pdo) {
    $cart = $_SESSION['cart'] ?? [];
    $sub = 0; $count = 0;
    foreach ($cart as $key => $item) {
        $p = getProductById($pdo, $item['id']);
        if (!$p) continue;
        $price = $p['discount_percent'] > 0 ? ($p['price'] * (1 - $p['discount_percent'] / 100)) : $p['price'];
        $sub += $price * $item['qty'];
        $count += $item['qty'];
    }
    $shipping = $sub >= (defined('FREE_SHIPPING_ABOVE') ? FREE_SHIPPING_ABOVE : 999) ? 0 : ($sub > 0 ? (defined('SHIPPING_CHARGE') ? SHIPPING_CHARGE : 50) : 0);
    $gst = $sub * (defined('GST_PERCENT') ? GST_PERCENT : 18) / 100;
    return [
        'cartCount' => $count,
        'subtotal' => number_format($sub, 2),
        'shipping' => number_format($shipping, 2),
        'gst' => number_format($gst, 2),
        'total' => number_format($sub + $shipping + $gst, 2),
    ];
}

switch ($action) {
    case 'add':
        $p = getProductById($pdo, $productId);
        if (!$p) { echo json_encode(['success'=>false,'message'=>'Product not found']); exit; }
        if ($p['stock'] <= 0) { echo json_encode(['success'=>false,'message'=>'Product is out of stock']); exit; }
        
        $totalInCart = 0;
        foreach($_SESSION['cart'] as $item) {
            if($item['id'] === $productId) $totalInCart += $item['qty'];
        }

        if ($totalInCart + $qty > $p['stock']) { echo json_encode(['success'=>false,'message'=>'Only '.$p['stock'].' in stock total']); exit; }
        
        if (!isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey] = [
                'id' => $productId,
                'qty' => $qty,
                'size' => $size,
                'color' => $color
            ];
        } else {
            $_SESSION['cart'][$cartKey]['qty'] += $qty;
        }
        $summary = cartSummary($pdo);
        echo json_encode(['success'=>true,'message'=>'"'.htmlspecialchars($p['name']).'" added to cart!','cartCount'=>$summary['cartCount']]);
        break;

    case 'update':
        $updateKey = $_POST['cart_key'] ?? $cartKey; // For update we might have cartKey or productId-size-color
        if (!isset($_SESSION['cart'][$updateKey])) { echo json_encode(['success'=>false,'message'=>'Not in cart']); exit; }
        
        $newQty = $_SESSION['cart'][$updateKey]['qty'] + $delta;
        if ($newQty <= 0) { unset($_SESSION['cart'][$updateKey]); $qtyCount = 0; }
        else {
            $p = getProductById($pdo, $_SESSION['cart'][$updateKey]['id']);
            
            $totalInCart = 0;
            foreach($_SESSION['cart'] as $k => $item) {
                if($item['id'] === $_SESSION['cart'][$updateKey]['id'] && $k !== $updateKey) $totalInCart += $item['qty'];
            }

            if ($p && ($totalInCart + $newQty) > $p['stock']) { echo json_encode(['success'=>false,'message'=>'Max stock reached']); exit; }
            $_SESSION['cart'][$updateKey]['qty'] = $newQty;
            $qtyCount = $newQty;
        }
        
        $p = $p ?? getProductById($pdo, $productId);
        $price = $p ? ($p['discount_percent'] > 0 ? ($p['price'] * (1 - $p['discount_percent'] / 100)) : $p['price']) : 0;
        $summary = cartSummary($pdo);
        echo json_encode(['success'=>true,'qty'=>$qtyCount,'itemTotal'=>formatPrice($price * $qtyCount)]+$summary);
        break;

    case 'remove':
        $removeKey = $_POST['cart_key'] ?? $cartKey;
        unset($_SESSION['cart'][$removeKey]);
        $summary = cartSummary($pdo);
        echo json_encode(['success'=>true,'message'=>'Removed from cart']+$summary);
        break;

    case 'get':
        $summary = cartSummary($pdo);
        echo json_encode(['success'=>true]+$summary);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Invalid action']);
}
